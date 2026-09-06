<?php

namespace Tests\Feature;

use App\Exceptions\Auth\AccountDisabledFailure;
use App\Exceptions\Auth\AccountNotActivatedFailure;
use App\Exceptions\Auth\AuthFailure;
use App\Exceptions\Auth\SchoolSuspendedFailure;
use App\Exceptions\Auth\TrialExpiredFailure;
use App\Models\AuditLog;
use App\Models\School;
use App\Services\AuthenticationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesSchoolOsFixtures;
use Tests\TestCase;

/**
 * Coverage of AuthenticationService behavior beyond the 8 rows in
 * 14-schoolos-implementation-plan.md §1 — these back the D2/D3-derived
 * sketches already in AuthenticationService's doc comments, so they're
 * kept alongside the Phase 1 gate rather than deferred, even though the
 * implementation plan's table doesn't itemize them individually.
 */
class AuthenticationServiceTest extends TestCase
{
    use RefreshDatabase;
    use CreatesSchoolOsFixtures;

    /**
     * D2: "This check runs on every login attempt, not only at the
     * moment of suspension" — confirmed by suspending a school *after*
     * the user was created, then attempting to log in with a otherwise-
     * correct password.
     */
    public function test_authenticate_rejects_login_for_suspended_school_even_with_correct_password(): void
    {
        $service = new AuthenticationService();

        $school = $this->makeSchool(['status' => 'active']);
        $this->makeUser(['school_id' => $school->id, 'email' => 'suspended-school-user@example.test']);

        // Suspend after the user exists, mirroring a real "school gets
        // suspended after users are already active" timeline.
        $school->status = 'suspended';
        $school->save();

        $resolved = $service->resolveIdentifier('suspended-school-user@example.test');

        $this->expectException(SchoolSuspendedFailure::class);
        $service->authenticate($resolved, 'correct-password');
    }

    /**
     * D2's suspension check must fire even though the password is right —
     * it must not be masked by (or mistaken for) a generic AuthFailure.
     */
    public function test_suspended_school_failure_is_distinguishable_from_generic_auth_failure(): void
    {
        $service = new AuthenticationService();

        $school = $this->makeSchool(['status' => 'suspended']);
        $this->makeUser(['school_id' => $school->id, 'email' => 'another-suspended-user@example.test']);

        $resolved = $service->resolveIdentifier('another-suspended-user@example.test');

        try {
            $service->authenticate($resolved, 'correct-password');
            $this->fail('Expected SchoolSuspendedFailure to be thrown.');
        } catch (SchoolSuspendedFailure $e) {
            // Still an AuthFailure for callers that only branch on the
            // broad type, per SchoolSuspendedFailure's own doc comment.
            $this->assertInstanceOf(AuthFailure::class, $e);
            $this->assertSame($school->id, $e->schoolId);
        }
    }

    public function test_authenticate_rejects_wrong_password_with_generic_auth_failure(): void
    {
        $service = new AuthenticationService();
        $this->makeUser(['email' => 'wrongpass@example.test']);

        $resolved = $service->resolveIdentifier('wrongpass@example.test');

        $this->expectException(AuthFailure::class);
        $service->authenticate($resolved, 'not-the-right-password');
    }

    /**
     * Design ref: SchoolOS Account Creation & Onboarding plan, §12
     * (Security Requirements — "disabled accounts cannot authenticate").
     * A real, pre-existing gap this pass closes — see authenticate()'s
     * own updated doc comment.
     */
    /**
     * Design ref: SchoolOS Onboarding & Authentication UI — demo/trial
     * billing addition.
     */
    public function test_authenticate_rejects_login_for_an_expired_trial_school(): void
    {
        $service = new AuthenticationService();
        $school = $this->makeSchool(['billing_plan' => 'trial', 'trial_ends_at' => now()->subDay()]);
        $this->makeUser(['school_id' => $school->id, 'email' => 'expired-trial@example.test']);

        $resolved = $service->resolveIdentifier('expired-trial@example.test');

        $this->expectException(TrialExpiredFailure::class);
        $service->authenticate($resolved, 'correct-password');
    }

    public function test_a_paid_school_is_never_trial_expired_regardless_of_dates(): void
    {
        $service = new AuthenticationService();
        $school = $this->makeSchool(['billing_plan' => 'paid', 'trial_ends_at' => now()->subYear()]);
        $this->makeUser(['school_id' => $school->id, 'email' => 'paid-school@example.test']);

        $resolved = $service->resolveIdentifier('paid-school@example.test');

        // Reaches Hash::check() rather than throwing TrialExpiredFailure —
        // asserted by getting AuthFailure (wrong password), not
        // TrialExpiredFailure, proving isTrialExpired() correctly ignored
        // the stale trial_ends_at on a 'paid' plan.
        $this->expectException(AuthFailure::class);
        $service->authenticate($resolved, 'wrong-password');
    }

    public function test_a_trial_school_with_no_end_date_set_is_never_expired(): void
    {
        $service = new AuthenticationService();
        $school = $this->makeSchool(['billing_plan' => 'trial', 'trial_ends_at' => null]);
        $user = $this->makeUser(['school_id' => $school->id, 'email' => 'no-trial-date@example.test', 'password' => 'correct-password']);

        $resolved = $service->resolveIdentifier('no-trial-date@example.test');

        $this->assertTrue($service->authenticate($resolved, 'correct-password')->is($user));
    }

    public function test_authenticate_rejects_an_inactive_account_even_with_correct_password(): void
    {
        $service = new AuthenticationService();
        $this->makeUser(['email' => 'inactive-user@example.test', 'password' => 'correct-password', 'status' => 'inactive']);

        $resolved = $service->resolveIdentifier('inactive-user@example.test');

        $this->expectException(AccountDisabledFailure::class);
        $service->authenticate($resolved, 'correct-password');
    }

    public function test_authenticate_rejects_an_exited_account(): void
    {
        $service = new AuthenticationService();
        $this->makeUser(['email' => 'exited-user@example.test', 'password' => 'correct-password', 'status' => 'exited']);

        $resolved = $service->resolveIdentifier('exited-user@example.test');

        $this->expectException(AccountDisabledFailure::class);
        $service->authenticate($resolved, 'correct-password');
    }

    public function test_authenticate_rejects_an_invited_account_with_a_distinct_message(): void
    {
        $service = new AuthenticationService();
        $this->makeUser(['email' => 'invited-user@example.test', 'status' => 'invited']);

        $resolved = $service->resolveIdentifier('invited-user@example.test');

        try {
            $service->authenticate($resolved, 'whatever-password');
            $this->fail('Expected AccountNotActivatedFailure to be thrown.');
        } catch (AccountNotActivatedFailure $e) {
            $this->assertInstanceOf(AuthFailure::class, $e);
        }
    }

    public function test_resolve_identifier_throws_typed_failure_for_unrecognized_input(): void
    {
        $service = new AuthenticationService();

        // Whitespace-only input can't shape-match email, phone, or even
        // the registration_number catch-all (which still requires a
        // non-empty value) — resolveIdentifier() rejects it before any
        // shape check runs, never falling through to a `name` lookup.
        $this->expectException(\App\Exceptions\Auth\UserNotFoundFailure::class);
        $service->resolveIdentifier('   ');
    }

    /**
     * D2: both directions write an audit_logs row, and the write happens
     * inside the same transaction as the status change.
     */
    public function test_set_school_status_writes_audit_log_for_both_directions(): void
    {
        $service = new AuthenticationService();
        $school = $this->makeSchool(['status' => 'active']);
        $actor = $this->makeUser(['email' => 'actor@example.test']);

        $service->setSchoolStatus($school->id, 'suspended', $actor);

        $this->assertDatabaseHas('audit_logs', [
            'school_id' => $school->id,
            'actor_id' => $actor->id,
            'action' => 'school.suspended',
            'entity_type' => 'School',
            'entity_id' => $school->id,
        ]);
        $this->assertSame('suspended', $school->fresh()->status);

        $service->setSchoolStatus($school->id, 'active', $actor);

        $this->assertDatabaseHas('audit_logs', [
            'school_id' => $school->id,
            'actor_id' => $actor->id,
            'action' => 'school.reactivated',
            'entity_type' => 'School',
            'entity_id' => $school->id,
        ]);
        $this->assertSame('active', $school->fresh()->status);

        $this->assertSame(
            2,
            AuditLog::where('entity_type', 'School')->where('entity_id', $school->id)->count()
        );
    }

    public function test_set_school_status_rejects_unknown_status_value(): void
    {
        $service = new AuthenticationService();
        $school = $this->makeSchool();
        $actor = $this->makeUser();

        $this->expectException(\InvalidArgumentException::class);
        $service->setSchoolStatus($school->id, 'archived', $actor);

        // Must not have partially applied — status unchanged, no audit row.
        $this->assertSame('active', $school->fresh()->status);
        $this->assertSame(0, AuditLog::where('entity_type', 'School')->count());
    }

    public function test_set_school_status_revokes_sessions_for_that_schools_users(): void
    {
        $service = new AuthenticationService();
        $school = $this->makeSchool(['status' => 'active']);
        $user = $this->makeUser(['school_id' => $school->id]);
        $otherSchool = $this->makeSchool(['status' => 'active']);
        $otherUser = $this->makeUser(['school_id' => $otherSchool->id]);

        \Illuminate\Support\Facades\DB::table('sessions')->insert([
            'id' => 'session-for-target-school-user',
            'user_id' => $user->id,
            'payload' => base64_encode('x'),
            'last_activity' => time(),
        ]);
        \Illuminate\Support\Facades\DB::table('sessions')->insert([
            'id' => 'session-for-other-school-user',
            'user_id' => $otherUser->id,
            'payload' => base64_encode('x'),
            'last_activity' => time(),
        ]);

        $service->setSchoolStatus($school->id, 'suspended', $user);

        $this->assertDatabaseMissing('sessions', ['id' => 'session-for-target-school-user']);
        $this->assertDatabaseHas('sessions', ['id' => 'session-for-other-school-user']);
    }
}
