<?php

namespace App\Exceptions\Promotion;

use App\Models\StudentEnrollment;

/**
 * Design ref: 19-discovery-hierarchy-gap-closure-plan.md §8 (discovery
 * §9.2's "exceptional cases" framing)
 *
 * Thrown by PromotionRepository::override() when $reason is empty —
 * discovery §9.2 requires human override to remain available "for
 * special circumstances," which this bundle reads as: a manual
 * override that can't say *why* isn't distinguishable from an
 * unexplained deviation from the school's own rule, defeating the
 * audit purpose the reason column exists for. Mirrors
 * InvalidFeeAssessmentTargetFailure's shape (App\Exceptions\Fee): one
 * typed exception, thrown before any row is written, not caught and
 * silently defaulted.
 */
class MissingPromotionReasonFailure extends \Exception
{
    public function __construct(public readonly StudentEnrollment $enrollment)
    {
        parent::__construct(sprintf(
            'A manual promotion override for student_enrollment #%d requires a non-empty reason.',
            $enrollment->id,
        ));
    }
}