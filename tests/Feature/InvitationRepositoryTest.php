<?php

namespace Tests\Feature;

use App\Exceptions\Invitation\ExpiredInvitationFailure;
use App\Exceptions\Invitation\InvalidInvitationTokenFailure;
use App\Models\Invitation;
use App\Repositories\InvitationRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\Concerns\CreatesSchoolOsFixtures;
use Tests\TestCase;

/**
 * Design ref: SchoolOS Account Creation & Onboarding plan, "New:
 * Invitation mechanism"
 *
 * Direct, non-HTTP coverage of InvitationRepository::issue()/accept()/
 * revoke() — the one write path every invite-capable admin controller
 * (Admin\StaffController, Admin\ParentEnrollmentController,
 * SuperAdmin\SchoolAdminController) and the public accept page funnel
 * through.
 */
class InvitationRepositoryTest extends TestCase
{
    use RefreshDatabase;
    use CreatesSchoolOsFixtures;

    public function test_issue_sets_the_user_to_invited_and_creates_a_pending_row(): void
    {
        Notification::fake();

        $school = $this->makeSchool();
        $admin = $this->makeUser(['role_id' => $this->makeRole('school_admin')->id, 'school_id' => $school->id]);
        $teacher = $this->makeUser([
            'role_id' => $this->makeRole('teacher')->id,
            'school_id' => $school->id,
            'status' => 'active',
        ]);

        $result = (new InvitationRepository())->issue($teacher, $admin);

        $this->assertSame('invited', $teacher->fresh()->status);
        $this->assertDatabaseHas('invitations', [
            'school_id' => $school->id,
            'user_id' => $teacher->id,
            'role_id' => $teacher->role_id,
            'status' => 'pending',
            'invited_by' => $admin->id,
        ]);
        $this->assertNotEmpty($result['plainToken']);
        $this->assertSame(64, strlen($result['plainToken']));

        $stored = Invitation::first();
        $this->assertSame(hash('sha256', $result['plainToken']), $stored->token_hash);

        $this->assertDatabaseHas('audit_logs', [
            'school_id' => $school->id,
            'actor_id' => $admin->id,
            'action' => 'invitation.issued',
            'entity_type' => 'Invitation',
        ]);
    }

    public function test_accept_activates_the_user_and_sets_the_new_password(): void
    {
        Notification::fake();

        $school = $this->makeSchool();
        $admin = $this->makeUser(['role_id' => $this->makeRole('school_admin')->id, 'school_id' => $school->id]);
        $teacher = $this->makeUser(['role_id' => $this->makeRole('teacher')->id, 'school_id' => $school->id]);

        $repository = new InvitationRepository();
        $issued = $repository->issue($teacher, $admin);

        $activated = $repository->accept($issued['plainToken'], 'a-brand-new-password');

        $this->assertSame($teacher->id, $activated->id);
        $this->assertSame('active', $activated->status);
        $this->assertTrue(\Illuminate\Support\Facades\Hash::check('a-brand-new-password', $activated->password));

        $this->assertDatabaseHas('invitations', [
            'id' => $issued['invitation']->id,
            'status' => 'accepted',
        ]);
        $this->assertNotNull($issued['invitation']->fresh()->accepted_at);
    }

    public function test_accept_with_a_wrong_token_throws_invalid_invitation(): void
    {
        Notification::fake();

        $school = $this->makeSchool();
        $admin = $this->makeUser(['role_id' => $this->makeRole('school_admin')->id, 'school_id' => $school->id]);
        $teacher = $this->makeUser(['role_id' => $this->makeRole('teacher')->id, 'school_id' => $school->id]);

        (new InvitationRepository())->issue($teacher, $admin);

        $this->expectException(InvalidInvitationTokenFailure::class);

        (new InvitationRepository())->accept('this-is-not-a-real-token', 'whatever-password');
    }

    public function test_accept_twice_with_the_same_token_fails_the_second_time(): void
    {
        Notification::fake();

        $school = $this->makeSchool();
        $admin = $this->makeUser(['role_id' => $this->makeRole('school_admin')->id, 'school_id' => $school->id]);
        $teacher = $this->makeUser(['role_id' => $this->makeRole('teacher')->id, 'school_id' => $school->id]);

        $repository = new InvitationRepository();
        $issued = $repository->issue($teacher, $admin);

        $repository->accept($issued['plainToken'], 'first-password-123');

        $this->expectException(InvalidInvitationTokenFailure::class);

        $repository->accept($issued['plainToken'], 'second-password-456');
    }

    public function test_accept_after_expiry_throws_expired_invitation(): void
    {
        Notification::fake();

        $school = $this->makeSchool();
        $admin = $this->makeUser(['role_id' => $this->makeRole('school_admin')->id, 'school_id' => $school->id]);
        $teacher = $this->makeUser(['role_id' => $this->makeRole('teacher')->id, 'school_id' => $school->id]);

        $repository = new InvitationRepository();
        $issued = $repository->issue($teacher, $admin, ttlDays: 7);

        $issued['invitation']->update(['expires_at' => now()->subDay()]);

        $this->expectException(ExpiredInvitationFailure::class);

        $repository->accept($issued['plainToken'], 'a-password-123');
    }

    public function test_revoke_marks_pending_invitation_revoked_and_blocks_acceptance(): void
    {
        Notification::fake();

        $school = $this->makeSchool();
        $admin = $this->makeUser(['role_id' => $this->makeRole('school_admin')->id, 'school_id' => $school->id]);
        $teacher = $this->makeUser(['role_id' => $this->makeRole('teacher')->id, 'school_id' => $school->id]);

        $repository = new InvitationRepository();
        $issued = $repository->issue($teacher, $admin);

        $repository->revoke($issued['invitation'], $admin);

        $this->assertSame('revoked', $issued['invitation']->fresh()->status);

        $this->expectException(InvalidInvitationTokenFailure::class);

        $repository->accept($issued['plainToken'], 'a-password-123');
    }

    public function test_issue_dispatches_invitation_notification(): void
    {
        Notification::fake();

        $school = $this->makeSchool();
        $admin = $this->makeUser(['role_id' => $this->makeRole('school_admin')->id, 'school_id' => $school->id]);
        $teacher = $this->makeUser(['role_id' => $this->makeRole('teacher')->id, 'school_id' => $school->id]);

        (new InvitationRepository())->issue($teacher, $admin);

        Notification::assertSentTo($teacher, \App\Notifications\InvitationNotification::class);
    }
}
