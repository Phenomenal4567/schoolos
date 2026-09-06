<?php

namespace Tests\Feature\TeacherPortal;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesSchoolOsFixtures;
use Tests\TestCase;

/**
 * Design ref: 12-schoolos-architecture.md §3a, 14-schoolos-implementation-plan.md §4
 *
 * TeacherPortal\AttendanceController::store() is Phase 3's first
 * teacher-facing write route and, until this file, had no controller-
 * level test of its own — AttendanceRepositoryTest covers mark() directly,
 * but nothing exercised the HTTP layer around it: request validation,
 * the tenantScope()->relationshipScope() resolution of $classSection
 * (and its 404-not-403 discipline), or the try/catch that turns
 * StudentNotEnrolledFailure/InvalidArgumentException into form errors
 * instead of a 500. Mirrors Admin\EnrollmentControllerTest's shape for
 * the equivalent gap on that write path.
 */
class AttendanceControllerTest extends TestCase
{
    use RefreshDatabase;
    use CreatesSchoolOsFixtures;

    public function test_guest_is_redirected_to_login(): void
    {
        $response = $this->post('/teacher/attendance', []);

        $response->assertRedirect('/login');
    }

    public function test_non_teacher_role_is_forbidden(): void
    {
        $school = $this->makeSchool();
        $admin = $this->makeUser(['role_id' => $this->makeRole('school_admin')->id, 'school_id' => $school->id]);

        $response = $this->actingAs($admin)->post('/teacher/attendance', []);

        $response->assertForbidden();
    }

    public function test_teacher_can_mark_attendance_for_their_own_class_section(): void
    {
        $school = $this->makeSchool();
        $academicYear = $this->makeAcademicYear($school);
        $teacher = $this->makeUser(['role_id' => $this->makeRole('teacher')->id, 'school_id' => $school->id]);
        $classSection = $this->makeClassSection($school, $academicYear, $teacher);
        $student = $this->makeUser(['role_id' => $this->makeRole('student')->id, 'school_id' => $school->id]);
        $this->makeStudentEnrollment($school, $academicYear, $classSection, $student);

        $response = $this->actingAs($teacher)->post('/teacher/attendance', [
            'class_section_id' => $classSection->id,
            'student_id' => $student->id,
            'date' => '2026-08-25',
            'session' => 'morning',
            'status' => 'present',
        ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('attendance_records', [
            'school_id' => $school->id,
            'class_section_id' => $classSection->id,
            'student_id' => $student->id,
            'date' => '2026-08-25',
            'session' => 'morning',
            'status' => 'present',
        ]);
    }

    /**
     * The 404-not-403 discipline the controller's own doc comment
     * describes: a teacher who is not assigned to $classSection (no
     * class_teacher_assignments row, not the homeroom teacher either)
     * gets the same response as a nonexistent class_section_id, never a
     * signal that the id is valid for someone else's class.
     */
    public function test_a_teacher_not_assigned_to_the_class_section_gets_a_404_not_a_403(): void
    {
        $school = $this->makeSchool();
        $academicYear = $this->makeAcademicYear($school);
        $ownerTeacher = $this->makeUser(['role_id' => $this->makeRole('teacher')->id, 'school_id' => $school->id]);
        $classSection = $this->makeClassSection($school, $academicYear, $ownerTeacher);
        $otherTeacher = $this->makeUser(['role_id' => $this->makeRole('teacher')->id, 'school_id' => $school->id]);
        $student = $this->makeUser(['role_id' => $this->makeRole('student')->id, 'school_id' => $school->id]);
        $this->makeStudentEnrollment($school, $academicYear, $classSection, $student);

        $response = $this->actingAs($otherTeacher)->post('/teacher/attendance', [
            'class_section_id' => $classSection->id,
            'student_id' => $student->id,
            'date' => '2026-08-25',
            'session' => 'morning',
            'status' => 'present',
        ]);

        $response->assertNotFound();
        $this->assertDatabaseCount('attendance_records', 0);
    }

    public function test_marking_a_non_enrolled_student_returns_a_form_error_not_a_500(): void
    {
        $school = $this->makeSchool();
        $academicYear = $this->makeAcademicYear($school);
        $teacher = $this->makeUser(['role_id' => $this->makeRole('teacher')->id, 'school_id' => $school->id]);
        $classSection = $this->makeClassSection($school, $academicYear, $teacher);
        $student = $this->makeUser(['role_id' => $this->makeRole('student')->id, 'school_id' => $school->id]);
        // Deliberately no makeStudentEnrollment() call.

        $response = $this->actingAs($teacher)->post('/teacher/attendance', [
            'class_section_id' => $classSection->id,
            'student_id' => $student->id,
            'date' => '2026-08-25',
            'session' => 'morning',
            'status' => 'present',
        ]);

        $response->assertSessionHasErrors('student_id');
        $this->assertDatabaseCount('attendance_records', 0);
    }

    public function test_correcting_without_a_reason_returns_a_form_error_not_a_500(): void
    {
        $school = $this->makeSchool();
        $academicYear = $this->makeAcademicYear($school);
        $teacher = $this->makeUser(['role_id' => $this->makeRole('teacher')->id, 'school_id' => $school->id]);
        $classSection = $this->makeClassSection($school, $academicYear, $teacher);
        $student = $this->makeUser(['role_id' => $this->makeRole('student')->id, 'school_id' => $school->id]);
        $this->makeStudentEnrollment($school, $academicYear, $classSection, $student);

        $this->actingAs($teacher)->post('/teacher/attendance', [
            'class_section_id' => $classSection->id,
            'student_id' => $student->id,
            'date' => '2026-08-25',
            'session' => 'morning',
            'status' => 'absent',
        ]);

        $response = $this->actingAs($teacher)->post('/teacher/attendance', [
            'class_section_id' => $classSection->id,
            'student_id' => $student->id,
            'date' => '2026-08-25',
            'session' => 'morning',
            'status' => 'present',
        ]);

        $response->assertSessionHasErrors('reason');
        $this->assertDatabaseHas('attendance_records', [
            'school_id' => $school->id,
            'student_id' => $student->id,
            'date' => '2026-08-25',
            'session' => 'morning',
            'status' => 'absent',
        ]);
        $this->assertDatabaseCount('attendance_corrections', 0);
    }

    public function test_correcting_with_a_reason_updates_the_existing_row(): void
    {
        $school = $this->makeSchool();
        $academicYear = $this->makeAcademicYear($school);
        $teacher = $this->makeUser(['role_id' => $this->makeRole('teacher')->id, 'school_id' => $school->id]);
        $classSection = $this->makeClassSection($school, $academicYear, $teacher);
        $student = $this->makeUser(['role_id' => $this->makeRole('student')->id, 'school_id' => $school->id]);
        $this->makeStudentEnrollment($school, $academicYear, $classSection, $student);

        $this->actingAs($teacher)->post('/teacher/attendance', [
            'class_section_id' => $classSection->id,
            'student_id' => $student->id,
            'date' => '2026-08-25',
            'session' => 'morning',
            'status' => 'absent',
        ]);

        $response = $this->actingAs($teacher)->post('/teacher/attendance', [
            'class_section_id' => $classSection->id,
            'student_id' => $student->id,
            'date' => '2026-08-25',
            'session' => 'morning',
            'status' => 'present',
            'reason' => 'Marked absent by mistake, student was present',
        ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();
        $this->assertDatabaseCount('attendance_records', 1);
        $this->assertDatabaseHas('attendance_records', [
            'school_id' => $school->id,
            'student_id' => $student->id,
            'date' => '2026-08-25',
            'session' => 'morning',
            'status' => 'present',
        ]);
        $this->assertDatabaseCount('attendance_corrections', 1);
    }

    /**
     * The Rule::exists(...)->where('school_id', ...) constraint on
     * student_id in the controller's validate() call — a student_id that
     * belongs to a real user in another school must fail validation, not
     * reach the repository at all.
     */
    public function test_a_student_from_another_school_fails_request_validation(): void
    {
        $school = $this->makeSchool();
        $otherSchool = $this->makeSchool();
        $academicYear = $this->makeAcademicYear($school);
        $teacher = $this->makeUser(['role_id' => $this->makeRole('teacher')->id, 'school_id' => $school->id]);
        $classSection = $this->makeClassSection($school, $academicYear, $teacher);
        $studentInOtherSchool = $this->makeUser([
            'role_id' => $this->makeRole('student')->id,
            'school_id' => $otherSchool->id,
        ]);

        $response = $this->actingAs($teacher)->post('/teacher/attendance', [
            'class_section_id' => $classSection->id,
            'student_id' => $studentInOtherSchool->id,
            'date' => '2026-08-25',
            'session' => 'morning',
            'status' => 'present',
        ]);

        $response->assertSessionHasErrors('student_id');
        $this->assertDatabaseCount('attendance_records', 0);
    }
}