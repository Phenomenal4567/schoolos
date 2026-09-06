<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\Concerns\CreatesSchoolOsFixtures;
use Tests\TestCase;

class AccountCreationControllerTest extends TestCase
{
    use RefreshDatabase;
    use CreatesSchoolOsFixtures;

    public function test_admin_can_create_teacher_account_with_school_scope_and_staff_id(): void
    {
        $school = $this->makeSchool(['name' => 'Greenwood High School']);
        $admin = $this->makeRoleUser('school_admin', $school);
        $this->makeRole('teacher');

        $response = $this->actingAs($admin)->post('/admin/staff', [
            'name' => 'Ada Teacher',
            'email' => 'ada.teacher@example.test',
            'role' => 'teacher',
            'password' => 'secret-password',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('status');

        $teacher = User::where('email', 'ada.teacher@example.test')->firstOrFail();

        $this->assertSame($school->id, $teacher->school_id);
        $this->assertSame('teacher', $teacher->role->key);
        $this->assertNotNull($teacher->staff_id);
    }

    public function test_admin_can_create_staff_account_with_staff_role(): void
    {
        $school = $this->makeSchool();
        $admin = $this->makeRoleUser('school_admin', $school);
        $this->makeRole('staff');

        $this->actingAs($admin)->post('/admin/staff', [
            'name' => 'Office Staff',
            'mobile_no' => '+15550000001',
            'role' => 'staff',
            'password' => 'secret-password',
        ])->assertRedirect();

        $staff = User::where('mobile_no', '+15550000001')->firstOrFail();

        $this->assertSame($school->id, $staff->school_id);
        $this->assertSame('staff', $staff->role->key);
        $this->assertNotNull($staff->staff_id);
    }

    public function test_admin_can_create_parent_and_link_multiple_students(): void
    {
        $school = $this->makeSchool();
        $admin = $this->makeRoleUser('school_admin', $school);
        $this->makeRole('parent');
        $studentA = $this->makeRoleUser('student', $school);
        $studentB = $this->makeRoleUser('student', $school);

        $response = $this->actingAs($admin)->post('/admin/parents', [
            'name' => 'Pat Parent',
            'email' => 'pat.parent@example.test',
            'password' => 'secret-password',
            'student_ids' => [$studentA->id, $studentB->id],
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('status');

        $parent = User::where('email', 'pat.parent@example.test')->firstOrFail();

        $this->assertSame($school->id, $parent->school_id);
        $this->assertSame('parent', $parent->role->key);
        $this->assertDatabaseHas('student_parent_links', [
            'parent_id' => $parent->id,
            'student_id' => $studentA->id,
            'status' => 'active',
        ]);
        $this->assertDatabaseHas('student_parent_links', [
            'parent_id' => $parent->id,
            'student_id' => $studentB->id,
            'status' => 'active',
        ]);
    }

    public function test_duplicate_staff_email_is_rejected(): void
    {
        $school = $this->makeSchool();
        $admin = $this->makeRoleUser('school_admin', $school);
        $this->makeRole('teacher');
        $this->makeRoleUser('teacher', $school, ['email' => 'duplicate@example.test']);

        $response = $this->actingAs($admin)->post('/admin/staff', [
            'name' => 'Duplicate Teacher',
            'email' => 'duplicate@example.test',
            'role' => 'teacher',
            'password' => 'secret-password',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertSame(1, User::where('email', 'duplicate@example.test')->count());
    }

    public function test_duplicate_parent_mobile_is_rejected(): void
    {
        $school = $this->makeSchool();
        $admin = $this->makeRoleUser('school_admin', $school);
        $this->makeRole('parent');
        $student = $this->makeRoleUser('student', $school);
        $this->makeRoleUser('parent', $school, ['mobile_no' => '+15550000002']);

        $response = $this->actingAs($admin)->post('/admin/parents', [
            'name' => 'Duplicate Parent',
            'mobile_no' => '+15550000002',
            'password' => 'secret-password',
            'student_ids' => [$student->id],
        ]);

        $response->assertSessionHasErrors('mobile_no');
        $this->assertSame(1, User::where('mobile_no', '+15550000002')->count());
        $this->assertDatabaseCount('student_parent_links', 0);
    }

    /**
     * Design ref: SchoolOS Account Creation & Onboarding plan, "New:
     * Invitation mechanism" — the invite path is additive: omitting
     * `password` and passing `invite: true` creates the account with no
     * usable password and an invitations row, instead of requiring the
     * admin to type one (the two tests above already cover that the
     * original path is unchanged).
     */
    public function test_admin_can_invite_a_teacher_instead_of_setting_a_password(): void
    {
        Notification::fake();

        $school = $this->makeSchool(['name' => 'Greenwood High School']);
        $admin = $this->makeRoleUser('school_admin', $school);
        $this->makeRole('teacher');

        $response = $this->actingAs($admin)->post('/admin/staff', [
            'name' => 'Invited Teacher',
            'email' => 'invited.teacher@example.test',
            'role' => 'teacher',
            'invite' => true,
        ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();

        $teacher = User::where('email', 'invited.teacher@example.test')->firstOrFail();

        $this->assertSame('invited', $teacher->status);
        $this->assertDatabaseHas('invitations', [
            'user_id' => $teacher->id,
            'school_id' => $school->id,
            'status' => 'pending',
        ]);
    }

    public function test_inviting_a_teacher_without_a_password_still_requires_no_password_field(): void
    {
        $school = $this->makeSchool();
        $admin = $this->makeRoleUser('school_admin', $school);
        $this->makeRole('teacher');

        // invite omitted (defaults false) and no password supplied — must
        // fail validation exactly like before this pass, not silently
        // treat a missing invite flag as an invite.
        $response = $this->actingAs($admin)->post('/admin/staff', [
            'name' => 'No Password Teacher',
            'email' => 'no.password@example.test',
            'role' => 'teacher',
        ]);

        $response->assertSessionHasErrors('password');
        $this->assertDatabaseCount('users', 1);
    }

    public function test_admin_can_invite_a_parent_and_link_students(): void
    {
        Notification::fake();

        $school = $this->makeSchool();
        $admin = $this->makeRoleUser('school_admin', $school);
        $this->makeRole('parent');
        $student = $this->makeRoleUser('student', $school);

        $response = $this->actingAs($admin)->post('/admin/parents', [
            'name' => 'Invited Parent',
            'email' => 'invited.parent@example.test',
            'invite' => true,
            'student_ids' => [$student->id],
        ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();

        $parent = User::where('email', 'invited.parent@example.test')->firstOrFail();

        $this->assertSame('invited', $parent->status);
        $this->assertDatabaseHas('student_parent_links', [
            'parent_id' => $parent->id,
            'student_id' => $student->id,
            'status' => 'active',
        ]);
        $this->assertDatabaseHas('invitations', [
            'user_id' => $parent->id,
            'status' => 'pending',
        ]);
    }
}
