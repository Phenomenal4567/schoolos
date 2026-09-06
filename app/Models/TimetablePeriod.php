<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Design ref: Timetable Management module — see the
 * 2026_09_04_000001_create_timetable_periods_table migration's doc
 * comment.
 *
 * One row per (school_id, period_number) — the school-wide "period 1 is
 * 8:00-8:40" grid every timetable_slots row's period_number is
 * displayed against. Written exclusively by
 * TimetableRepository::replaceSettings(), the same "one place decides
 * this" discipline ExamComponentRepository::replaceForSchool()
 * establishes for exam_components.
 */
class TimetablePeriod extends Model
{
    protected $fillable = [
        'school_id',
        'period_number',
        'start_time',
        'end_time',
    ];

    protected function casts(): array
    {
        return [
            'period_number' => 'integer',
        ];
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }
}
