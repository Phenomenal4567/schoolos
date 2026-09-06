<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Design ref: Timetable Management module — see the
 * 2026_09_04_000004_create_timetable_publications_table migration's doc
 * comment for why publish state is a header row per (school_id,
 * academic_year_id) rather than a column on every timetable_slots row.
 *
 * Written exclusively by TimetableRepository::publish()/unpublish().
 */
class TimetablePublication extends Model
{
    protected $fillable = [
        'school_id',
        'academic_year_id',
        'is_published',
        'published_at',
        'published_by',
    ];

    protected function casts(): array
    {
        return [
            'is_published' => 'boolean',
            'published_at' => 'datetime',
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

    public function publishedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'published_by');
    }
}
