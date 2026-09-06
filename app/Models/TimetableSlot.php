<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Design ref: 17-schoolos-academic-domain-map.md §2, §3
 *
 * The class_section_id column is what lets
 * ScopeService::relationshipScope() cover this model generically — a
 * teacher's assigned sections, a parent/student's own/linked children's
 * sections, or (default branch) a school_admin's whole school. No
 * ScopeService change accompanies this model, matching how
 * AttendanceRecord's own doc comment describes the same generic
 * coverage.
 *
 * Recurring weekly slot (day_of_week + period_number), not a
 * date-specific row — see the 2026_08_27_000001 migration's doc comment
 * for why.
 */
class TimetableSlot extends Model
{
    use HasFactory;

    protected $fillable = [
        'school_id',
        'academic_year_id',
        'class_section_id',
        'subject_id',
        'teacher_id',
        'day_of_week',
        'period_number',
        'room',
    ];

    protected function casts(): array
    {
        return [
            'day_of_week' => 'string',
            'period_number' => 'integer',
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

    public function classSection(): BelongsTo
    {
        return $this->belongsTo(ClassSection::class);
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }
}
