<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Design ref: 17-schoolos-academic-domain-map.md §2, §3, §5
 *
 * The class_section_id column is what lets
 * ScopeService::relationshipScope() cover reads of this model
 * generically (17 §3) — no ScopeService change was needed to scope
 * "which lesson plans can this teacher/parent/student see."
 *
 * status/reviewed_by/reviewed_at/review_note exist for
 * LessonPlanRepository::approve()/reject() — the direct fix for F30/F31
 * (the reference codebase's approve/reject endpoints had no tenant
 * scoping and no role check at all, despite the same controller's
 * index() correctly branching on hasRole('principal') two methods
 * away). reviewed_by is a plain FK to users, not a role-specific column
 * or table — 17 §4 settled that school_admin is the SchoolOS stand-in
 * for GegoK12's principal role, so the reviewer is just "a user," and
 * LessonPlanRepository::approve()/reject() are what enforce that the
 * actor's role is actually school_admin before writing.
 */
class LessonPlan extends Model
{
    use HasFactory;

    protected $fillable = [
        'school_id',
        'academic_year_id',
        'class_section_id',
        'subject_id',
        'teacher_id',
        'title',
        'content',
        'document_path',
        'status',
        'reviewed_by',
        'reviewed_at',
        'review_note',
    ];

    protected function casts(): array
    {
        return [
            'status' => 'string',
            'reviewed_at' => 'datetime',
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

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
