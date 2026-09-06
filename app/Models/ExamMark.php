<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Design ref: 17-schoolos-academic-domain-map.md §2, §3
 *
 * Mirrors AssignmentSubmission's shape exactly, for the same reason:
 * carries exam_id + student_id, not class_section_id directly. student_id
 * lets ScopeService's existing generic dispatch cover the parent/student
 * side for free; exam_id is what the new teacherRelationshipScope()
 * branch (this pass) joins through to exams.class_section_id for the
 * teacher side. See AssignmentSubmission's doc comment and
 * ScopeService::teacherRelationshipScope()'s for the full reasoning —
 * not repeated here to avoid the two doc comments drifting out of sync
 * with each other.
 */
class ExamMark extends Model
{
    use HasFactory;

    public const STATUS_DRAFT = 'draft';
    public const STATUS_REVIEWED = 'reviewed';
    public const STATUS_PUBLISHED = 'published';

    protected $fillable = [
        'school_id',
        'exam_id',
        'exam_component_id',
        'student_id',
        'marks_obtained',
        'max_marks',
        'status',
        'recorded_by',
        'recorded_at',
    ];

    protected function casts(): array
    {
        return [
            'marks_obtained' => 'decimal:2',
            'max_marks' => 'decimal:2',
            'status' => 'string',
            'recorded_at' => 'datetime',
        ];
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function exam(): BelongsTo
    {
        return $this->belongsTo(Exam::class);
    }

    public function component(): BelongsTo
    {
        return $this->belongsTo(ExamComponent::class, 'exam_component_id');
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
