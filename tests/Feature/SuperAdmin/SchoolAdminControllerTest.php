<?php

namespace Tests\Feature\SuperAdmin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\Concerns\CreatesSchoolOsFixtures;
use Tests\TestCase;

/**
 * Design ref: SchoolOS Account Creation & Onboarding plan, §1 (School
 * Admin onboarding), "New: Invitation mechanism"
 */
class SchoolAdminControllerTest extends TestCase
{
    use RefreshDatabase;
    use CreatesSchoolOsFixtures;

    public function test_super_admin_can_create_a_school_admin_with_a_password(): void
    {
        $school = $this->makeSchool();
        $superAdmin = $this->makeUser(['role_id' => $this->makeRole('super_admin')->id, 'school_id' => null]);
        $this->makeRole('school_admin');

        $response = $this->actingAs($superAdmin)->post("/super-admin/schools/{$school->id}/admins", [
            'name' => 'New Admin',
            'email' => 'new.admin@example.test',
            'password' => 'secret-password',
        ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();

        $admin = User::where('email', 'new.admin@example.test')->firstOrFail();
        $this->assertSame('active', $admin->status);
        $this->assertSame($school->id, $admin->school_id);
        $this->assertSame('school_admin', $admin->role->key);
    }

    public function test_super_admin_can_invite_a_school_admin_instead_of_setting_a_password(): void
    {
        Notification::fake();

        $school = $this->makeSchool();
        $superAdmin = $this->makeUser(['role_id' => $this->makeRole('super_admin')->id, 'school_id' => null]);
        $this->makeRole('school_admin');

        $response = $this->actingAs($superAdmin)->post("/super-admin/schools/{$school->id}/admins", [
            'name' => 'Invited Admin',
            'email' => 'invited.admin@example.test',
            'invite' => true,
        ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();

        $admin = User::where('email', 'invited.admin@example.test')->firstOrFail();
        $this->assertSame('invited', $admin->status);
        $this->assertDatabaseHas('invitations', [
            'user_id' => $admin->id,
            'school_id' => $school->id,
            'status' => 'pending',
        ]);
    }

    public function test_without_invite_a_password_is_still_required(): void
    {
        $school = $this->makeSchool();
        $superAdmin = $this->makeUser(['role_id' => $this->makeRole('super_admin')->id, 'school_id' => null]);
        $this->makeRole('school_admin');

        $response = $this->actingAs($superAdmin)->post("/super-admin/schools/{$school->id}/admins", [
            'name' => 'No Password Admin',
            'email' => 'no.password.admin@example.test',
        ]);

        $response->assertSessionHasErrors('password');
        $this->assertDatabaseCount('users', 1);
    }

    public function test_school_admin_cannot_create_another_school_admin(): void
    {
        $school = $this->makeSchool();
        $admin = $this->makeRoleUser('school_admin', $school);

        $response = $this->actingAs($admin)->post("/super-admin/schools/{$school->id}/admins", [
            'name' => 'Should Not Work',
            'email' => 'nope@example.test',
            'password' => 'secret-password',
        ]);

        $response->assertForbidden();
    }
}
