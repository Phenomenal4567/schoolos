<?php

namespace App\Repositories;

use App\Models\FeeCategory;

/**
 * Design ref: 22-schoolos-finance-schema.md §1
 *
 * The write path for fee_categories. Deliberately thin — same
 * observation AcademicYearRepository's own doc comment makes: no
 * cross-role invariant exists on this table (unlike D3's
 * class_teacher_id), so there is no gate to write here beyond the
 * schema's own UNIQUE(school_id, key) constraint.
 *
 * create() rejects an attempt to author a category under the
 * 'rolled_over_debt' key directly — that key is system-reserved for
 * FeeRolloverRepository (D12) and is not admin-creatable, even though
 * nothing else about this table is restricted.
 */
class FeeCategoryRepository
{
    public function create(int $schoolId, string $key, string $label): FeeCategory
    {
        if ($key === 'rolled_over_debt') {
            throw new \InvalidArgumentException(
                "FeeCategoryRepository::create(): 'rolled_over_debt' is a system-reserved fee "
                . 'category key and cannot be created directly.'
            );
        }

        return FeeCategory::create([
            'school_id' => $schoolId,
            'key' => $key,
            'label' => $label,
            'is_system_reserved' => false,
        ]);
    }
}
