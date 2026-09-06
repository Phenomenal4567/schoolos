<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Design ref: 19-discovery-hierarchy-gap-closure-plan.md §8
 * Decision ref: 16-schoolos-decisions-register.md D15
 *
 * One rule per (school, academic_year, standard) — see the
 * 2026_09_02_000001 migration's doc comment for the UNIQUE constraint
 * this enforces and why `criteria` is JSON rather than a flat column.
 * Carries no `class_section_id` (a rule applies to every section under
 * a standard, not one section specifically) and so is not covered by
 * `ScopeService::relationshipScope()`'s generic dispatch — it doesn't
 * need to be: only `school_admin` ever reads or writes this table
 * (`PromotionRuleRepository`), and that role is tenant-scope-only by
 * default, the same as every other admin-authored resource in this
 * bundle (`CalendarEvent`, `FeeCategory`).
 */
class PromotionRule extends Model
{
    use HasFactory;

    protected $fillable = [
        'school_id',
        'academic_year_id',
        'standard_id',
        'criteria',
    ];

    protected function casts(): array
    {
        return [
            'criteria' => 'array',
        ];
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function standard(): BelongsTo
    {
        return $this->belongsTo(Standard::class);
    }
}