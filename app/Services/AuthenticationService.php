<?php

namespace App\Services;

use App\Exceptions\Auth\AccountDisabledFailure;
use App\Exceptions\Auth\AccountNotActivatedFailure;
use App\Exceptions\Auth\AuthFailure;
use App\Exceptions\Auth\SchoolSuspendedFailure;
use App\Exceptions\Auth\TrialExpiredFailure;
use App\Exceptions\Auth\UserNotFoundFailure;
use App\Models\AuditLog;
use App\Models\School;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

/**
 * Design ref: 12-schoolos-architecture.md §2
 * Decision refs: 16-schoolos-decisions-register.md D2, D5
 *
 * One service, called identically by web and API controllers — the direct
 * fix for F5/F6 (four validators that disagreed on which field to check,
 * a registration-number path that crashed) and F4 (client-supplied
 * `usergroup` accepted as authentication input).
 *
 * Ground rule (14 §0): web and mobile/API both call authenticate() below.
 * There is no parallel per-role login controller anywhere in SchoolOS.
 */
class AuthenticationService
{
    /**
     * Resolve a raw login input to exactly one identifier type.
     *
     * F5/F6's root cause was hardcoding *which* field a given login flow
     * checked. This is the only place that decision gets made — every
     * downstream check (below) uses the resolved identifier, never a
     * hardcoded field name.
     *
     * Resolution here is purely shape-based (does the input *look like* an
     * email / a phone number / neither) — it does not touch the database.
     * authenticate() is what actually looks the value up against the
     * resolved column. Keeping resolution and lookup as two separate steps
     * is what makes "try email, then mobile_no, then registration_number,
     * in that fixed order" an enforceable rule rather than an emergent
     * side effect of whatever `orWhere` chain a validator happens to use
     * (which is exactly how F6 let a `name` match slip in).
     *
     * The exact email/phone shape rules below aren't specified anywhere in
     * `12`/`13` — this is a judgment call, not a value pulled from a doc.
     * `FILTER_VALIDATE_EMAIL` for the email check is standard and
     * unambiguous. The mobile_no pattern (digits, optionally with a
     * leading `+` and interior spaces/hyphens, 7–15 digits) is deliberately
     * permissive rather than country-specific, since `13` §2 doesn't
     * constrain `mobile_no`'s format beyond "unique, nullable". Anything
     * that matches neither shape falls through to registration_number,
     * which `13` §2 also leaves free-form — this is why registration_number
     * is checked last rather than matched against its own pattern: it's
     * the catch-all for "doesn't look like the other two," not a shape of
     * its own.
     *
     * @return array{type: 'email'|'mobile_no'|'registration_number', value: string}
     *
     * @throws UserNotFoundFailure if the input matches none of the three
     *         unique identifier columns (13-schoolos-database-schema-v2.md
     *         §2 — email/mobile_no/registration_number are all UNIQUE,
     *         which is what makes this resolution safe; `name` is
     *         deliberately not a lookup field at all, closing F6's
     *         entire failure class at the schema level).
     */
    public function resolveIdentifier(string $input): array
    {
        $input = trim($input);

        if ($input === '') {
            // Empty input can't shape-match anything below; fail the same
            // way an unrecognized shape would (typed, not a downstream
            // null-dereference — F5's fix applies just as much here).
            throw new UserNotFoundFailure();
        }

        if (filter_var($input, FILTER_VALIDATE_EMAIL) !== false) {
            return ['type' => 'email', 'value' => $input];
        }

        // Permissive phone shape: optional leading +, 7-15 digits, with
        // spaces/hyphens allowed between digits. Deliberately not
        // country-specific — see doc comment above.
        if (preg_match('/^\+?[0-9](?:[0-9\s\-]{5,20})[0-9]$/', $input) === 1) {
            return ['type' => 'mobile_no', 'value' => $input];
        }

        // Catch-all: anything that reached here didn't shape-match email
        // or mobile_no. registration_number is free-form per 13 §2, so
        // there's no further shape check to fail — the only remaining
        // question is whether a row actually exists for it, and that's
        // authenticate()'s job, not resolveIdentifier()'s. Never falls
        // back to a `name` match (F6) — `name` is not one of the three
        // branches here at all.
        return ['type' => 'registration_number', 'value' => $input];
    }

    /**
     * Authenticate a resolved identifier + credential pair.
     *
     * - Never accepts role/usergroup as input (direct fix for F4 — the
     *   OTPRequest pattern that took `usergroup` from the client).
     * - "User not found" is always a typed failure, never a null-dereference
     *   (direct fix for F5) or an uncaught \Error (direct fix for F16's
     *   \Error-not-\Exception catch gap).
     * - Checks D2's suspension behavior: a user whose school is suspended
     *   fails with SchoolSuspendedFailure, not a generic invalid-credentials
     *   message, and this check runs on every login attempt, not only at
     *   the moment of suspension.
     *
     * Suspension is checked before the credential is verified, on purpose:
     * D2 says a suspended-school user should be told that plainly, not
     * left to assume they mistyped a password — if password verification
     * ran first, a correct-password/suspended-school attempt would need
     * a second branch to still surface the suspension message, which is
     * exactly the kind of per-call-site duplication D2/F3 exist to avoid.
     *
     * The user's own `status` (SchoolOS Account Creation & Onboarding
     * plan, §12) is checked the same way, for the same reason, and was a
     * real, pre-existing gap this pass closes: this method previously
     * checked school-level suspension but never the user row's own
     * status at all, so an 'inactive'/'exited' account that still had (or
     * was told) a working password could log in anyway. 'invited' gets
     * its own distinct exception rather than falling through to
     * AuthFailure's generic wrong-credential message — see
     * AccountNotActivatedFailure's own doc comment for why that
     * distinction is worth making.
     *
     * @return User
     *
     * @throws UserNotFoundFailure
     * @throws SchoolSuspendedFailure
     * @throws TrialExpiredFailure
     * @throws AccountNotActivatedFailure
     * @throws AccountDisabledFailure
     * @throws AuthFailure for a bad credential against a resolved user
     */
    public function authenticate(array $identifier, string $credential): User
    {
        $user = User::where($identifier['type'], $identifier['value'])->first();

        if (! $user) {
            throw new UserNotFoundFailure();
        }

        // D2: this check runs on every authenticate() call, not only at
        // the moment a school transitions to suspended — a school that
        // was suspended yesterday must still block today's login attempt,
        // so there is no "only check on the way in" shortcut here.
        if ($user->school_id !== null && $user->school->status === 'suspended') {
            throw new SchoolSuspendedFailure($user->school_id);
        }

        // Same "check on every attempt, not just at the moment it
        // happened" reasoning as suspension, immediately above — a trial
        // that expired yesterday must still block today's login.
        if ($user->school_id !== null && $user->school->isTrialExpired()) {
            throw new TrialExpiredFailure();
        }

        if ($user->status === 'invited') {
            throw new AccountNotActivatedFailure();
        }

        if ($user->status !== 'active') {
            throw new AccountDisabledFailure();
        }

        if (! Hash::check($credential, $user->password)) {
            throw new AuthFailure();
        }

        return $user;
    }

    /**
     * Issue a web session or API token for an authenticated user.
     *
     * Web/API is distinguished by the current request context
     * (`json`/`api/*`), not by a parameter, so the caller — whether a web
     * or API controller — invokes this identically per 14 §0's ground
     * rule; issueSession() decides the transport, not the caller.
     *
     * @return string|null the plain-text API token for an API request,
     *         or null for a web request (the session itself is the
     *         result; there's nothing to hand back to the caller).
     */
    public function issueSession(User $user, bool $remember = false): ?string
    {
        $request = request();
        $isApiRequest = $request !== null && ($request->is('api/*') || $request->expectsJson());

        if ($isApiRequest) {
            // TODO(Phase 1): Sanctum is not confirmed installed yet (see
            // the TODO already left on app\Models\User.php). createToken()
            // only exists once `Laravel\Sanctum\HasApiTokens` is added to
            // User, which itself only exists once `composer require
            // laravel/sanctum` has run and its migration is applied. Fail
            // loudly here rather than silently returning null or a fake
            // token, so an API caller can't mistake "not wired up yet"
            // for "issued, but empty."
            if (! method_exists($user, 'createToken')) {
                throw new \RuntimeException(
                    'AuthenticationService::issueSession(): API session requested but '
                    . 'Laravel\\Sanctum\\HasApiTokens is not present on App\\Models\\User. '
                    . 'Install Sanctum (composer require laravel/sanctum), run its migration, '
                    . 'and add the HasApiTokens trait per the TODO already left in '
                    . 'app/Models/User.php before issuing API sessions.'
                );
            }

            /** @var \Laravel\Sanctum\NewAccessToken $token */
            $token = $user->createToken('api');

            return $token->plainTextToken;
        }

        // Web path: Auth::login() must run before regenerate() — it's what
        // actually attaches $user to the 'web' guard for this session;
        // without it, every route behind 'auth' middleware would reject a
        // just-authenticated user, since the guard would have no user to
        // find on the very next request. (Bug found and fixed while
        // building Phase 2's parent web dashboard: this method previously
        // called session()->regenerate() alone, which rotates the session
        // ID but establishes no authenticated identity at all.)
        //
        // regenerate() still runs after, on purpose, for the standard
        // session-fixation reason: rotate the ID once the new identity is
        // attached, so a pre-login session ID an attacker knew about can't
        // be reused post-login.
        Auth::login($user, $remember);
        session()->regenerate();

        return null;
    }

    /**
     * Post-login/post-activation landing page, by role. Not itself an
     * authorization decision — ScopeService inside each dashboard
     * controller still governs what the user can actually see once they
     * land there; this only picks where to send them.
     *
     * Design ref: SchoolOS Account Creation & Onboarding plan.
     * Moved here from SessionController (its original, sole caller) so
     * every place a user is freshly authenticated — normal login, and
     * now InvitationRepository::accept()'s "set password, then land in
     * the right portal" flow — routes identically without duplicating
     * this match statement. Falls through to '/' for any role with no
     * dashboard yet, same as before the move.
     *
     * 'accountant'/'librarian'/'receptionist'/'staff' → staff.dashboard
     * (§3's new generic Staff Portal) is this method's one new branch —
     * those four roles previously fell through to the default '/' case
     * with no dashboard of their own at all.
     */
    public function dashboardPathFor(User $user): string
    {
        return match ($user->role->key ?? null) {
            'parent' => route('parent.dashboard'),
            'teacher' => route('teacher.dashboard'),
            'student' => route('student.dashboard'),
            'school_admin' => route('admin.dashboard'),
            'super_admin' => route('super-admin.dashboard'),
            'accountant', 'librarian', 'receptionist', 'staff' => route('staff.dashboard'),
            default => '/',
        };
    }

    /**
     * D2 (resolved): transition a school between active/suspended.
     *
     * - Invalidates all existing sessions/tokens for that school's users
     *   immediately (not left to expire naturally).
     * - Does not delete or hide any data — suspension is read-preserving
     *   at the platform layer.
     * - Both directions (active -> suspended, suspended -> active) write
     *   an audit_logs row (13-schoolos-database-schema-v2.md §7a).
     *
     * @throws \InvalidArgumentException if $status is not 'active' or
     *         'suspended' — schools.status is a two-value enum (13 §1);
     *         a third value has no defined audit-log action and must not
     *         be written silently.
     */
    public function setSchoolStatus(int $schoolId, string $status, User $actor): void
    {
        $action = match ($status) {
            'suspended' => 'school.suspended',
            'active' => 'school.reactivated',
            default => throw new \InvalidArgumentException(
                "setSchoolStatus(): status must be 'active' or 'suspended', got '{$status}'."
            ),
        };

        DB::transaction(function () use ($schoolId, $status, $actor, $action) {
            // lockForUpdate: two concurrent status changes on the same
            // school must not both read the same "before" state and race
            // each other's audit_logs row past the actual final value —
            // same reasoning ClassSectionRepository::assignClassTeacher()
            // already applies to class_sections.
            $school = School::lockForUpdate()->findOrFail($schoolId);

            $beforeState = ['status' => $school->status];

            $school->status = $status;
            $school->save();

            $userIds = User::where('school_id', $schoolId)->pluck('id');

            if ($userIds->isNotEmpty()) {
                // Sessions: revoke by deleting the school's users' rows
                // from the sessions table directly, keyed by user_id.
                // TODO(Phase 1): this assumes the database session driver
                // (a `sessions` table with a `user_id` column) — confirm
                // that's what SchoolOS is configured to use. If the
                // file/redis/cookie driver is used instead, this needs a
                // different revocation mechanism (e.g. a per-user session
                // version/nonce checked on every request), since those
                // drivers have no queryable "all sessions for user X" view.
                if (Schema::hasTable('sessions') && Schema::hasColumn('sessions', 'user_id')) {
                    DB::table('sessions')->whereIn('user_id', $userIds)->delete();
                }

                // Tokens: revoke Sanctum personal access tokens the same
                // way, once Sanctum is actually installed (see
                // issueSession()'s TODO — same unconfirmed-infra caveat
                // applies here). Guarded rather than assumed, so
                // setSchoolStatus() doesn't hard-fail on a school with no
                // API tokens issued yet just because Sanctum's table
                // doesn't exist.
                if (Schema::hasTable('personal_access_tokens')) {
                    DB::table('personal_access_tokens')
                        ->where('tokenable_type', User::class)
                        ->whereIn('tokenable_id', $userIds)
                        ->delete();
                }
            }

            // Both directions log (D2) — this line runs for both
            // 'school.suspended' and 'school.reactivated', there is no
            // branch that skips it for one direction.
            AuditLog::create([
                'school_id' => $schoolId,
                'actor_id' => $actor->id,
                'action' => $action,
                'entity_type' => 'School',
                'entity_id' => $schoolId,
                'before_state' => $beforeState,
                'after_state' => ['status' => $status],
            ]);
        });
    }
}
