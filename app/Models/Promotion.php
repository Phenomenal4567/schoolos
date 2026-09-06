<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Design ref: 19-discovery-hierarchy-gap-closure-plan.md §8
 * Decision ref: 16-schoolos-decisions-register.md D15
 *
 * Carries `student_id` denormalized from `student_enrollment_id` —
 * mirrors `ExamMark`/`AssignmentSubmission`'s shape exactly, for the
 * same reason: this lets `ScopeService`'s existing generic `student_id`
 * dispatch cover the parent/student read side for free, no new
 * `ScopeService` branch needed for this resource. See the
 * 2026_09_02_000001 migration's doc comment for the full reasoning —
 * not repeated here to avoid the two doc comments drifting out of sync.
 *
 * No teacher access to this resource at all (unlike `Exam`/`ExamMark`,
 * which do have a teacher side) — discovery §9 frames promotion as an
 * admin/school-level decision, not something a subject teacher
 * authors or reviews, so `ScopeService::relationshipScope()`'s teacher
 * branch is never invoked for this model and needs no new join.
 */
class Promotion extends Model
{
    use HasFactory;

    protected $fillable = [
        'school_id',
        'student_enrollment_id',
        'student_id',
        'from_class_section_id',
        'to_class_section_id',
        'method',
        'decided_by',
        'reason',
        'decided_at',
    ];

    protected function casts(): array
    {
        return [
            'decided_at' => 'datetime',
        ];
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function studentEnrollment(): BelongsTo
    {
        return $this->belongsTo(StudentEnrollment::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function fromClassSection(): BelongsTo
    {
        return $this->belongsTo(ClassSection::class, 'from_class_section_id');
    }

    public function toClassSection(): BelongsTo
    {
        return $this->belongsTo(ClassSection::class, 'to_class_section_id');
    }

    public function decidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }
}