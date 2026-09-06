<?php

namespace Tests\Feature\StaffPortal;

use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\CreatesSchoolOsFixtures;
use Tests\TestCase;

/**
 * Design ref: SchoolOS Account Creation & Onboarding plan, §3 (Staff
 * onboarding)
 */
class DashboardControllerTest extends TestCase
{
    use RefreshDatabase;
    use CreatesSchoolOsFixtures;

    public function test_guest_is_redirected_to_login(): void
    {
        $response = $this->get('/staff/dashboard');

        $response->assertRedirect('/login');
    }

    #[DataProvider('staffRoleProvider')]
    public function test_each_staff_role_can_view_their_own_dashboard(string $roleKey): void
    {
        $school = $this->makeSchool();
        $staff = $this->makeRoleUser($roleKey, $school);

        $response = $this->actingAs($staff)->get('/staff/dashboard');

        $response->assertOk();
    }

    public static function staffRoleProvider(): array
    {
        return [
            ['accountant'],
            ['librarian'],
            ['receptionist'],
            ['staff'],
        ];
    }

    public function test_teacher_cannot_reach_the_generic_staff_portal(): void
    {
        $school = $this->makeSchool();
        $teacher = $this->makeRoleUser('teacher', $school);

        $response = $this->actingAs($teacher)->get('/staff/dashboard');

        $response->assertForbidden();
    }

    public function test_school_admin_cannot_reach_the_generic_staff_portal(): void
    {
        $school = $this->makeSchool();
        $admin = $this->makeRoleUser('school_admin', $school);

        $response = $this->actingAs($admin)->get('/staff/dashboard');

        $response->assertForbidden();
    }

    public function test_accountant_lands_on_staff_dashboard_after_login(): void
    {
        $school = $this->makeSchool();
        $accountant = $this->makeRoleUser('accountant', $school, ['password' => 'secret-password']);

        $response = $this->post('/login', [
            'identifier' => $accountant->email,
            'password' => 'secret-password',
        ]);

        $response->assertRedirect(route('staff.dashboard'));
    }

    public function test_staff_member_can_check_in_from_the_dashboard(): void
    {
        $school = $this->makeSchool();
        $librarian = $this->makeRoleUser('librarian', $school);

        $response = $this->actingAs($librarian)->post('/staff-attendance/check-in');

        $response->assertRedirect();
        $this->assertDatabaseHas('staff_attendance_records', [
            'user_id' => $librarian->id,
            'school_id' => $school->id,
        ]);

        $dashboard = $this->actingAs($librarian)->get('/staff/dashboard');
        $dashboard->assertSee('checked in today');
    }
}
