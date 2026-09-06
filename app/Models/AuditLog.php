<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Design ref: 13-schoolos-database-schema-v2.md §7a
 * Decision refs: 16-schoolos-decisions-register.md D2, D3
 *
 * Required in prose three separate times (original handoff §24, Master
 * Plan §31, and implicitly by F3/F18's remediation) and never once
 * schema'd until v2 (audit §11) — this is the concrete mechanism D2's
 * school-suspension transitions and D3's class_teacher_id writes both
 * depend on. Append-only: no `updated_at`, no soft-delete, no update()
 * calls anywhere in the codebase against an existing row — an audit log
 * entry is written once and never edited or removed.
 *
 * There is no `factory()`/HasFactory here on purpose: audit rows are a
 * side effect of a real write (ClassSectionRepository::
 * assignClassTeacher(), AuthenticationService::setSchoolStatus()), not a
 * standalone resource anything should construct directly outside those
 * write paths.
 */
class AuditLog extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'school_id',
        'actor_id',
        'action',
        'entity_type',
        'entity_id',
        'before_state',
        'after_state',
    ];

    protected function casts(): array
    {
        return [
            'before_state' => 'array',
            'after_state' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
