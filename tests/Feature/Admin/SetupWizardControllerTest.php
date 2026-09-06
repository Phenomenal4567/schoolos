<?php

namespace Tests\Feature\Admin;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesSchoolOsFixtures;
use Tests\TestCase;

/**
 * Design ref: SchoolOS Account Creation & Onboarding plan, §1 (School
 * Admin onboarding)
 */
class SetupWizardControllerTest extends TestCase
{
    use RefreshDatabase;
    use CreatesSchoolOsFixtures;

    public function test_guest_is_redirected_to_login(): void
    {
        $response = $this->get('/admin/setup');

        $response->assertRedirect('/login');
    }

    public function test_non_admin_role_is_forbidden(): void
    {
        $school = $this->makeSchool();
        $teacher = $this->makeRoleUser('teacher', $school);

        $response = $this->actingAs($teacher)->get('/admin/setup');

        $response->assertForbidden();
    }

    public function test_admin_sees_the_wizard_for_their_own_school(): void
    {
        $school = $this->makeSchool();
        $admin = $this->makeRoleUser('school_admin', $school);

        $response = $this->actingAs($admin)->get('/admin/setup');

        $response->assertOk();
        $response->assertSee('School Information');
        $response->assertSee('Academic Configuration');
    }

    public function test_a_new_school_starts_pending_and_the_dashboard_shows_the_banner(): void
    {
        $school = $this->makeSchool();
        $admin = $this->makeRoleUser('school_admin', $school);

        $this->assertSame('pending', $school->fresh()->setup_status);

        $response = $this->actingAs($admin)->get('/admin/dashboard');

        $response->assertOk();
        $response->assertSee('Finish setting up your school');
    }

    public function test_advancing_a_step_moves_setup_status_to_in_progress(): void
    {
        $school = $this->makeSchool();
        $admin = $this->makeRoleUser('school_admin', $school);

        $response = $this->actingAs($admin)->post('/admin/setup/advance', ['step' => 1]);

        $response->assertRedirect();
        $this->assertSame('in_progress', $school->fresh()->setup_status);
        $this->assertSame(2, $school->fresh()->setup_step);
    }

    public function test_completing_setup_hides_the_dashboard_banner(): void
    {
        $school = $this->makeSchool();
        $admin = $this->makeRoleUser('school_admin', $school);

        $this->actingAs($admin)->post('/admin/setup/complete');

        $this->assertSame('complete', $school->fresh()->setup_status);

        $response = $this->actingAs($admin)->get('/admin/dashboard');

        $response->assertOk();
        $response->assertDontSee('Finish setting up your school');
    }

    public function test_setup_never_blocks_reaching_the_dashboard_directly(): void
    {
        $school = $this->makeSchool();
        $admin = $this->makeRoleUser('school_admin', $school);

        // Never touched /admin/setup at all — the dashboard is still
        // fully reachable, per the plan's "never gates" decision.
        $response = $this->actingAs($admin)->get('/admin/dashboard');

        $response->assertOk();
    }

    /**
     * School isolation: advancing/completing setup only ever touches the
     * acting admin's own school, resolved server-side from
     * $request->user() — there is no school-id input an admin could
     * manipulate to affect a different school's setup progress.
     */
    public function test_advancing_setup_never_affects_another_school(): void
    {
        $schoolA = $this->makeSchool();
        $schoolB = $this->makeSchool();
        $adminA = $this->makeRoleUser('school_admin', $schoolA);

        $this->actingAs($adminA)->post('/admin/setup/advance', ['step' => 1]);

        $this->assertSame('pending', $schoolB->fresh()->setup_status);
        $this->assertSame(1, $schoolB->fresh()->setup_step);
    }
}
