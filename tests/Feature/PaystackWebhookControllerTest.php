<?php

namespace Tests\Feature;

use App\Contracts\PaystackClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesSchoolOsFixtures;
use Tests\Support\FakePaystackClient;
use Tests\TestCase;

/**
 * Design ref: 21-schoolos-finance-architecture.md §3.
 * Decision ref: 16-schoolos-decisions-register.md D13.
 *
 * PaystackWebhookController's HTTP-layer coverage — public, no 'auth'
 * middleware (Paystack has no SchoolOS session), but never trusts the
 * posted event's own claimed status: a 'charge.success' event is only
 * ever confirmed after PaymentRepository::confirmOnlinePayment() itself
 * re-verifies the reference against PaystackClient (D13). This file
 * fakes that client the same way PaymentRepositoryTest does, and drives
 * the flow entirely through HTTP, matching how Paystack actually calls
 * this endpoint.
 */
class PaystackWebhookControllerTest extends TestCase
{
    use RefreshDatabase;
    use CreatesSchoolOsFixtures;

    public function test_a_charge_success_event_confirms_the_payment_once_paystack_verifies_it(): void
    {
        $this->app->instance(PaystackClient::class, (new FakePaystackClient())->verifyAs('ref-webhook-ok', true));

        $school = $this->makeSchool();
        $academicYear = $this->makeAcademicYear($school);
        $student = $this->makeRoleUser('student', $school);
        $category = $this->makeFeeCategory($school);
        $assessment = $this->makeFeeAssessment($school, $academicYear, $student, $category, ['base_amount' => '1000.00']);
        $this->makePayment($school, $assessment, [
            'amount' => '1000.00',
            'processor' => 'paystack',
            'paystack_reference' => 'ref-webhook-ok',
            'status' => 'pending',
            'recorded_by' => null,
        ]);

        $response = $this->postJson('/webhooks/paystack', [
            'event' => 'charge.success',
            'data' => ['reference' => 'ref-webhook-ok'],
        ]);

        $response->assertOk();
        $response->assertJson(['received' => true]);
        $this->assertDatabaseHas('payments', ['paystack_reference' => 'ref-webhook-ok', 'status' => 'confirmed']);
        $this->assertSame('paid', $assessment->fresh()->status);
    }

    /**
     * D13's central rule, exercised through the actual public endpoint:
     * a 'charge.success' payload claiming success is not enough — if
     * PaystackClient itself does not confirm the reference, the payment
     * must be marked 'failed', never 'confirmed'.
     */
    public function test_a_charge_success_event_does_not_confirm_a_payment_paystack_does_not_verify(): void
    {
        $this->app->instance(PaystackClient::class, (new FakePaystackClient())->verifyAs('ref-webhook-forged', false));

        $school = $this->makeSchool();
        $academicYear = $this->makeAcademicYear($school);
        $student = $this->makeRoleUser('student', $school);
        $category = $this->makeFeeCategory($school);
        $assessment = $this->makeFeeAssessment($school, $academicYear, $student, $category, ['base_amount' => '1000.00']);
        $this->makePayment($school, $assessment, [
            'amount' => '1000.00',
            'processor' => 'paystack',
            'paystack_reference' => 'ref-webhook-forged',
            'status' => 'pending',
            'recorded_by' => null,
        ]);

        $response = $this->postJson('/webhooks/paystack', [
            'event' => 'charge.success',
            'data' => ['reference' => 'ref-webhook-forged'],
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('payments', ['paystack_reference' => 'ref-webhook-forged', 'status' => 'failed']);
        $this->assertSame('1000.00', $assessment->fresh()->amountRemaining());
    }

    public function test_a_non_success_event_marks_the_payment_failed_without_a_ledger_effect(): void
    {
        $this->app->instance(PaystackClient::class, new FakePaystackClient());

        $school = $this->makeSchool();
        $academicYear = $this->makeAcademicYear($school);
        $student = $this->makeRoleUser('student', $school);
        $category = $this->makeFeeCategory($school);
        $assessment = $this->makeFeeAssessment($school, $academicYear, $student, $category, ['base_amount' => '1000.00']);
        $this->makePayment($school, $assessment, [
            'amount' => '1000.00',
            'processor' => 'paystack',
            'paystack_reference' => 'ref-webhook-declined',
            'status' => 'pending',
            'recorded_by' => null,
        ]);

        $response = $this->postJson('/webhooks/paystack', [
            'event' => 'charge.failed',
            'data' => ['reference' => 'ref-webhook-declined'],
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('payments', ['paystack_reference' => 'ref-webhook-declined', 'status' => 'failed']);
        $this->assertSame('1000.00', $assessment->fresh()->amountRemaining());
    }

    public function test_a_webhook_for_an_unknown_reference_still_returns_200_without_error(): void
    {
        $this->app->instance(PaystackClient::class, new FakePaystackClient());

        $response = $this->postJson('/webhooks/paystack', [
            'event' => 'charge.success',
            'data' => ['reference' => 'no-such-reference'],
        ]);

        $response->assertOk();
        $response->assertJson(['received' => true]);
    }

    /**
     * A retried webhook delivery for a reference that's already
     * confirmed must be a safe no-op — same idempotency guarantee
     * PaymentRepositoryTest exercises directly, here driven end to end
     * through the endpoint Paystack actually retries against.
     */
    public function test_a_retried_webhook_for_an_already_confirmed_reference_is_idempotent(): void
    {
        $this->app->instance(PaystackClient::class, (new FakePaystackClient())->verifyAs('ref-webhook-retry', true));

        $school = $this->makeSchool();
        $academicYear = $this->makeAcademicYear($school);
        $student = $this->makeRoleUser('student', $school);
        $category = $this->makeFeeCategory($school);
        $assessment = $this->makeFeeAssessment($school, $academicYear, $student, $category, ['base_amount' => '1000.00']);
        $this->makePayment($school, $assessment, [
            'amount' => '1000.00',
            'processor' => 'paystack',
            'paystack_reference' => 'ref-webhook-retry',
            'status' => 'pending',
            'recorded_by' => null,
        ]);

        $this->postJson('/webhooks/paystack', [
            'event' => 'charge.success',
            'data' => ['reference' => 'ref-webhook-retry'],
        ])->assertOk();

        $retryResponse = $this->postJson('/webhooks/paystack', [
            'event' => 'charge.success',
            'data' => ['reference' => 'ref-webhook-retry'],
        ]);

        $retryResponse->assertOk();
        $this->assertDatabaseCount('payments', 1);
        $this->assertSame('paid', $assessment->fresh()->status);
    }

    public function test_the_webhook_route_requires_no_authentication(): void
    {
        $this->app->instance(PaystackClient::class, new FakePaystackClient());

        $response = $this->postJson('/webhooks/paystack', [
            'event' => 'charge.success',
            'data' => ['reference' => 'ref-no-auth-needed'],
        ]);

        $response->assertOk();
    }
}