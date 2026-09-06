<?php

namespace App\Repositories;

use App\Models\Standard;

/**
 * Design ref: 13-schoolos-database-schema-v2.md §3
 * Decision ref: 14-schoolos-implementation-plan.md §2
 *
 * The write path that creates a standards row. See
 * AcademicYearRepository's doc comment for why this is deliberately
 * thinner than ClassSectionRepository — there's no cross-role invariant
 * on a bare "grade/level" row for this gate to check.
 */
class StandardRepository
{
    /**
     * $schoolId is taken from the caller's own scoped context, never
     * from client input directly, per Ground Rule 0.
     */
    public function create(int $schoolId, string $name): Standard
    {
        return Standard::create([
            'school_id' => $schoolId,
            'name' => $name,
        ]);
    }
}
