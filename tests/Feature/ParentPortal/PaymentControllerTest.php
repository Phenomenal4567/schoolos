<?php

namespace Tests\Feature\ParentPortal;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CreatesSchoolOsFixtures;
use Tests\TestCase;

/**
 * Design ref: 21-schoolos-finance-architecture.md §3, §5.
 * Decision ref: 16-schoolos-decisions-register.md D13.
 *
 * ParentPortal\PaymentController's HTTP-layer coverage — the write path
 * 20 §5's kickoff prompt singles out as strictly worse than a read-only
 * scope gap: a parent must never be able to initiate a payment against,
 * or upload a receipt against, another family's fee assessment. store()
 * only ever records a 'pending' Paystack-initiation row here — actual
 * confirmation is PaystackWebhookControllerTest's surface.
 */
class PaymentControllerTest extends TestCase
{
    use RefreshDatabase;
    use CreatesSchoolOsFixtures;

    public function test_guest_is_redirected_to_login(): void
    {
        $school = $this->makeSchool();
        $academicYear = $this->makeAcademicYear($school);
        $student = $this->makeRoleUser('student', $school);
        $category = $this->makeFeeCategory($school);
        $assessment = $this->makeFeeAssessment($school, $academicYear, $student, $category);

        $response = $this->post("/parent/fees/{$assessment->id}/payments", [
            'amount' => '100.00',
            'paystack_reference' => 'ref-1',
        ]);

        $response->assertRedirect('/login');
    }

    public function test_non_parent_role_is_forbidden(): void
    {
        $school = $this->makeSchool();
        $admin = $this->makeRoleUser('school_admin', $school);
        $academicYear = $this->makeAcademicYear($school);
        $student = $this->makeRoleUser('student', $school);
        $category = $this->makeFeeCategory($school);
        $assessment = $this->makeFeeAssessment($school, $academicYear, $student, $category);

        $response = $this->actingAs($admin)->post("/parent/fees/{$assessment->id}/payments", [
            'amount' => '100.00',
            'paystack_reference' => 'ref-2',
        ]);

        $response->assertForbidden();
    }

    public function test_a_linked_parent_can_initiate_a_payment_for_their_child(): void
    {
        $school = $this->makeSchool();
        $academicYear = $this->makeAcademicYear($school);
        $parent = $this->makeRoleUser('parent', $school);
        $child = $this->makeRoleUser('student', $school);
        $this->makeStudentParentLink($school, $parent, $child);
        $category = $this->makeFeeCategory($school);
        $assessment = $this->makeFeeAssessment($school, $academicYear, $child, $category, ['base_amount' => '750.00']);

        $response = $this->actingAs($parent)->post("/parent/fees/{$assessment->id}/payments", [
            'amount' => '750.00',
            'paystack_reference' => 'ref-parent-1',
        ]);

        $response->assertRedirect(route('parent.fees.show', $assessment));
        $this->assertDatabaseHas('payments', [
            'fee_assessment_id' => $assessment->id,
            'amount' => '750.00',
            'processor' => 'paystack',
            'paystack_reference' => 'ref-parent-1',
            'status' => 'pending',
        ]);
        $this->assertSame('750.00', $assessment->fresh()->amountRemaining(), 'A pending payment must not yet pay down the balance.');
    }

    public function test_a_linked_parent_can_upload_a_manual_payment_receipt_for_review(): void
    {
        Storage::fake('public');

        $school = $this->makeSchool();
        $academicYear = $this->makeAcademicYear($school);
        $parent = $this->makeRoleUser('parent', $school);
        $child = $this->makeRoleUser('student', $school);
        $this->makeStudentParentLink($school, $parent, $child);
        $category = $this->makeFeeCategory($school);
        $assessment = $this->makeFeeAssessment($school, $academicYear, $child, $category, ['base_amount' => '750.00']);

        $response = $this->actingAs($parent)->post("/parent/fees/{$assessment->id}/payments", [
            'payment_method' => 'receipt_upload',
            'amount' => '500.00',
            'receipt' => UploadedFile::fake()->create('receipt.pdf', 120, 'application/pdf'),
        ]);

        $response->assertRedirect(route('parent.fees.show', $assessment));

        $payment = $assessment->payments()->firstOrFail();
        $this->assertSame('manual', $payment->processor);
        $this->assertSame('pending', $payment->status);
        $this->assertSame($parent->id, $payment->recorded_by);
        $this->assertNotNull($payment->receipt_upload_path);
        Storage::disk('public')->assertExists($payment->receipt_upload_path);
        $this->assertSame('750.00', $assessment->fresh()->amountRemaining(), 'A pending receipt must not yet pay down the balance.');
    }

    public function test_a_json_caller_gets_a_201_with_the_created_payment(): void
    {
        $school = $this->makeSchool();
        $academicYear = $this->makeAcademicYear($school);
        $parent = $this->makeRoleUser('parent', $school);
        $child = $this->makeRoleUser('student', $school);
        $this->makeStudentParentLink($school, $parent, $child);
        $category = $this->makeFeeCategory($school);
        $assessment = $this->makeFeeAssessment($school, $academicYear, $child, $category, ['base_amount' => '400.00']);

        $response = $this->actingAs($parent)->postJson("/parent/fees/{$assessment->id}/payments", [
            'amount' => '400.00',
            'paystack_reference' => 'ref-json-1',
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.fee_assessment_id', $assessment->id);
    }

    /**
     * The critical write-side case: a parent must never be able to
     * initiate a payment against another family's assessment. 404, not
     * 403, matching the read-side FeeControllerTest, and — the part
     * that matters most here — no payment row is created at all.
     */
    public function test_a_parent_cannot_pay_against_an_assessment_for_a_student_who_is_not_their_linked_child(): void
    {
        $school = $this->makeSchool();
        $academicYear = $this->makeAcademicYear($school);
        $parent = $this->makeRoleUser('parent', $school);
        $unrelatedStudent = $this->makeRoleUser('student', $school);
        $category = $this->makeFeeCategory($school);
        $assessment = $this->makeFeeAssessment($school, $academicYear, $unrelatedStudent, $category);

        $response = $this->actingAs($parent)->post("/parent/fees/{$assessment->id}/payments", [
            'amount' => '100.00',
            'paystack_reference' => 'ref-should-not-exist',
        ]);

        $response->assertNotFound();
        $this->assertDatabaseCount('payments', 0);
    }

    public function test_a_parent_cannot_upload_a_receipt_against_an_unlinked_students_assessment(): void
    {
        Storage::fake('public');

        $school = $this->makeSchool();
        $academicYear = $this->makeAcademicYear($school);
        $parent = $this->makeRoleUser('parent', $school);
        $unrelatedStudent = $this->makeRoleUser('student', $school);
        $category = $this->makeFeeCategory($school);
        $assessment = $this->makeFeeAssessment($school, $academicYear, $unrelatedStudent, $category);

        $response = $this->actingAs($parent)->post("/parent/fees/{$assessment->id}/payments", [
            'payment_method' => 'receipt_upload',
            'amount' => '100.00',
            'receipt' => UploadedFile::fake()->create('receipt.pdf', 120, 'application/pdf'),
        ]);

        $response->assertNotFound();
        $this->assertDatabaseCount('payments', 0);
        Storage::disk('public')->assertMissing('payment-receipts/receipt.pdf');
    }

    public function test_a_parent_cannot_pay_against_an_assessment_from_another_school(): void
    {
        $school = $this->makeSchool();
        $otherSchool = $this->makeSchool();
        $parent = $this->makeRoleUser('parent', $school);
        $otherYear = $this->makeAcademicYear($otherSchool);
        $otherStudent = $this->makeRoleUser('student', $otherSchool);
        $otherCategory = $this->makeFeeCategory($otherSchool);
        $assessmentInOtherSchool = $this->makeFeeAssessment($otherSchool, $otherYear, $otherStudent, $otherCategory);

        $response = $this->actingAs($parent)->post("/parent/fees/{$assessmentInOtherSchool->id}/payments", [
            'amount' => '50.00',
            'paystack_reference' => 'ref-cross-tenant',
        ]);

        $response->assertNotFound();
        $this->assertDatabaseCount('payments', 0);
    }

    public function test_an_amount_exceeding_amount_remaining_is_rejected_without_writing_a_row(): void
    {
        $school = $this->makeSchool();
        $academicYear = $this->makeAcademicYear($school);
        $parent = $this->makeRoleUser('parent', $school);
        $child = $this->makeRoleUser('student', $school);
        $this->makeStudentParentLink($school, $parent, $child);
        $category = $this->makeFeeCategory($school);
        $assessment = $this->makeFeeAssessment($school, $academicYear, $child, $category, ['base_amount' => '200.00']);

        $response = $this->actingAs($parent)->post("/parent/fees/{$assessment->id}/payments", [
            'amount' => '200.01',
            'paystack_reference' => 'ref-too-much',
        ]);

        $response->assertSessionHasErrors('amount');
        $this->assertDatabaseCount('payments', 0);
    }

    public function test_a_json_caller_gets_a_422_for_an_amount_exceeding_amount_remaining(): void
    {
        $school = $this->makeSchool();
        $academicYear = $this->makeAcademicYear($school);
        $parent = $this->makeRoleUser('parent', $school);
        $child = $this->makeRoleUser('student', $school);
        $this->makeStudentParentLink($school, $parent, $child);
        $category = $this->makeFeeCategory($school);
        $assessment = $this->makeFeeAssessment($school, $academicYear, $child, $category, ['base_amount' => '200.00']);

        $response = $this->actingAs($parent)->postJson("/parent/fees/{$assessment->id}/payments", [
            'amount' => '200.01',
            'paystack_reference' => 'ref-too-much-json',
        ]);

        $response->assertStatus(422);
        $this->assertDatabaseCount('payments', 0);
    }
}
