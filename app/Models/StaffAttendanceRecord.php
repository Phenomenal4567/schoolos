<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Design ref: 13-schoolos-database-schema-v2.md §7b
 * Decision refs: 16-schoolos-decisions-register.md D12, D13
 * Execution ref: 20-phase-6-8-execution-prompt.md §4
 *
 * `user_id`, not `student_id` — this table is for any staff-type role
 * (teacher/school_admin/accountant/librarian/receptionist/staff), never
 * student-specific, matching this table's own migration comment. Because
 * it carries neither `student_id` nor `class_section_id`,
 * ScopeService::relationshipScope()'s existing dispatch has no branch
 * that resolves it correctly for a non-admin staff role — see
 * ScopeService::staffSelfScope()'s doc comment for the dedicated method
 * this model's visibility uses instead of relationshipScope().
 *
 * No image/photo column exists on this table, and none should ever be
 * added — see this table's migration for why that omission is
 * deliberate (D13).
 */
class StaffAttendanceRecord extends Model
{
    protected $fillable = [
        'school_id',
        'user_id',
        'date',
        'check_in',
        'check_out',
        'method',
        'verification_result',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'check_in' => 'datetime',
            'check_out' => 'datetime',
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
