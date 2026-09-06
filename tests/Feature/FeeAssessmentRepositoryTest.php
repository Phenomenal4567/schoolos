<?php

namespace Tests\Feature;

use App\Exceptions\Fee\InvalidFeeAssessmentTargetFailure;
use App\Models\FeeAssessment;
use App\Repositories\FeeAssessmentRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesSchoolOsFixtures;
use Tests\TestCase;

/**
 * Design ref: 21-schoolos-finance-architecture.md §2,
 * 22-schoolos-finance-schema.md §2.
 * Decision ref: 16-schoolos-decisions-register.md D11.
 *
 * FeeAssessmentRepository::assess()/adjust() is the one write path for
 * fee_assessments (that class's own doc comment) — this file exercises
 * D11's actual invariant directly against it: amount_due is computed
 * once, at INSERT time, from base_amount/discount_amount/
 * scholarship_amount, and is never rewritten by a later grant once
 * payments exist against the row. Phase6dTestGateTest's own D11 row
 * calls into this same repository for its one summary assertion; this
 * file is the fuller regression suite behind that row, the same
 * division Phase6a/Phase6b's gate files draw against their own
 * dedicated repository tests.
 */
class FeeAssessmentRepositoryTest extends TestCase
{
    use RefreshDatabase;
    use CreatesSchoolOsFixtures;

    public function test_amount_due_is_base_amount_when_no_discount_or_scholarship_applies(): void
    {
        $repository = app(FeeAssessmentRepository::class);

        $school = $this->makeSchool();
        $academicYear = $this->makeAcademicYear($school);
        $student = $this->makeRoleUser('student', $school);
        $category = $this->makeFeeCategory($school);
        $admin = $this->makeRoleUser('school_admin', $school);

        $assessment = $repository->assess(
            $school->id,
            $academicYear->id,
            $student->id,
            $category->id,
            '1000.00',
            $admin,
        );

        $this->assertSame('0.00', $assessment->discount_amount);
        $this->assertSame('0.00', $assessment->scholarship_amount);
        $this->assertSame('1000.00', $assessment->amount_due);
    }

    public function test_a_discount_at_assessment_time_reduces_amount_due_and_is_fixed(): void
    {
        $repository = app(FeeAssessmentRepository::class);

        $school = $this->makeSchool();
        $academicYear = $this->makeAcademicYear($school);
        $student = $this->makeRoleUser('student', $school);
        $category = $this->makeFeeCategory($school);
        $admin = $this->makeRoleUser('school_admin', $school);

        $assessment = $repository->assess(
            $school->id,
            $academicYear->id,
            $student->id,
            $category->id,
            '1000.00',
            $admin,
            ['type' => 'percentage', 'value' => '10'],
        );

        $this->assertSame('100.00', $assessment->discount_amount);
        $this->assertSame('900.00', $assessment->amount_due);
        $this->assertDatabaseHas('discounts', [
            'fee_assessment_id' => $assessment->id,
            'type' => 'percentage',
            'value' => '10.00',
            'granted_by' => $admin->id,
        ]);
    }

    public function test_a_full_scholarship_zeroes_amount_due(): void
    {
        $repository = app(FeeAssessmentRepository::class);

        $school = $this->makeSchool();
        $academicYear = $this->makeAcademicYear($school);
        $student = $this->makeRoleUser('student', $school);
        $category = $this->makeFeeCategory($school);
        $admin = $this->makeRoleUser('school_admin', $school);

        $this->makeScholarship($school, $student, $academicYear, $admin, ['type' => 'full']);

        $assessment = $repository->assess(
            $school->id,
            $academicYear->id,
            $student->id,
            $category->id,
            '1000.00',
            $admin,
        );

        $this->assertSame('1000.00', $assessment->scholarship_amount);
        $this->assertSame('0.00', $assessment->amount_due);
    }

    public function test_a_specific_exemption_scholarship_only_applies_to_its_own_fee_category(): void
    {
        $repository = app(FeeAssessmentRepository::class);

        $school = $this->makeSchool();
        $academicYear = $this->makeAcademicYear($school);
        $student = $this->makeRoleUser('student', $school);
        $uniformCategory = $this->makeFeeCategory($school, ['key' => 'uniform', 'label' => 'Uniform']);
        $tuitionCategory = $this->makeFeeCategory($school, ['key' => 'tuition', 'label' => 'Tuition']);
        $admin = $this->makeRoleUser('school_admin', $school);

        $this->makeScholarship($school, $student, $academicYear, $admin, [
            'type' => 'specific_exemption',
            'fee_category_id' => $uniformCategory->id,
        ]);

        $uniformAssessment = $repository->assess(
            $school->id, $academicYear->id, $student->id, $uniformCategory->id, '200.00', $admin,
        );
        $tuitionAssessment = $repository->assess(
            $school->id, $academicYear->id, $student->id, $tuitionCategory->id, '5000.00', $admin,
        );

        $this->assertSame('0.00', $uniformAssessment->amount_due);
        $this->assertSame('5000.00', $tuitionAssessment->amount_due, 'A specific_exemption must not bleed into other fee categories.');
    }

    public function test_assess_rejects_a_target_whose_role_is_not_student(): void
    {
        $repository = app(FeeAssessmentRepository::class);

        $school = $this->makeSchool();
        $academicYear = $this->makeAcademicYear($school);
        $category = $this->makeFeeCategory($school);
        $admin = $this->makeRoleUser('school_admin', $school);
        $teacher = $this->makeRoleUser('teacher', $school);

        $this->expectException(InvalidFeeAssessmentTargetFailure::class);

        $repository->assess($school->id, $academicYear->id, $teacher->id, $category->id, '1000.00', $admin);
    }

    /**
     * D11's core invariant: once payments exist against an assessment,
     * amount_due on that row is never rewritten, ever again — even by
     * adjust(), the method that exists specifically to handle a
     * discount granted after the fact.
     */
    public function test_adjust_never_rewrites_amount_due_on_an_assessment_with_existing_payments(): void
    {
        $repository = app(FeeAssessmentRepository::class);

        $school = $this->makeSchool();
        $academicYear = $this->makeAcademicYear($school);
        $student = $this->makeRoleUser('student', $school);
        $category = $this->makeFeeCategory($school);
        $admin = $this->makeRoleUser('school_admin', $school);

        $assessment = $repository->assess(
            $school->id, $academicYear->id, $student->id, $category->id, '1000.00', $admin,
        );
        $this->makePayment($school, $assessment, ['amount' => '400.00', 'status' => 'confirmed']);

        $repository->adjust($assessment->id, $admin, ['type' => 'fixed', 'value' => '150.00']);

        $assessment->refresh();
        $this->assertSame('1000.00', $assessment->amount_due, 'The original assessment\'s amount_due must be untouched by adjust().');
        $this->assertSame('0.00', $assessment->discount_amount, 'The original discount_amount must also be untouched.');
    }

    /**
     * The escape hatch itself: adjust() creates a distinct new row
     * (a visible credit line item) rather than mutating the original —
     * computed against the *original* base_amount, per this repository's
     * own doc comment, not against an already-reduced remaining figure.
     */
    public function test_adjust_creates_a_separate_credit_assessment_computed_against_original_base_amount(): void
    {
        $repository = app(FeeAssessmentRepository::class);

        $school = $this->makeSchool();
        $academicYear = $this->makeAcademicYear($school);
        $student = $this->makeRoleUser('student', $school);
        $category = $this->makeFeeCategory($school);
        $admin = $this->makeRoleUser('school_admin', $school);

        $assessment = $repository->assess(
            $school->id, $academicYear->id, $student->id, $category->id, '1000.00', $admin,
        );
        $this->makePayment($school, $assessment, ['amount' => '400.00', 'status' => 'confirmed']);

        $adjustment = $repository->adjust($assessment->id, $admin, ['type' => 'percentage', 'value' => '10']);

        $this->assertNotSame($assessment->id, $adjustment->id);
        $this->assertSame($student->id, $adjustment->student_id);
        $this->assertSame($category->id, $adjustment->fee_category_id);
        // 10% of the *original* 1000.00 base_amount, not of the 600.00
        // amount_remaining at adjustment time.
        $this->assertSame('-100.00', $adjustment->base_amount);
        $this->assertSame('-100.00', $adjustment->amount_due);
        $this->assertDatabaseCount('fee_assessments', 2);
    }

    public function test_a_discount_can_never_push_amount_due_negative_at_assessment_time(): void
    {
        $repository = app(FeeAssessmentRepository::class);

        $school = $this->makeSchool();
        $academicYear = $this->makeAcademicYear($school);
        $student = $this->makeRoleUser('student', $school);
        $category = $this->makeFeeCategory($school);
        $admin = $this->makeRoleUser('school_admin', $school);

        $assessment = $repository->assess(
            $school->id, $academicYear->id, $student->id, $category->id, '100.00', $admin,
            ['type' => 'fixed', 'value' => '9999.00'],
        );

        $this->assertSame('100.00', $assessment->discount_amount, 'A discount is capped at the amount it discounts from.');
        $this->assertSame('0.00', $assessment->amount_due);
    }

    public function test_amount_remaining_is_never_a_stored_column_and_always_reflects_confirmed_payments_only(): void
    {
        $repository = app(FeeAssessmentRepository::class);

        $school = $this->makeSchool();
        $academicYear = $this->makeAcademicYear($school);
        $student = $this->makeRoleUser('student', $school);
        $category = $this->makeFeeCategory($school);
        $admin = $this->makeRoleUser('school_admin', $school);

        $assessment = $repository->assess(
            $school->id, $academicYear->id, $student->id, $category->id, '1000.00', $admin,
        );

        $this->assertFalse(
            \Illuminate\Support\Facades\Schema::hasColumn('fee_assessments', 'amount_remaining'),
            'amount_remaining must never become a real column — it is always derived (21 §2, 22 §2).'
        );

        $this->makePayment($school, $assessment, ['amount' => '300.00', 'status' => 'confirmed']);
        $this->makePayment($school, $assessment, ['amount' => '250.00', 'status' => 'pending']);
        $this->makePayment($school, $assessment, ['amount' => '9999.00', 'status' => 'failed']);

        $this->assertSame('700.00', $assessment->fresh()->amountRemaining(), 'Only confirmed payments count toward amount_remaining.');
    }
}