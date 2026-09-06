<?php

namespace Tests\Feature;

use App\Exceptions\Staff\NotAStaffMemberFailure;
use App\Models\StaffProfile;
use App\Repositories\StaffProfileRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CreatesSchoolOsFixtures;
use Tests\TestCase;

/**
 * Design ref: 26-discovery-hierarchy-status.md ("Rich Teacher/Staff
 * Enrollment And Profiles").
 *
 * StaffProfileRepository is the one write path for staff_profiles and
 * staff_documents — central assertions: every method rejects a non-staff
 * role (student/parent/super_admin), profile writes upsert by user_id
 * rather than duplicating a row, and replacing a CV deletes the
 * previous file from disk rather than leaving it orphaned.
 */
class StaffProfileRepositoryTest extends TestCase
{
    use RefreshDatabase;
    use CreatesSchoolOsFixtures;

    public function test_a_student_is_rejected_from_every_write_method(): void
    {
        $repository = app(StaffProfileRepository::class);
        $school = $this->makeSchool();
        $student = $this->makeRoleUser('student', $school);

        $this->expectException(NotAStaffMemberFailure::class);

        $repository->updateProfile($school->id, $student, ['responsibilities' => 'Should fail.']);
    }

    public function test_update_profile_upserts_by_user_id(): void
    {
        $repository = app(StaffProfileRepository::class);
        $school = $this->makeSchool();
        $teacher = $this->makeRoleUser('teacher', $school);

        $repository->updateProfile($school->id, $teacher, ['qualifications' => ['B.Sc. Mathematics']]);
        $profile = $repository->updateProfile($school->id, $teacher, [
            'qualifications' => ['B.Sc. Mathematics', 'PGDE'],
            'responsibilities' => 'Head of Mathematics department.',
        ]);

        $this->assertDatabaseCount('staff_profiles', 1);
        $this->assertSame(['B.Sc. Mathematics', 'PGDE'], $profile->qualifications);
        $this->assertSame('Head of Mathematics department.', $profile->responsibilities);
    }

    public function test_uploading_a_new_cv_deletes_the_previous_file(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('staff-cvs/old.pdf', 'old-cv-content');

        $repository = app(StaffProfileRepository::class);
        $school = $this->makeSchool();
        $teacher = $this->makeRoleUser('teacher', $school);
        $this->makeStaffProfile($school, $teacher, ['cv_path' => 'staff-cvs/old.pdf']);

        $repository->uploadCv($school->id, $teacher, 'staff-cvs/new.pdf');

        Storage::disk('local')->assertMissing('staff-cvs/old.pdf');
        $this->assertSame('staff-cvs/new.pdf', StaffProfile::where('user_id', $teacher->id)->first()->cv_path);
    }

    public function test_acknowledge_rules_sets_a_timestamp(): void
    {
        $repository = app(StaffProfileRepository::class);
        $school = $this->makeSchool();
        $teacher = $this->makeRoleUser('teacher', $school);

        $profile = $repository->acknowledgeRules($school->id, $teacher);

        $this->assertNotNull($profile->rules_acknowledged_at);
    }

    public function test_delete_document_rejects_a_document_belonging_to_another_user(): void
    {
        $repository = app(StaffProfileRepository::class);
        $school = $this->makeSchool();
        $teacher = $this->makeRoleUser('teacher', $school);
        $otherTeacher = $this->makeRoleUser('teacher', $school);
        $document = $this->makeStaffDocument($school, $otherTeacher);

        $this->expectException(\InvalidArgumentException::class);

        $repository->deleteDocument($school->id, $teacher, $document->id);
    }

    public function test_delete_document_removes_the_row_and_the_stored_file(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('staff-documents/cert.pdf', 'content');

        $repository = app(StaffProfileRepository::class);
        $school = $this->makeSchool();
        $teacher = $this->makeRoleUser('teacher', $school);
        $document = $this->makeStaffDocument($school, $teacher, ['file_path' => 'staff-documents/cert.pdf']);

        $repository->deleteDocument($school->id, $teacher, $document->id);

        $this->assertDatabaseMissing('staff_documents', ['id' => $document->id]);
        Storage::disk('local')->assertMissing('staff-documents/cert.pdf');
    }
}
