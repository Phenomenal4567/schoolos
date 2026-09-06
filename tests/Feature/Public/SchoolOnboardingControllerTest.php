<?php

namespace Tests\Feature\Public;

use App\Models\AcademicYear;
use App\Models\PlatformSetting;
use App\Models\School;
use App\Models\Standard;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesSchoolOsFixtures;
use Tests\TestCase;

class SchoolOnboardingControllerTest extends TestCase
{
    use RefreshDatabase;
    use CreatesSchoolOsFixtures;

    public function test_welcome_role_selection_screen_is_available(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('Run your entire school from one simple platform.')
            ->assertSee('Get started free')
            ->assertSee('Sign in');
    }

    public function test_guest_can_view_public_school_onboarding(): void
    {
        $response = $this->get('/get-started');

        $response->assertOk();
        $response->assertSee('School Information');
        $response->assertSee('Modules &amp; Features', false);
        $response->assertSee('Admin Account');
    }

    public function test_school_onboarding_creates_school_admin_and_continues_existing_setup_flow(): void
    {
        $this->makeRole('school_admin');
        PlatformSetting::create(['id' => 1, 'trial_days' => 21]);

        $response = $this->post('/get-started', $this->validPayload());

        $school = School::where('email', 'greenwood@example.test')->firstOrFail();
        $admin = User::where('email', 'owner@example.test')->firstOrFail();

        $response->assertRedirect(route('admin.setup.index'));
        $this->assertAuthenticatedAs($admin);

        $this->assertSame($school->id, $admin->school_id);
        $this->assertSame('school_admin', $admin->role->key);
        $this->assertSame('trial', $school->billing_plan);
        $this->assertNotNull($school->trial_ends_at);
        $this->assertTrue($school->trial_ends_at->isSameDay(now()->addDays(21)));
        $this->assertSame(['primary', 'junior_secondary'], $school->education_levels);
        $this->assertSame(['monday', 'tuesday', 'wednesday', 'thursday', 'friday'], $school->working_days);
        $this->assertSame(['student_information', 'attendance', 'academics'], $school->enabled_modules);
        $this->assertTrue($school->allow_manual_promotion);
        $this->assertSame('2026/2027', AcademicYear::where('school_id', $school->id)->firstOrFail()->label);
        $this->assertGreaterThanOrEqual(9, Standard::where('school_id', $school->id)->count());
    }

    public function test_paid_school_onboarding_has_no_trial_expiry(): void
    {
        $this->makeRole('school_admin');

        $payload = array_merge($this->validPayload(), [
            'school_name' => 'Paid Academy',
            'school_email' => 'paid@example.test',
            'school_phone' => '+234800000002',
            'admin_email' => 'paid-owner@example.test',
            'billing_plan' => 'paid',
        ]);

        $this->post('/get-started', $payload)->assertRedirect(route('admin.setup.index'));

        $school = School::where('email', 'paid@example.test')->firstOrFail();
        $this->assertSame('paid', $school->billing_plan);
        $this->assertNull($school->trial_ends_at);
    }

    private function validPayload(): array
    {
        return [
            'school_name' => 'Greenwood School',
            'school_email' => 'greenwood@example.test',
            'school_phone' => '+234800000001',
            'school_type' => 'mixed',
            'address' => '12 Unity Road',
            'country' => 'Nigeria',
            'state' => 'Lagos',
            'city' => 'Ikeja',
            'education_levels' => ['primary', 'junior_secondary'],
            'academic_year_label' => '2026/2027',
            'working_days' => ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'],
            'grading_system' => 'percentage',
            'allow_manual_promotion' => '1',
            'enabled_modules' => ['student_information', 'attendance', 'academics'],
            'billing_plan' => 'trial',
            'admin_name' => 'School Owner',
            'admin_email' => 'owner@example.test',
            'admin_mobile_no' => '+2348011111111',
            'password' => 'correct-password',
            'password_confirmation' => 'correct-password',
        ];
    }
}
