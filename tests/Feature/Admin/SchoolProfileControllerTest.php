<?php

namespace Tests\Feature\Admin;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CreatesSchoolOsFixtures;
use Tests\TestCase;

class SchoolProfileControllerTest extends TestCase
{
    use RefreshDatabase;
    use CreatesSchoolOsFixtures;

    public function test_guest_is_redirected_to_login(): void
    {
        $response = $this->get('/admin/school-profile');

        $response->assertRedirect('/login');
    }

    public function test_non_admin_role_is_forbidden(): void
    {
        $school = $this->makeSchool();
        $teacher = $this->makeRoleUser('teacher', $school);

        $response = $this->actingAs($teacher)->get('/admin/school-profile');

        $response->assertForbidden();
    }

    public function test_school_admin_can_update_own_school_profile(): void
    {
        Storage::fake('public');

        $school = $this->makeSchool();
        $admin = $this->makeRoleUser('school_admin', $school);

        $this->actingAs($admin)->put('/admin/school-profile', [
            'name' => 'Updated Academy',
            'initials' => 'UA',
            'email' => 'updated-academy@example.test',
            'phone' => '+2348123456789',
            'location' => 'Ikeja, Lagos',
            'google_maps_url' => 'https://maps.google.com/?q=Updated+Academy',
            'school_type' => 'mixed',
            'logo' => UploadedFile::fake()->create('crest.jpg', 10, 'image/jpeg'),
            'fee_overdue_reminder_days' => 14,
        ])->assertRedirect();

        $school->refresh();

        $this->assertSame('Updated Academy', $school->name);
        $this->assertSame('UA', $school->initials);
        $this->assertSame('Ikeja, Lagos', $school->location);
        $this->assertSame('mixed', $school->school_type);
        $this->assertSame(14, $school->fee_overdue_reminder_days);
        Storage::disk('public')->assertExists($school->logo_path);
        $this->assertDatabaseHas('audit_logs', [
            'school_id' => $school->id,
            'actor_id' => $admin->id,
            'action' => 'school.profile_updated',
            'entity_type' => 'School',
            'entity_id' => $school->id,
        ]);
    }

    public function test_fee_overdue_reminder_days_must_be_a_positive_integer(): void
    {
        $school = $this->makeSchool();
        $admin = $this->makeRoleUser('school_admin', $school);

        $this->actingAs($admin)->put('/admin/school-profile', [
            'name' => $school->name,
            'email' => $school->email,
            'phone' => $school->phone,
            'fee_overdue_reminder_days' => 0,
        ])->assertSessionHasErrors('fee_overdue_reminder_days');
    }
}
