<?php

namespace App\Repositories;

use App\Models\AcademicYear;
use App\Models\PromotionRule;
use App\Models\Standard;
use App\Models\User;

/**
 * Design ref: 19-discovery-hierarchy-gap-closure-plan.md §8
 * Decision ref: 16-schoolos-decisions-register.md D15
 *
 * The one write path for promotion_rules — only 'school_admin' may
 * author a rule (discovery §9.1: "school defines its promotion
 * criteria"), same role-gate shape as
 * CalendarEventRepository::assertActorMayAuthor(). setRule() upserts
 * against the UNIQUE(school_id, academic_year_id, standard_id)
 * constraint (see the 2026_09_02_000001 migration's doc comment) rather
 * than rejecting a second call for the same triple — a school revising
 * its own threshold before running promotion is a correction, not a
 * business-rule violation, unlike EnrollmentRepository::enroll()'s
 * double-enrollment case.
 */
class PromotionRuleRepository
{
    /**
     * v1 (D15): the only recognized key is 'min_aggregate', a
     * percentage 0-100. See this file's own migration doc comment for
     * why `criteria` is JSON rather than a flat column despite v1 only
     * using one key.
     *
     * @param  array{min_aggregate: float}  $criteria
     *
     * @throws \InvalidArgumentException if $actor isn't 'school_admin',
     *         if criteria['min_aggregate'] is missing or not a number
     *         in [0, 100], or if $academicYearId/$standardId don't
     *         belong to $schoolId.
     */
    public function setRule(
        int $schoolId,
        int $academicYearId,
        int $standardId,
        array $criteria,
        User $actor
    ): PromotionRule {
        $this->assertActorMayAuthor($actor);
        $this->assertValidCriteria($criteria);

        $academicYear = AcademicYear::findOrFail($academicYearId);

        if ($academicYear->school_id !== $schoolId) {
            throw new \InvalidArgumentException(
                "PromotionRuleRepository::setRule(): academic_year #{$academicYearId} belongs to "
                . "school #{$academicYear->school_id}, not the requested school #{$schoolId}."
            );
        }

        $standard = Standard::findOrFail($standardId);

        if ($standard->school_id !== $schoolId) {
            throw new \InvalidArgumentException(
                "PromotionRuleRepository::setRule(): standard #{$standardId} belongs to school "
                . "#{$standard->school_id}, not the requested school #{$schoolId}."
            );
        }

        return PromotionRule::updateOrCreate(
            [
                'school_id' => $schoolId,
                'academic_year_id' => $academicYearId,
                'standard_id' => $standardId,
            ],
            ['criteria' => $criteria]
        );
    }

    private function assertActorMayAuthor(User $actor): void
    {
        $roleKey = $actor->role->key ?? null;

        if ($roleKey !== 'school_admin') {
            throw new \InvalidArgumentException(
                "PromotionRuleRepository: role '{$roleKey}' may not author promotion rules."
            );
        }
    }

    private function assertValidCriteria(array $criteria): void
    {
        if (! array_key_exists('min_aggregate', $criteria) || ! is_numeric($criteria['min_aggregate'])) {
            throw new \InvalidArgumentException(
                "PromotionRuleRepository: criteria['min_aggregate'] is required and must be numeric."
            );
        }

        $minAggregate = (float) $criteria['min_aggregate'];

        if ($minAggregate < 0 || $minAggregate > 100) {
            throw new \InvalidArgumentException(
                "PromotionRuleRepository: criteria['min_aggregate'] must be between 0 and 100, got "
                . "{$minAggregate}."
            );
        }
    }
}