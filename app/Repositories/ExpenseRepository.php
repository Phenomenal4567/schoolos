<?php

namespace App\Repositories;

use App\Models\Expense;
use App\Models\User;

/**
 * Design ref: 22-schoolos-finance-schema.md §5, 21-schoolos-finance-architecture.md §7.
 *
 * Deliberately thin, same observation AcademicYearRepository's own doc
 * comment makes: expenses carries no discount/scholarship/rollover
 * machinery and no cross-role invariant on any of its columns (unlike
 * D3's class_teacher_id, or this track's own assertIsStudent() gates on
 * fee_assessments/scholarships) — there is nothing to check here beyond
 * the schema's own column constraints.
 */
class ExpenseRepository
{
    /**
     * $schoolId is taken from the caller's own scoped context, never
     * from client input directly, per Ground Rule 0.
     */
    public function create(
        int $schoolId,
        string $category,
        string $amount,
        ?string $description,
        string $incurredOn,
        User $recordedBy,
    ): Expense {
        return Expense::create([
            'school_id' => $schoolId,
            'category' => $category,
            'amount' => $amount,
            'description' => $description,
            'incurred_on' => $incurredOn,
            'recorded_by' => $recordedBy->id,
        ]);
    }
}
