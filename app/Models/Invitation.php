<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Design ref: SchoolOS Account Creation & Onboarding plan, "New:
 * Invitation mechanism"
 *
 * One row per pending/accepted/revoked activation for an already-created
 * User row (teacher, staff, parent, student, or school_admin — role_id
 * is denormalized here purely for display in an admin "pending
 * invitations" list, never read for authorization; the real role always
 * comes from `user.role_id`). token_hash is the only thing persisted for
 * the token itself — see the migration's own doc comment. No factory: an
 * invitation is always a side effect of InvitationRepository::issue(),
 * never constructed standalone, the same reasoning AuditLog/
 * AttendanceCorrection give for omitting HasFactory.
 */
class Invitation extends Model
{
    protected $fillable = [
        'school_id',
        'user_id',
        'role_id',
        'token_hash',
        'status',
        'expires_at',
        'accepted_at',
        'invited_by',
    ];

    protected function casts(): array
    {
        return [
            'status' => 'string',
            'expires_at' => 'datetime',
            'accepted_at' => 'datetime',
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

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function invitedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by');
    }
}
