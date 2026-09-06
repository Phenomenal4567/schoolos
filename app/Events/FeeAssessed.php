<?php

namespace App\Events;

use App\Models\FeeAssessment;

/**
 * Design ref: 26-discovery-hierarchy-status.md ("Fee / Debt Notifications").
 *
 * Fired by FeeAssessmentRepository::assess() after the write transaction
 * commits, the same decoupling shape AttendanceMarked already
 * established: this event carries the fact, a listener decides what to
 * do with it. Only fired when the new assessment's amount_due is
 * positive — a scholarship/discount that fully covers the fee leaves
 * nothing for a parent to be alerted about.
 */
class FeeAssessed
{
    public function __construct(
        public readonly FeeAssessment $assessment,
    ) {
    }
}
