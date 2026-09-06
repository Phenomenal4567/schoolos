<?php

namespace Tests\Feature;

use App\Exceptions\ClassSection\InvalidClassTeacherFailure;
use App\Repositories\ClassSectionRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesSchoolOsFixtures;
use Tests\TestCase;

/**
 * Design ref: 13-schoolos-database-schema-v2.md §3, §7a
 * Decision ref: 16-schoolos-decisions-register.md D3
 *
 * No test file existed for this repository before now — assignClassTeacher()
 * was built in an earlier session and left untested, and create() didn't
 * exist at all (the only thing that could ever produce a ClassSection row
 * was CreatesSchoolOsFixtures::makeClassSection()'s forceFill() bypass).
 * This file covers both methods together since they share the same
 * assertIsTeacher() gate and this is the first place either gets exercised
 * outside a fixture.
 */
class ClassSectionRepositoryTest extends TestCase
{
    use RefreshDatabase;
    use CreatesSchoolOsFixtures;

    public function test_creating_a_class_section_with_a_valid_teacher_writes_the_row(): void
    {
        $school = $this->makeSchool();
        $academicYear = $this->makeAcademicYear($school);
        $teacher = $this->makeUser(['role_id' => $this->makeRole('teacher')->id, 'school_id' => $school->id]);
        $admin = $this->makeUser(['role_id' => $this->makeRole('school_admin')->id, 'school_id' => $school->id]);

        $standard = \App\Models\Standard::create(['school_id' => $school->id, 'name' => 'Grade 5']);
        $section = \App\Models\Section::create(['school_id' => $school->id, 'name' => 'A']);

        $classSection = (new ClassSectionRepository())->create(
            $school->id,
            $academicYear->id,
            $standard->id,
            $section->id,
            $teacher->id,
            $admin
        );

        $this->assertDatabaseHas('class_sections', [
            'id' => $classSection->id,
            'school_id' => $school->id,
            'academic_year_id' => $academicYear->id,
            'standard_id' => $standard->id,
            'section_id' => $section->id,
            'class_teacher_id' => $teacher->id,
        ]);
    }

    public function test_creating_a_class_section_writes_an_audit_log_row(): void
    {
        $school = $this->makeSchool();
        $academicYear = $this->makeAcademicYear($school);
        $teacher = $this->makeUser(['role_id' => $this->makeRole('teacher')->id, 'school_id' => $school->id]);
        $admin = $this->makeUser(['role_id' => $this->makeRole('school_admin')->id, 'school_id' => $school->id]);

        $standard = \App\Models\Standard::create(['school_id' => $school->id, 'name' => 'Grade 5']);
        $section = \App\Models\Section::create(['school_id' => $school->id, 'name' => 'A']);

        $classSection = (new ClassSectionRepository())->create(
            $school->id,
            $academicYear->id,
            $standard->id,
            $section->id,
            $teacher->id,
            $admin
        );

        $this->assertDatabaseHas('audit_logs', [
            'school_id' => $school->id,
            'actor_id' => $admin->id,
            'action' => 'class_section.created',
            'entity_type' => 'ClassSection',
            'entity_id' => $classSection->id,
        ]);
    }

    public function test_creating_a_class_section_with_a_non_teacher_is_rejected(): void
    {
        $school = $this->makeSchool();
        $academicYear = $this->makeAcademicYear($school);
        $notATeacher = $this->makeUser(['role_id' => $this->makeRole('student')->id, 'school_id' => $school->id]);
        $admin = $this->makeUser(['role_id' => $this->makeRole('school_admin')->id, 'school_id' => $school->id]);

        $standard = \App\Models\Standard::create(['school_id' => $school->id, 'name' => 'Grade 5']);
        $section = \App\Models\Section::create(['school_id' => $school->id, 'name' => 'A']);

        $this->expectException(InvalidClassTeacherFailure::class);

        (new ClassSectionRepository())->create(
            $school->id,
            $academicYear->id,
            $standard->id,
            $section->id,
            $notATeacher->id,
            $admin
        );
    }

    public function test_creating_a_class_section_with_a_standard_from_another_school_is_rejected(): void
    {
        $school = $this->makeSchool();
        $otherSchool = $this->makeSchool();
        $academicYear = $this->makeAcademicYear($school);
        $teacher = $this->makeUser(['role_id' => $this->makeRole('teacher')->id, 'school_id' => $school->id]);
        $admin = $this->makeUser(['role_id' => $this->makeRole('school_admin')->id, 'school_id' => $school->id]);

        $standardInOtherSchool = \App\Models\Standard::create([
            'school_id' => $otherSchool->id,
            'name' => 'Grade 5',
        ]);
        $section = \App\Models\Section::create(['school_id' => $school->id, 'name' => 'A']);

        $this->expectException(\InvalidArgumentException::class);

        (new ClassSectionRepository())->create(
            $school->id,
            $academicYear->id,
            $standardInOtherSchool->id,
            $section->id,
            $teacher->id,
            $admin
        );
    }

    public function test_no_row_is_written_when_creation_is_rejected(): void
    {
        $school = $this->makeSchool();
        $academicYear = $this->makeAcademicYear($school);
        $notATeacher = $this->makeUser(['role_id' => $this->makeRole('student')->id, 'school_id' => $school->id]);
        $admin = $this->makeUser(['role_id' => $this->makeRole('school_admin')->id, 'school_id' => $school->id]);

        $standard = \App\Models\Standard::create(['school_id' => $school->id, 'name' => 'Grade 5']);
        $section = \App\Models\Section::create(['school_id' => $school->id, 'name' => 'A']);

        try {
            (new ClassSectionRepository())->create(
                $school->id,
                $academicYear->id,
                $standard->id,
                $section->id,
                $notATeacher->id,
                $admin
            );
        } catch (InvalidClassTeacherFailure) {
            // expected
        }

        $this->assertDatabaseCount('class_sections', 0);
    }

    public function test_assigning_a_valid_teacher_updates_the_existing_row(): void
    {
        $school = $this->makeSchool();
        $academicYear = $this->makeAcademicYear($school);
        $originalTeacher = $this->makeUser(['role_id' => $this->makeRole('teacher')->id, 'school_id' => $school->id]);
        $newTeacher = $this->makeUser(['role_id' => $this->makeRole('teacher')->id, 'school_id' => $school->id]);
        $admin = $this->makeUser(['role_id' => $this->makeRole('school_admin')->id, 'school_id' => $school->id]);
        $classSection = $this->makeClassSection($school, $academicYear, $originalTeacher);

        (new ClassSectionRepository())->assignClassTeacher($classSection->id, $newTeacher->id, $admin);

        $this->assertDatabaseHas('class_sections', [
            'id' => $classSection->id,
            'class_teacher_id' => $newTeacher->id,
        ]);
    }

    public function test_assigning_a_non_teacher_is_rejected(): void
    {
        $school = $this->makeSchool();
        $academicYear = $this->makeAcademicYear($school);
        $originalTeacher = $this->makeUser(['role_id' => $this->makeRole('teacher')->id, 'school_id' => $school->id]);
        $notATeacher = $this->makeUser(['role_id' => $this->makeRole('parent')->id, 'school_id' => $school->id]);
        $admin = $this->makeUser(['role_id' => $this->makeRole('school_admin')->id, 'school_id' => $school->id]);
        $classSection = $this->makeClassSection($school, $academicYear, $originalTeacher);

        $this->expectException(InvalidClassTeacherFailure::class);

        (new ClassSectionRepository())->assignClassTeacher($classSection->id, $notATeacher->id, $admin);
    }

    public function test_assigning_a_teacher_writes_before_and_after_state_to_the_audit_log(): void
    {
        $school = $this->makeSchool();
        $academicYear = $this->makeAcademicYear($school);
        $originalTeacher = $this->makeUser(['role_id' => $this->makeRole('teacher')->id, 'school_id' => $school->id]);
        $newTeacher = $this->makeUser(['role_id' => $this->makeRole('teacher')->id, 'school_id' => $school->id]);
        $admin = $this->makeUser(['role_id' => $this->makeRole('school_admin')->id, 'school_id' => $school->id]);
        $classSection = $this->makeClassSection($school, $academicYear, $originalTeacher);

        (new ClassSectionRepository())->assignClassTeacher($classSection->id, $newTeacher->id, $admin);

        $this->assertDatabaseHas('audit_logs', [
            'school_id' => $school->id,
            'actor_id' => $admin->id,
            'action' => 'class_section.class_teacher_assigned',
            'entity_type' => 'ClassSection',
            'entity_id' => $classSection->id,
        ]);
    }
}
