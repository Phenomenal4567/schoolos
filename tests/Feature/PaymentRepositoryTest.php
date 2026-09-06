<?php

namespace Tests\Feature;

use App\Contracts\PaystackClient;
use App\Exceptions\Payment\InvalidPaymentAmountFailure;
use App\Exceptions\Payment\PaystackVerificationFailure;
use App\Repositories\PaymentRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesSchoolOsFixtures;
use Tests\Support\FakePaystackClient;
use Tests\TestCase;

/**
 * Design ref: 21-schoolos-finance-architecture.md §3,
 * 22-schoolos-finance-schema.md §4.
 * Decision ref: 16-schoolos-decisions-register.md D13.
 *
 * PaymentRepository::confirmOnlinePayment() is the only code path that
 * moves a Paystack payment from 'pending' to 'confirmed' — this file's
 * central assertions are that it never does so without
 * PaystackClient::verifyTransaction() actually confirming the
 * transaction first (D13's "never trust a client-supplied success
 * callback alone" rule), and that a retry against an already-confirmed
 * reference is a no-op rather than a second verify call or a double
 * count. FakePaystackClient (tests/Support) stands in for the real HTTP
 * client per that interface's own doc comment. Phase6dTestGateTest's D13
 * row calls into this same repository for its one summary assertion;
 * this file is the fuller regression suite behind it.
 */
class PaymentRepositoryTest extends TestCase
{
    use RefreshDatabase;
    use CreatesSchoolOsFixtures;

    private function repositoryWithFakePaystack(FakePaystackClient $fake): PaymentRepository
    {
        $this->app->instance(PaystackClient::class, $fake);

        return $this->app->make(PaymentRepository::class);
    }

    public function test_manual_payment_is_confirmed_immediately_and_reduces_amount_remaining(): void
    {
        $repository = $this->repositoryWithFakePaystack(new FakePaystackClient());

        $school = $this->makeSchool();
        $academicYear = $this->makeAcademicYear($school);
        $student = $this->makeRoleUser('student', $school);
        $category = $this->makeFeeCategory($school);
        $admin = $this->makeRoleUser('school_admin', $school);
        $assessment = $this->makeFeeAssessment($school, $academicYear, $student, $category, ['base_amount' => '1000.00']);

        $payment = $repository->recordManualPayment($school->id, $assessment->id, '400.00', $admin);

        $this->assertSame('confirmed', $payment->status);
        $this->assertSame('manual', $payment->processor);
        $this->assertSame($admin->id, $payment->recorded_by);
        $this->assertSame('600.00', $assessment->fresh()->amountRemaining());
        $this->assertSame('partially_paid', $assessment->fresh()->status);
    }

    public function test_manual_payment_rejects_an_amount_exceeding_amount_remaining(): void
    {
        $repository = $this->repositoryWithFakePaystack(new FakePaystackClient());

        $school = $this->makeSchool();
        $academicYear = $this->makeAcademicYear($school);
        $student = $this->makeRoleUser('student', $school);
        $category = $this->makeFeeCategory($school);
        $admin = $this->makeRoleUser('school_admin', $school);
        $assessment = $this->makeFeeAssessment($school, $academicYear, $student, $category, ['base_amount' => '500.00']);

        $this->expectException(InvalidPaymentAmountFailure::class);

        $repository->recordManualPayment($school->id, $assessment->id, '500.01', $admin);
    }

    public function test_manual_payment_rejects_a_non_positive_amount(): void
    {
        $repository = $this->repositoryWithFakePaystack(new FakePaystackClient());

        $school = $this->makeSchool();
        $academicYear = $this->makeAcademicYear($school);
        $student = $this->makeRoleUser('student', $school);
        $category = $this->makeFeeCategory($school);
        $admin = $this->makeRoleUser('school_admin', $school);
        $assessment = $this->makeFeeAssessment($school, $academicYear, $student, $category, ['base_amount' => '500.00']);

        $this->expectException(InvalidPaymentAmountFailure::class);

        $repository->recordManualPayment($school->id, $assessment->id, '0.00', $admin);
    }

    public function test_initiating_an_online_payment_records_pending_and_has_no_ledger_effect(): void
    {
        $repository = $this->repositoryWithFakePaystack(new FakePaystackClient());

        $school = $this->makeSchool();
        $academicYear = $this->makeAcademicYear($school);
        $student = $this->makeRoleUser('student', $school);
        $category = $this->makeFeeCategory($school);
        $assessment = $this->makeFeeAssessment($school, $academicYear, $student, $category, ['base_amount' => '1000.00']);

        $payment = $repository->initiateOnlinePayment($school->id, $assessment->id, '1000.00', 'ref-abc-123');

        $this->assertSame('pending', $payment->status);
        $this->assertSame('paystack', $payment->processor);
        $this->assertNull($payment->recorded_by);
        $this->assertSame('1000.00', $assessment->fresh()->amountRemaining(), 'A pending payment must not appear to pay down the balance.');
    }

    public function test_parent_submitted_manual_receipt_is_pending_and_has_no_ledger_effect(): void
    {
        $repository = $this->repositoryWithFakePaystack(new FakePaystackClient());

        $school = $this->makeSchool();
        $academicYear = $this->makeAcademicYear($school);
        $student = $this->makeRoleUser('student', $school);
        $parent = $this->makeRoleUser('parent', $school);
        $category = $this->makeFeeCategory($school);
        $assessment = $this->makeFeeAssessment($school, $academicYear, $student, $category, ['base_amount' => '1000.00']);

        $payment = $repository->submitManualReceipt(
            $school->id,
            $assessment->id,
            '400.00',
            $parent,
            'payment-receipts/receipt.pdf',
        );

        $this->assertSame('pending', $payment->status);
        $this->assertSame('manual', $payment->processor);
        $this->assertSame('payment-receipts/receipt.pdf', $payment->receipt_upload_path);
        $this->assertSame($parent->id, $payment->recorded_by);
        $this->assertSame('1000.00', $assessment->fresh()->amountRemaining());
        $this->assertSame('open', $assessment->fresh()->status);
    }

    /**
     * D13's central rule: confirmOnlinePayment() only flips the row to
     * 'confirmed' — and only then reduces amount_remaining — once
     * PaystackClient::verifyTransaction() itself returns true. Never
     * based on anything the webhook payload merely claimed.
     */
    public function test_confirm_online_payment_only_confirms_after_paystack_verifies_it(): void
    {
        $fake = (new FakePaystackClient())->verifyAs('ref-verified', true);
        $repository = $this->repositoryWithFakePaystack($fake);

        $school = $this->makeSchool();
        $academicYear = $this->makeAcademicYear($school);
        $student = $this->makeRoleUser('student', $school);
        $category = $this->makeFeeCategory($school);
        $assessment = $this->makeFeeAssessment($school, $academicYear, $student, $category, ['base_amount' => '1000.00']);
        $repository->initiateOnlinePayment($school->id, $assessment->id, '1000.00', 'ref-verified');

        $confirmed = $repository->confirmOnlinePayment('ref-verified');

        $this->assertSame('confirmed', $confirmed->status);
        $this->assertSame('0.00', $assessment->fresh()->amountRemaining());
        $this->assertSame('paid', $assessment->fresh()->status);
    }

    /**
     * The negative case this whole decision exists for: a reference
     * Paystack's own API does not confirm must never be written as
     * 'confirmed', no matter what the caller (a forged or malformed
     * webhook payload) claimed about it.
     */
    public function test_confirm_online_payment_raises_and_does_not_confirm_when_paystack_does_not_verify(): void
    {
        $fake = (new FakePaystackClient())->verifyAs('ref-unverified', false);
        $repository = $this->repositoryWithFakePaystack($fake);

        $school = $this->makeSchool();
        $academicYear = $this->makeAcademicYear($school);
        $student = $this->makeRoleUser('student', $school);
        $category = $this->makeFeeCategory($school);
        $assessment = $this->makeFeeAssessment($school, $academicYear, $student, $category, ['base_amount' => '1000.00']);
        $repository->initiateOnlinePayment($school->id, $assessment->id, '1000.00', 'ref-unverified');

        try {
            $repository->confirmOnlinePayment('ref-unverified');
            $this->fail('Expected PaystackVerificationFailure to be thrown.');
        } catch (PaystackVerificationFailure $e) {
            $this->assertStringContainsString('ref-unverified', $e->getMessage());
        }

        $this->assertDatabaseHas('payments', [
            'paystack_reference' => 'ref-unverified',
            'status' => 'pending',
        ]);
        $this->assertSame('1000.00', $assessment->fresh()->amountRemaining());
    }

    /**
     * Idempotency: a webhook retry against an already-confirmed
     * reference must be a no-op — critically, it must not call
     * verifyTransaction() again at all, since a second webhook delivery
     * for a settled payment should never be able to flip it back out of
     * 'confirmed' even if a caller supplied a client that would now
     * answer differently.
     */
    public function test_confirm_online_payment_is_idempotent_and_does_not_re_verify_an_already_confirmed_reference(): void
    {
        $fake = (new FakePaystackClient())->verifyAs('ref-retry', true);
        $repository = $this->repositoryWithFakePaystack($fake);

        $school = $this->makeSchool();
        $academicYear = $this->makeAcademicYear($school);
        $student = $this->makeRoleUser('student', $school);
        $category = $this->makeFeeCategory($school);
        $assessment = $this->makeFeeAssessment($school, $academicYear, $student, $category, ['base_amount' => '1000.00']);
        $repository->initiateOnlinePayment($school->id, $assessment->id, '1000.00', 'ref-retry');
        $repository->confirmOnlinePayment('ref-retry');

        // Flip the fake to now deny verification — if confirmOnlinePayment()
        // re-verified on this second call, it would throw. It must not.
        $fake->verifyAs('ref-retry', false);

        $secondResult = $repository->confirmOnlinePayment('ref-retry');

        $this->assertSame('confirmed', $secondResult->status);
        $this->assertDatabaseCount('payments', 1);
        $this->assertSame('0.00', $assessment->fresh()->amountRemaining());
    }

    public function test_mark_online_payment_failed_has_no_ledger_effect(): void
    {
        $repository = $this->repositoryWithFakePaystack(new FakePaystackClient());

        $school = $this->makeSchool();
        $academicYear = $this->makeAcademicYear($school);
        $student = $this->makeRoleUser('student', $school);
        $category = $this->makeFeeCategory($school);
        $assessment = $this->makeFeeAssessment($school, $academicYear, $student, $category, ['base_amount' => '1000.00']);
        $repository->initiateOnlinePayment($school->id, $assessment->id, '1000.00', 'ref-failed');

        $failed = $repository->markOnlinePaymentFailed('ref-failed');

        $this->assertSame('failed', $failed->status);
        $this->assertSame('1000.00', $assessment->fresh()->amountRemaining());
    }
}
