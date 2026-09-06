<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Design ref: 26-discovery-hierarchy-status.md ("Subject Attendance" gap)
 *
 * The subject-level analogue of AttendanceRecord — see that model's doc
 * comment and the 2026_09_05_000001 migration's doc comment for how this
 * table relates to attendance_records. class_section_id and student_id
 * are what let ScopeService::relationshipScope() cover this model
 * generically, identically to AttendanceRecord.
 *
 * Uniqueness is per (student_id, date, timetable_slot_id) at the schema
 * level, the direct analogue of AttendanceRecord's own
 * (student_id, date, session) constraint.
 */
class SubjectAttendanceRecord extends Model
{
    use HasFactory;

    protected $fillable = [
        'school_id',
        'academic_year_id',
        'class_section_id',
        'subject_id',
        'timetable_slot_id',
        'student_id',
        'recorded_by',
        'date',
        'status',
        'note',
        'recorded_at',
    ];

    protected function casts(): array
    {
        return [
            // Explicit Y-m-d, not the bare 'date' cast — see
            // AttendanceRecord::casts()'s doc comment for the exact
            // SQLite/query-input mismatch this avoids; the same
            // correction-lookup shape (where('date', $date) with a plain
            // "Y-m-d" string) exists in SubjectAttendanceRepository::mark().
            'date' => 'date:Y-m-d',
            'status' => 'string',
            'recorded_at' => 'datetime',
        ];
    }

    public function corrections(): HasMany
    {
        return $this->hasMany(SubjectAttendanceCorrection::class);
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

    public function timetableSlot(): BelongsTo
    {
        return $this->belongsTo(TimetableSlot::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
