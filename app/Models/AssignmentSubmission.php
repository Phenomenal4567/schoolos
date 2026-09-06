<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Design ref: 17-schoolos-academic-domain-map.md §2, §3, §5
 *
 * Direct schema-level fix for F29 (any teacher, any school, could write
 * obtained_marks/comments onto any student's submission — zero scoping
 * on the entire reference-codebase controller).
 *
 * Deliberately carries student_id + assignment_id, not class_section_id
 * directly — a submission belongs to a student, not a section.
 * student_id lets ScopeService::parentRelationshipScope()/
 * studentRelationshipScope()'s existing generic dispatch cover the
 * parent/student side of this model for free. The teacher side needed a
 * new branch in ScopeService::teacherRelationshipScope() (this pass) —
 * a teacher's relationship to a submission is "is this row's parent
 * assignment_id on one of my assigned sections," which the existing
 * class_section_id-presence check can't express since this table has no
 * class_section_id column of its own. See that method's doc comment for
 * the branch this model (and ExamMark) required.
 */
class AssignmentSubmission extends Model
{
    use HasFactory;

    protected $fillable = [
        'school_id',
        'assignment_id',
        'student_id',
        'submitted_at',
        'obtained_marks',
        'comments',
        'graded_by',
        'graded_at',
    ];

    protected function casts(): array
    {
        return [
            'submitted_at' => 'datetime',
            'obtained_marks' => 'decimal:2',
            'graded_at' => 'datetime',
        ];
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function assignment(): BelongsTo
    {
        return $this->belongsTo(Assignment::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function gradedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'graded_by');
    }
}
