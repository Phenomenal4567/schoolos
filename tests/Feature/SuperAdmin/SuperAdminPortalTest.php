<?php

namespace Tests\Feature\SuperAdmin;

use App\Models\AuditLog;
use App\Models\School;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\UploadedFile;
use Tests\Concerns\CreatesSchoolOsFixtures;
use Tests\TestCase;

class SuperAdminPortalTest extends TestCase
{
    use RefreshDatabase;
    use CreatesSchoolOsFixtures;

    public function test_guest_is_redirected_to_login(): void
    {
        $response = $this->get('/super-admin/dashboard');

        $response->assertRedirect('/login');
    }

    public function test_school_admin_is_forbidden(): void
    {
        $school = $this->makeSchool();
        $admin = $this->makeRoleUser('school_admin', $school);

        $response = $this->actingAs($admin)->get('/super-admin/dashboard');

        $response->assertForbidden();
    }

    public function test_super_admin_lands_on_platform_dashboard_after_login(): void
    {
        $superAdmin = $this->makeUser([
            'role_id' => $this->makeRole('super_admin')->id,
            'school_id' => null,
            'email' => 'platform@example.test',
            'password' => 'secret-password',
        ]);

        $response = $this->post('/login', [
            'identifier' => $superAdmin->email,
            'password' => 'secret-password',
        ]);

        $response->assertRedirect(route('super-admin.dashboard'));
    }

    public function test_super_admin_dashboard_lists_all_schools(): void
    {
        $superAdmin = $this->makeSuperAdmin();
        $firstSchool = $this->makeSchool(['name' => 'North Campus']);
        $secondSchool = $this->makeSchool(['name' => 'South Campus']);

        $response = $this->actingAs($superAdmin)->get('/super-admin/dashboard');

        $response->assertOk();
        $response->assertSee($firstSchool->name);
        $response->assertSee($secondSchool->name);
    }

    public function test_super_admin_can_create_school(): void
    {
        $superAdmin = $this->makeSuperAdmin();
        Storage::fake('public');

        $response = $this->actingAs($superAdmin)->post('/super-admin/schools', [
            'name' => 'New School',
            'initials' => 'NS',
            'email' => 'new-school@example.test',
            'phone' => '+2348000000000',
            'location' => 'Lagos',
            'google_maps_url' => 'https://maps.google.com/?q=New+School',
            'school_type' => 'primary',
            'logo' => UploadedFile::fake()->create('logo.png', 10, 'image/png'),
        ]);

        $school = School::where('name', 'New School')->firstOrFail();

        $response->assertRedirect(route('super-admin.schools.show', $school));
        $this->assertSame('active', $school->status);
        $this->assertSame('NS', $school->initials);
        $this->assertSame('Lagos', $school->location);
        $this->assertSame('primary', $school->school_type);
        Storage::disk('public')->assertExists($school->logo_path);
        $this->assertDatabaseHas('audit_logs', [
            'school_id' => $school->id,
            'actor_id' => $superAdmin->id,
            'action' => 'school.created',
            'entity_type' => 'School',
            'entity_id' => $school->id,
        ]);
    }

    public function test_super_admin_can_update_and_suspend_school(): void
    {
        $superAdmin = $this->makeSuperAdmin();
        $school = $this->makeSchool();

        $this->actingAs($superAdmin)->put("/super-admin/schools/{$school->id}", [
            'name' => 'Updated School',
            'initials' => 'US',
            'email' => 'updated-school@example.test',
            'phone' => '+2348111111111',
            'location' => 'Abuja',
            'google_maps_url' => 'https://maps.google.com/?q=Updated+School',
            'school_type' => 'secondary',
        ])->assertRedirect();

        $this->actingAs($superAdmin)->post("/super-admin/schools/{$school->id}/status", [
            'status' => 'suspended',
        ])->assertRedirect();

        $this->assertDatabaseHas('schools', [
            'id' => $school->id,
            'name' => 'Updated School',
            'initials' => 'US',
            'location' => 'Abuja',
            'school_type' => 'secondary',
            'status' => 'suspended',
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'school_id' => $school->id,
            'actor_id' => $superAdmin->id,
            'action' => 'school.updated',
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'school_id' => $school->id,
            'actor_id' => $superAdmin->id,
            'action' => 'school.suspended',
        ]);
    }

    public function test_super_admin_can_create_school_admin_for_selected_school(): void
    {
        $superAdmin = $this->makeSuperAdmin();
        $school = $this->makeSchool();

        $this->actingAs($superAdmin)->post("/super-admin/schools/{$school->id}/admins", [
            'name' => 'Campus Admin',
            'email' => 'campus-admin@example.test',
            'password' => 'secret-password',
        ])->assertRedirect();

        $admin = User::where('email', 'campus-admin@example.test')->with('role')->firstOrFail();

        $this->assertSame($school->id, $admin->school_id);
        $this->assertSame('school_admin', $admin->role->key);
        $this->assertTrue(Hash::check('secret-password', $admin->password));
        $this->assertDatabaseHas('audit_logs', [
            'school_id' => $school->id,
            'actor_id' => $superAdmin->id,
            'action' => 'school_admin.created',
            'entity_type' => 'User',
            'entity_id' => $admin->id,
        ]);
    }

    public function test_audit_log_index_can_filter_by_school(): void
    {
        $superAdmin = $this->makeSuperAdmin();
        $firstSchool = $this->makeSchool(['name' => 'Visible School']);
        $secondSchool = $this->makeSchool(['name' => 'Hidden School']);

        AuditLog::create([
            'school_id' => $firstSchool->id,
            'actor_id' => $superAdmin->id,
            'action' => 'visible.action',
            'entity_type' => 'School',
            'entity_id' => $firstSchool->id,
        ]);
        AuditLog::create([
            'school_id' => $secondSchool->id,
            'actor_id' => $superAdmin->id,
            'action' => 'hidden.action',
            'entity_type' => 'School',
            'entity_id' => $secondSchool->id,
        ]);

        $response = $this->actingAs($superAdmin)->get("/super-admin/audit-logs?school_id={$firstSchool->id}");

        $response->assertOk();
        $response->assertSee('visible.action');
        $response->assertDontSee('hidden.action');
    }

    /**
     * Design ref: SchoolOS Onboarding & Authentication UI — demo/trial
     * billing addition.
     */
    public function test_super_admin_can_update_the_trial_length(): void
    {
        $superAdmin = $this->makeSuperAdmin();

        $response = $this->actingAs($superAdmin)->put('/super-admin/platform-settings', [
            'trial_days' => 30,
        ]);

        $response->assertRedirect();
        $this->assertSame(30, \App\Models\PlatformSetting::current()->trial_days);
    }

    public function test_school_admin_cannot_update_the_trial_length(): void
    {
        $school = $this->makeSchool();
        $admin = $this->makeRoleUser('school_admin', $school);

        $response = $this->actingAs($admin)->put('/super-admin/platform-settings', [
            'trial_days' => 30,
        ]);

        $response->assertForbidden();
    }

    public function test_trial_days_must_be_a_positive_integer(): void
    {
        $superAdmin = $this->makeSuperAdmin();

        $response = $this->actingAs($superAdmin)->put('/super-admin/platform-settings', [
            'trial_days' => 0,
        ]);

        $response->assertSessionHasErrors('trial_days');
    }

    private function makeSuperAdmin(): User
    {
        return $this->makeUser([
            'role_id' => $this->makeRole('super_admin')->id,
            'school_id' => null,
        ]);
    }
}
