<?php

namespace Tests\Feature;

use App\Models\FeeAssessment;
use App\Models\FeeCategory;
use App\Repositories\FeeRolloverRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesSchoolOsFixtures;
use Tests\TestCase;

/**
 * Design ref: 21-schoolos-finance-architecture.md §4,
 * 22-schoolos-finance-schema.md §2.
 * Decision ref: 16-schoolos-decisions-register.md D12.
 *
 * FeeRolloverRepository::rollOver() is D12's one write path — automatic,
 * traceable roll-over of any outstanding balance into the next academic
 * year, under the school-scoped, system-reserved 'rolled_over_debt'
 * category. This file's central assertion is idempotency: running the
 * same (school, fromYear, toYear) rollover twice must not double-create
 * rows, matching that repository's own doc comment. Phase6dTestGateTest's
 * D12 row calls into this same repository for its one summary
 * assertion; this file is the fuller regression suite behind it.
 */
class FeeRolloverRepositoryTest extends TestCase
{
    use RefreshDatabase;
    use CreatesSchoolOsFixtures;

    public function test_only_assessments_with_a_remaining_balance_are_rolled_over(): void
    {
        $repository = app(FeeRolloverRepository::class);

        $school = $this->makeSchool();
        $fromYear = $this->makeAcademicYear($school);
        $toYear = $this->makeAcademicYear($school);
        $student = $this->makeRoleUser('student', $school);
        $category = $this->makeFeeCategory($school);

        $paidOff = $this->makeFeeAssessment($school, $fromYear, $student, $category, ['base_amount' => '500.00']);
        $this->makePayment($school, $paidOff, ['amount' => '500.00', 'status' => 'confirmed']);

        $stillOwing = $this->makeFeeAssessment($school, $fromYear, $student, $category, ['base_amount' => '300.00']);
        $this->makePayment($school, $stillOwing, ['amount' => '100.00', 'status' => 'confirmed']);

        $rolled = $repository->rollOver($school->id, $fromYear->id, $toYear->id);

        $this->assertCount(1, $rolled);
        $this->assertSame($stillOwing->id, $rolled->first()->rolled_over_from_assessment_id);
        $this->assertSame('200.00', $rolled->first()->amount_due);
    }

    public function test_rolled_over_assessment_uses_the_reserved_category_and_links_back_to_its_origin(): void
    {
        $repository = app(FeeRolloverRepository::class);

        $school = $this->makeSchool();
        $fromYear = $this->makeAcademicYear($school);
        $toYear = $this->makeAcademicYear($school);
        $student = $this->makeRoleUser('student', $school);
        $category = $this->makeFeeCategory($school);

        $original = $this->makeFeeAssessment($school, $fromYear, $student, $category, ['base_amount' => '400.00']);

        $rolled = $repository->rollOver($school->id, $fromYear->id, $toYear->id)->first();

        $reservedCategory = FeeCategory::where('school_id', $school->id)
            ->where('key', 'rolled_over_debt')
            ->first();

        $this->assertNotNull($reservedCategory);
        $this->assertTrue($reservedCategory->is_system_reserved);
        $this->assertSame($reservedCategory->id, $rolled->fee_category_id);
        $this->assertSame($toYear->id, $rolled->academic_year_id);
        $this->assertSame($original->id, $rolled->rolled_over_from_assessment_id);
        $this->assertSame('0.00', $rolled->discount_amount);
        $this->assertSame('0.00', $rolled->scholarship_amount, 'A rolled-over balance is not re-discounted (21 §4).');
    }

    /**
     * D12's central guarantee: running the same year-boundary rollover
     * twice — the shape a retried scheduled job or an admin double-click
     * could produce — must not double-create rollover assessments.
     */
    public function test_rollover_is_idempotent_when_run_twice_for_the_same_year_pair(): void
    {
        $repository = app(FeeRolloverRepository::class);

        $school = $this->makeSchool();
        $fromYear = $this->makeAcademicYear($school);
        $toYear = $this->makeAcademicYear($school);
        $student = $this->makeRoleUser('student', $school);
        $category = $this->makeFeeCategory($school);

        $this->makeFeeAssessment($school, $fromYear, $student, $category, ['base_amount' => '600.00']);

        $firstRun = $repository->rollOver($school->id, $fromYear->id, $toYear->id);
        $secondRun = $repository->rollOver($school->id, $fromYear->id, $toYear->id);

        $this->assertCount(1, $firstRun);
        $this->assertCount(1, $secondRun);
        $this->assertSame($firstRun->first()->id, $secondRun->first()->id, 'A second run must reuse the existing rollover row, not create a new one.');
        $this->assertSame(
            1,
            FeeAssessment::where('academic_year_id', $toYear->id)->count(),
            'Exactly one rollover assessment must exist in the target year no matter how many times rollOver() runs.'
        );
    }

    public function test_rollover_does_not_reuse_the_reserved_category_seed_across_schools(): void
    {
        $repository = app(FeeRolloverRepository::class);

        $schoolA = $this->makeSchool();
        $schoolB = $this->makeSchool();
        $fromYearA = $this->makeAcademicYear($schoolA);
        $toYearA = $this->makeAcademicYear($schoolA);
        $fromYearB = $this->makeAcademicYear($schoolB);
        $toYearB = $this->makeAcademicYear($schoolB);
        $categoryA = $this->makeFeeCategory($schoolA);
        $categoryB = $this->makeFeeCategory($schoolB);
        $studentA = $this->makeRoleUser('student', $schoolA);
        $studentB = $this->makeRoleUser('student', $schoolB);

        $this->makeFeeAssessment($schoolA, $fromYearA, $studentA, $categoryA, ['base_amount' => '100.00']);
        $this->makeFeeAssessment($schoolB, $fromYearB, $studentB, $categoryB, ['base_amount' => '250.00']);

        $repository->rollOver($schoolA->id, $fromYearA->id, $toYearA->id);
        $repository->rollOver($schoolB->id, $fromYearB->id, $toYearB->id);

        // Each school gets its own school-scoped rolled_over_debt row, never a shared one —
        // assertDatabaseCount()'s third parameter is a connection name, not a message, so a
        // description belongs in a comment here rather than passed as an argument.
        $this->assertDatabaseCount('fee_categories', 4);
        $this->assertSame(
            2,
            FeeCategory::where('key', 'rolled_over_debt')->where('is_system_reserved', true)->count()
        );
    }

    public function test_a_rolled_over_assessment_pays_down_via_the_normal_payment_repository(): void
    {
        $rolloverRepository = app(FeeRolloverRepository::class);
        $paymentRepository = app(\App\Repositories\PaymentRepository::class);

        $school = $this->makeSchool();
        $fromYear = $this->makeAcademicYear($school);
        $toYear = $this->makeAcademicYear($school);
        $student = $this->makeRoleUser('student', $school);
        $category = $this->makeFeeCategory($school);
        $admin = $this->makeRoleUser('school_admin', $school);

        $this->makeFeeAssessment($school, $fromYear, $student, $category, ['base_amount' => '150.00']);

        $rolled = $rolloverRepository->rollOver($school->id, $fromYear->id, $toYear->id)->first();

        $paymentRepository->recordManualPayment($school->id, $rolled->id, '150.00', $admin);

        $this->assertSame('paid', $rolled->fresh()->status, 'A rollover assessment is a normal fee_assessment in every other respect (21 §4) — no parallel debt concept.');
    }
}