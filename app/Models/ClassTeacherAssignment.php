<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Design ref: 13-schoolos-database-schema-v2.md §3
 * Decision ref: 16-schoolos-decisions-register.md D3 (traceability), 12 §3a
 *
 * GegoK12's `class_teacher_links` / `Teacherlink` model
 * (07-teacher-domain-map.md §2) — confirmed real, populated, and
 * correctly used for lesson plans/timetable, but never consulted by the
 * authorization Gates that needed it (F18). This is the table
 * ScopeService::relationshipScope()'s teacher branch MUST join against —
 * do not resolve teacher-side scoping any other way.
 */
class ClassTeacherAssignment extends Model
{
    use HasFactory;

    protected $fillable = [
        'school_id',
        'academic_year_id',
        'class_section_id',
        'subject_id',
        'teacher_id',
    ];

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
