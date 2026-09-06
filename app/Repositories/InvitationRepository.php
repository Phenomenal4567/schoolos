<?php

namespace App\Repositories;

use App\Exceptions\Invitation\ExpiredInvitationFailure;
use App\Exceptions\Invitation\InvalidInvitationTokenFailure;
use App\Models\AuditLog;
use App\Models\Invitation;
use App\Models\User;
use App\Notifications\InvitationNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Design ref: SchoolOS Account Creation & Onboarding plan, "New:
 * Invitation mechanism"
 *
 * The one write path for invitations — issue()/accept()/revoke() are the
 * only places anything ever writes an invitations row or flips a User's
 * status between 'invited' and 'active'. Every admin-facing "create a
 * teacher/staff/parent/school-admin" controller that wants to invite
 * rather than set a password directly calls issue() against a User row
 * it already created (with an unusable random password — the same
 * pattern AdmissionApplicationRepository::accept() already uses for a
 * freshly admitted student), never the other way around: this repository
 * never creates the User itself, so there is exactly one place per
 * caller that decides role/school/profile, and this repository's only
 * job is the activation lifecycle on top of it.
 */
class InvitationRepository
{
    private const DEFAULT_TTL_DAYS = 7;

    /**
     * @return array{invitation: Invitation, plainToken: string}
     */
    public function issue(User $forUser, User $actor, int $ttlDays = self::DEFAULT_TTL_DAYS): array
    {
        $plainToken = Str::random(64);

        $invitation = DB::transaction(function () use ($forUser, $actor, $ttlDays, $plainToken) {
            $forUser->status = 'invited';
            $forUser->save();

            $invitation = Invitation::create([
                'school_id' => $forUser->school_id,
                'user_id' => $forUser->id,
                'role_id' => $forUser->role_id,
                'token_hash' => hash('sha256', $plainToken),
                'status' => 'pending',
                'expires_at' => now()->addDays($ttlDays),
                'invited_by' => $actor->id,
            ]);

            AuditLog::create([
                'school_id' => $forUser->school_id,
                'actor_id' => $actor->id,
                'action' => 'invitation.issued',
                'entity_type' => 'Invitation',
                'entity_id' => $invitation->id,
                'before_state' => null,
                'after_state' => ['user_id' => $forUser->id, 'role_id' => $forUser->role_id],
            ]);

            return $invitation;
        });

        $forUser->notify(new InvitationNotification($invitation, $plainToken));

        return ['invitation' => $invitation, 'plainToken' => $plainToken];
    }

    /**
     * @throws InvalidInvitationTokenFailure if $plainToken matches no
     *         'pending' row (wrong token, already accepted, or revoked —
     *         see that exception's own doc comment for why these three
     *         cases share one message).
     * @throws ExpiredInvitationFailure if the matched row's expires_at
     *         has passed.
     */
    public function accept(string $plainToken, string $password): User
    {
        $tokenHash = hash('sha256', $plainToken);

        return DB::transaction(function () use ($tokenHash, $password) {
            // Locks the row for the duration of the transaction — the
            // same TOCTOU-safe pattern AttendanceRepository::mark() uses,
            // so two near-simultaneous accept() calls for the same token
            // can't both pass the 'pending' check before either commits.
            $invitation = Invitation::where('token_hash', $tokenHash)
                ->lockForUpdate()
                ->first();

            if ($invitation === null || $invitation->status !== 'pending') {
                throw new InvalidInvitationTokenFailure();
            }

            if ($invitation->expires_at->isPast()) {
                throw new ExpiredInvitationFailure();
            }

            $user = User::findOrFail($invitation->user_id);
            $user->password = $password;
            $user->status = 'active';
            $user->save();

            $invitation->status = 'accepted';
            $invitation->accepted_at = now();
            $invitation->save();

            AuditLog::create([
                'school_id' => $invitation->school_id,
                'actor_id' => $user->id,
                'action' => 'invitation.accepted',
                'entity_type' => 'Invitation',
                'entity_id' => $invitation->id,
                'before_state' => null,
                'after_state' => ['user_id' => $user->id],
            ]);

            return $user;
        });
    }

    public function revoke(Invitation $invitation, User $actor): void
    {
        if ($invitation->status !== 'pending') {
            return;
        }

        $invitation->status = 'revoked';
        $invitation->save();

        AuditLog::create([
            'school_id' => $invitation->school_id,
            'actor_id' => $actor->id,
            'action' => 'invitation.revoked',
            'entity_type' => 'Invitation',
            'entity_id' => $invitation->id,
            'before_state' => ['status' => 'pending'],
            'after_state' => ['status' => 'revoked'],
        ]);
    }
}
