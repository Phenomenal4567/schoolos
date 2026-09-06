<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Design ref: 26-discovery-hierarchy-status.md ("Subject Attendance" gap)
 *
 * "Topic taught" for one timetable_slot on one date — a session-level
 * fact, not a per-student one, so it lives in its own row rather than
 * being duplicated across every SubjectAttendanceRecord for that slot/
 * date. See the 2026_09_05_000003 migration's doc comment for why this
 * is a separate table instead of a column on subject_attendance_records.
 *
 * Written only via SubjectAttendanceRepository::recordTopic(), which
 * upserts against the (timetable_slot_id, date) unique constraint.
 */
class SubjectAttendanceTopic extends Model
{
    use HasFactory;

    protected $fillable = [
        'school_id',
        'class_section_id',
        'subject_id',
        'timetable_slot_id',
        'date',
        'topic',
        'recorded_by',
        'recorded_at',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date:Y-m-d',
            'recorded_at' => 'datetime',
        ];
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function classSection(): BelongsTo
    {
        return $this->belongsTo(ClassSection::class);
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function timetableSlot(): BelongsTo
    {
        return $this->belongsTo(TimetableSlot::class);
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
