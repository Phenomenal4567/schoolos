<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Design ref: 13-schoolos-database-schema-v2.md §6
 *
 * F15's fix (misleading `student_history` name in the reference
 * codebase) — this table describes exactly what it contains: one row per
 * (user, piece-of-content) marking that the user has viewed it.
 * `entity_type`/`entity_id` is a plain polymorphic pair, not Eloquent's
 * morph columns, matching the schema doc's literal column names.
 *
 * No write path calls this model yet in this pass — see the migration's
 * own doc comment. It exists so the follow-up pass that adds a
 * "mark as read" endpoint has the table and model already in place,
 * rather than starting from nothing.
 */
class ContentReadReceipt extends Model
{
    use HasFactory;

    // No created_at/updated_at columns on this table at all (13 §6's
    // schema is exactly id/school_id/user_id/entity_type/entity_id/
    // read_at) — read_at is the one timestamp this table needs.
    public $timestamps = false;

    protected $fillable = [
        'school_id',
        'user_id',
        'entity_type',
        'entity_id',
        'read_at',
    ];

    protected function casts(): array
    {
        return [
            'read_at' => 'datetime',
        ];
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
