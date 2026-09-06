<?php

namespace Tests\Feature\TeacherPortal;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CreatesSchoolOsFixtures;
use Tests\TestCase;

class ProfileControllerTest extends TestCase
{
    use RefreshDatabase;
    use CreatesSchoolOsFixtures;

    public function test_guest_is_redirected_to_login(): void
    {
        $response = $this->get('/teacher/profile');

        $response->assertRedirect('/login');
    }

    public function test_non_teacher_role_is_forbidden(): void
    {
        $school = $this->makeSchool();
        $admin = $this->makeRoleUser('school_admin', $school);

        $response = $this->actingAs($admin)->get('/teacher/profile');

        $response->assertForbidden();
    }

    public function test_teacher_can_view_their_own_profile(): void
    {
        $school = $this->makeSchool();
        $teacher = $this->makeRoleUser('teacher', $school, [
            'name' => 'Ada Teacher',
            'email' => 'ada.teacher@example.test',
            'staff_id' => 'SCHADATCH001',
        ]);

        $response = $this->actingAs($teacher)->get('/teacher/profile');

        $response->assertOk();
        $response->assertViewIs('teacher.profile.show');
        $response->assertViewHas('teacher', fn ($viewTeacher) => $viewTeacher->id === $teacher->id);
        $response->assertSee('Ada Teacher');
        $response->assertSee('SCHADATCH001');
    }

    public function test_teacher_can_update_their_own_profile(): void
    {
        $school = $this->makeSchool();
        $teacher = $this->makeRoleUser('teacher', $school, [
            'name' => 'Old Name',
            'email' => 'old.teacher@example.test',
        ]);

        $response = $this->actingAs($teacher)->put('/teacher/profile', [
            'name' => 'New Name',
            'email' => 'new.teacher@example.test',
            'mobile_no' => '+15550000301',
            'photo_path' => 'profiles/new-name.jpg',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('status');

        $teacher->refresh();

        $this->assertSame('New Name', $teacher->name);
        $this->assertSame('new.teacher@example.test', $teacher->email);
        $this->assertSame('+15550000301', $teacher->mobile_no);
        $this->assertSame('profiles/new-name.jpg', $teacher->photo_path);
    }

    public function test_teacher_cannot_claim_another_users_email(): void
    {
        $school = $this->makeSchool();
        $teacher = $this->makeRoleUser('teacher', $school, ['email' => 'teacher@example.test']);
        $this->makeRoleUser('teacher', $school, ['email' => 'taken@example.test']);

        $response = $this->actingAs($teacher)->put('/teacher/profile', [
            'name' => 'Teacher Name',
            'email' => 'taken@example.test',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertSame('teacher@example.test', $teacher->fresh()->email);
    }

    public function test_teacher_profile_has_no_by_id_route_for_another_teacher(): void
    {
        $school = $this->makeSchool();
        $teacher = $this->makeRoleUser('teacher', $school);
        $otherTeacher = $this->makeRoleUser('teacher', $school);

        $this->actingAs($teacher)
            ->get("/teacher/profile/{$otherTeacher->id}")
            ->assertNotFound();

        $this->actingAs($teacher)
            ->put("/teacher/profile/{$otherTeacher->id}", ['name' => 'Changed'])
            ->assertNotFound();
    }

    public function test_teacher_can_save_qualifications_and_responsibilities(): void
    {
        $school = $this->makeSchool();
        $teacher = $this->makeRoleUser('teacher', $school);

        $this->actingAs($teacher)->put('/teacher/profile/details', [
            'qualifications' => "B.Sc. Mathematics\nPGDE",
            'responsibilities' => 'Head of Mathematics department.',
        ])->assertRedirect();

        $this->assertDatabaseHas('staff_profiles', [
            'user_id' => $teacher->id,
            'responsibilities' => 'Head of Mathematics department.',
        ]);
        $this->assertSame(
            ['B.Sc. Mathematics', 'PGDE'],
            \App\Models\StaffProfile::where('user_id', $teacher->id)->first()->qualifications
        );
    }

    public function test_teacher_can_upload_and_replace_their_cv(): void
    {
        Storage::fake('local');

        $school = $this->makeSchool();
        $teacher = $this->makeRoleUser('teacher', $school);

        $this->actingAs($teacher)->post('/teacher/profile/cv', [
            'cv' => UploadedFile::fake()->create('resume.pdf', 100, 'application/pdf'),
        ])->assertRedirect();

        $profile = \App\Models\StaffProfile::where('user_id', $teacher->id)->firstOrFail();
        Storage::disk('local')->assertExists($profile->cv_path);

        $this->actingAs($teacher)->post('/teacher/profile/cv', [
            'cv' => UploadedFile::fake()->create('resume-v2.pdf', 100, 'application/pdf'),
        ])->assertRedirect();

        $updatedPath = $profile->fresh()->cv_path;
        $this->assertNotSame($profile->cv_path, $updatedPath);
        Storage::disk('local')->assertExists($updatedPath);
    }

    public function test_teacher_can_upload_list_and_remove_a_supporting_document(): void
    {
        Storage::fake('local');

        $school = $this->makeSchool();
        $teacher = $this->makeRoleUser('teacher', $school);

        $this->actingAs($teacher)->post('/teacher/profile/documents', [
            'label' => 'NYSC Certificate',
            'document' => UploadedFile::fake()->create('nysc.pdf', 100, 'application/pdf'),
        ])->assertRedirect();

        $this->actingAs($teacher)->get('/teacher/profile')->assertSee('NYSC Certificate');

        $document = \App\Models\StaffDocument::where('user_id', $teacher->id)->firstOrFail();

        $this->actingAs($teacher)
            ->get("/teacher/profile/documents/{$document->id}/download")
            ->assertOk();

        $this->actingAs($teacher)
            ->delete("/teacher/profile/documents/{$document->id}")
            ->assertRedirect();

        $this->assertDatabaseMissing('staff_documents', ['id' => $document->id]);
    }

    public function test_a_teacher_cannot_download_or_delete_another_teachers_document(): void
    {
        Storage::fake('local');

        $school = $this->makeSchool();
        $teacher = $this->makeRoleUser('teacher', $school);
        $otherTeacher = $this->makeRoleUser('teacher', $school);
        $document = $this->makeStaffDocument($school, $otherTeacher);

        $this->actingAs($teacher)
            ->get("/teacher/profile/documents/{$document->id}/download")
            ->assertNotFound();

        $this->actingAs($teacher)
            ->delete("/teacher/profile/documents/{$document->id}")
            ->assertNotFound();
    }

    public function test_teacher_can_acknowledge_school_rules(): void
    {
        $school = $this->makeSchool();
        $teacher = $this->makeRoleUser('teacher', $school);

        $this->actingAs($teacher)->post('/teacher/profile/acknowledge-rules')->assertRedirect();

        $profile = \App\Models\StaffProfile::where('user_id', $teacher->id)->firstOrFail();
        $this->assertNotNull($profile->rules_acknowledged_at);
    }
}
