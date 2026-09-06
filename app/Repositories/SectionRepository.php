<?php

namespace App\Repositories;

use App\Models\Section;

/**
 * Design ref: 13-schoolos-database-schema-v2.md §3
 * Decision ref: 14-schoolos-implementation-plan.md §2
 *
 * The write path that creates a sections row. See
 * AcademicYearRepository's doc comment for why this is deliberately
 * thinner than ClassSectionRepository — there's no cross-role invariant
 * on a bare "A/B" section label for this gate to check.
 */
class SectionRepository
{
    /**
     * $schoolId is taken from the caller's own scoped context, never
     * from client input directly, per Ground Rule 0.
     */
    public function create(int $schoolId, string $name): Section
    {
        return Section::create([
            'school_id' => $schoolId,
            'name' => $name,
        ]);
    }
}
