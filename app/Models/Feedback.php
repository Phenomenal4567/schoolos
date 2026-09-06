<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Design ref: 18-schoolos-communication-domain-map.md §3, §6, §8
 *
 * Parent/student-initiated message tied to a specific student — see this
 * table's migration doc comment for why this is a fourth entity, not an
 * Announcement variant. `student_id` is the generic scoping column
 * ScopeService::relationshipScope() already dispatches on for
 * parent/student reads (18 §4); it carries no other new scoping shape.
 *
 * Not in $fillable: nothing writes a Feedback row outside
 * FeedbackRepository::create() (the single write path, matching this
 * bundle's "one repository method owns a resource's creation" shape) —
 * listed anyway for consistency with every other model in this bundle.
 */
class Feedback extends Model
{
    use HasFactory;

    protected $fillable = [
        'school_id',
        'student_id',
        'author_id',
        'category',
        'message',
        'recipient_type',
        'recipient_teacher_id',
    ];

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function recipientTeacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recipient_teacher_id');
    }
}
