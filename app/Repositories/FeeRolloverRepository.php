<?php

namespace App\Repositories;

use App\Models\FeeAssessment;
use App\Models\FeeCategory;
use Illuminate\Support\Collection;

/**
 * Design ref: 21-schoolos-finance-architecture.md §4,
 * 22-schoolos-finance-schema.md §1/§2.
 * Decision ref: 16-schoolos-decisions-register.md D12.
 *
 * D12 calls this "automatic at year-end," but AcademicYear carries no
 * generic "year end date" field for a scheduler to key off of (no
 * cron-scheduled command exists anywhere in this codebase for academic-
 * year transitions) — inventing one here would be a schema addition
 * this pass was never asked to make. Noting that honestly: rollOver()
 * is an explicitly-invoked write path, keyed off explicit
 * $fromAcademicYearId/$toAcademicYearId, the same way
 * Console\Commands\RunScheduledExports is a real cron entry point for
 * track 8 but nothing equivalent exists yet for academic-year
 * transitions. Wiring this to an actual scheduled command, once that
 * end-date field exists, is future work outside this track's scope.
 *
 * Idempotent per (school, fromYear, toYear) pair (matching
 * IdentifierService's own doc comment on idempotent generation): running
 * this twice for the same year boundary does not double-create rollover
 * assessments, since each source assessment is checked for an existing
 * rolled_over_from_assessment_id link into the target year before a new
 * one is written.
 */
class FeeRolloverRepository
{
    private const RESERVED_CATEGORY_KEY = 'rolled_over_debt';

    /**
     * For every fee_assessment in $fromAcademicYearId with
     * amount_remaining > 0, creates (or, if already run for this school/
     * year pair, reuses) a linked fee_assessment in
     * $toAcademicYearId under the reserved 'rolled_over_debt' category,
     * per D12/22 §2.
     *
     * @return Collection<int, FeeAssessment> every rollover-created (or
     *         already-existing) fee_assessment for this run
     */
    public function rollOver(int $schoolId, int $fromAcademicYearId, int $toAcademicYearId): Collection
    {
        $reservedCategory = $this->reservedCategory($schoolId);

        $openAssessments = FeeAssessment::where('school_id', $schoolId)
            ->where('academic_year_id', $fromAcademicYearId)
            ->get()
            ->filter(fn (FeeAssessment $assessment) => bccomp($assessment->amountRemaining(), '0', 2) > 0);

        return $openAssessments->map(function (FeeAssessment $assessment) use ($schoolId, $toAcademicYearId, $reservedCategory) {
            $existing = FeeAssessment::where('rolled_over_from_assessment_id', $assessment->id)
                ->where('academic_year_id', $toAcademicYearId)
                ->first();

            if ($existing !== null) {
                return $existing;
            }

            $remaining = $assessment->amountRemaining();

            return FeeAssessment::create([
                'school_id' => $schoolId,
                'academic_year_id' => $toAcademicYearId,
                'student_id' => $assessment->student_id,
                'fee_category_id' => $reservedCategory->id,
                'base_amount' => $remaining,
                'discount_amount' => 0,
                'scholarship_amount' => 0,
                'amount_due' => $remaining,
                'rolled_over_from_assessment_id' => $assessment->id,
                'status' => 'open',
            ]);
        })->values();
    }

    /**
     * Seeds the school-scoped, system-reserved 'rolled_over_debt'
     * category the first time this school rolls over any debt — per
     * FeeCategory's own doc comment, never admin-creatable directly
     * (FeeCategoryRepository::create() rejects this key outright).
     */
    private function reservedCategory(int $schoolId): FeeCategory
    {
        return FeeCategory::firstOrCreate(
            ['school_id' => $schoolId, 'key' => self::RESERVED_CATEGORY_KEY],
            ['label' => 'Rolled Over Debt', 'is_system_reserved' => true]
        );
    }
}
