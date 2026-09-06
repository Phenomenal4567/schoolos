<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Design ref: 13-schoolos-database-schema-v2.md §3
 * Decision ref: 12-schoolos-architecture.md §3a, §8
 *
 * The table F20's fix (and every F20-shaped finding) traces back to.
 * ScopeService::relationshipScope()'s parent/student branches MUST
 * intersect the target student against this table before returning
 * anything — never school_id alone (that was F20's exact bug).
 *
 * Kept close to GegoK12's shape almost as-is per 12 §8 — this was
 * explicitly called out as already-correct domain modeling. The one
 * change: `school_id` is NOT NULL here (05-database-map.md §1's
 * nullable-FK gap, closed). GegoK12's second, competing mechanism
 * (`users.ref_id`, consumed asymmetrically by `mother()`/`father()`,
 * flagged as vestigial in 08-parent-domain-map.md §3) has no equivalent
 * column anywhere in this schema — there is exactly one parent-child
 * relationship table, and this is it.
 */
class StudentParentLink extends Model
{
    use HasFactory;

    protected $fillable = [
        'school_id',
        'parent_id',
        'student_id',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'status' => 'string',
        ];
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'parent_id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }
}
