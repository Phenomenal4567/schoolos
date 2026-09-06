<?php

namespace App\Exceptions\Admission;

use App\Models\AdmissionApplication;

/**
 * Design ref: 19-discovery-hierarchy-gap-closure-plan.md §10.
 *
 * Thrown by AdmissionApplicationRepository::reject()/withdraw() when
 * $reason is empty. Mirrors
 * App\Exceptions\Promotion\MissingPromotionReasonFailure's shape exactly
 * (a separate, Admission-namespaced exception rather than reusing the
 * Promotion one — these two decision surfaces are unrelated domain
 * concepts that happen to share the same "a decision that can't say why
 * defeats its own audit purpose" reasoning, not a shared type): a reject
 * or withdrawal that can't say *why* isn't distinguishable from an
 * unexplained refusal, which defeats the purpose decision_reason exists
 * for on this table.
 */
class MissingDecisionReasonFailure extends \Exception
{
    public function __construct(public readonly AdmissionApplication $application)
    {
        parent::__construct(sprintf(
            'An admission_applications #%d reject/withdraw decision requires a non-empty decision_reason.',
            $application->id,
        ));
    }
}
