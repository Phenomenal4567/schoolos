<?php

namespace Tests\Feature\TeacherPortal;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesSchoolOsFixtures;
use Tests\TestCase;

/**
 * Design ref: 26-discovery-hierarchy-status.md ("Subject Attendance" gap)
 *
 * Mirrors TeacherPortal\AttendanceControllerTest's shape for
 * SubjectAttendanceController::store()/topic(): request validation, the
 * tenantScope()+teacher_id resolution of $timetableSlot (and its
 * 404-not-403 discipline for a slot taught by a different teacher), and
 * the try/catch that turns StudentNotEnrolledFailure/InvalidArgumentException
 * into form errors instead of a 500.
 */
class SubjectAttendanceControllerTest extends TestCase
{
    use RefreshDatabase;
    use CreatesSchoolOsFixtures;

    public function test_guest_is_redirected_to_login(): void
    {
        $response = $this->post('/teacher/subject-attendance', []);

        $response->assertRedirect('/login');
    }

    public function test_non_teacher_role_is_forbidden(): void
    {
        $school = $this->makeSchool();
        $admin = $this->makeUser(['role_id' => $this->makeRole('school_admin')->id, 'school_id' => $school->id]);

        $response = $this->actingAs($admin)->post('/teacher/subject-attendance', []);

        $response->assertForbidden();
    }

    public function test_teacher_can_mark_attendance_for_a_slot_they_teach(): void
    {
        $school = $this->makeSchool();
        $academicYear = $this->makeAcademicYear($school);
        $teacher = $this->makeUser(['role_id' => $this->makeRole('teacher')->id, 'school_id' => $school->id]);
        $classSection = $this->makeClassSection($school, $academicYear, $teacher);
        $subject = $this->makeSubject($school);
        $slot = $this->makeTimetableSlot($school, $academicYear, $classSection, $subject, $teacher);
        $student = $this->makeUser(['role_id' => $this->makeRole('student')->id, 'school_id' => $school->id]);
        $this->makeStudentEnrollment($school, $academicYear, $classSection, $student);

        $response = $this->actingAs($teacher)->post('/teacher/subject-attendance', [
            'timetable_slot_id' => $slot->id,
            'student_id' => $student->id,
            'date' => '2026-09-05',
            'status' => 'present',
        ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('subject_attendance_records', [
            'school_id' => $school->id,
            'class_section_id' => $classSection->id,
            'subject_id' => $subject->id,
            'timetable_slot_id' => $slot->id,
            'student_id' => $student->id,
            'date' => '2026-09-05',
            'status' => 'present',
        ]);
    }

    /**
     * A subject period is owned by the specific teacher on
     * timetable_slots.teacher_id — a different teacher, even one assigned
     * to the same class section generally, gets the same 404 a
     * nonexistent slot id would.
     */
    public function test_a_teacher_who_does_not_teach_the_slot_gets_a_404_not_a_403(): void
    {
        $school = $this->makeSchool();
        $academicYear = $this->makeAcademicYear($school);
        $ownerTeacher = $this->makeUser(['role_id' => $this->makeRole('teacher')->id, 'school_id' => $school->id]);
        $classSection = $this->makeClassSection($school, $academicYear, $ownerTeacher);
        $subject = $this->makeSubject($school);
        $slot = $this->makeTimetableSlot($school, $academicYear, $classSection, $subject, $ownerTeacher);
        $otherTeacher = $this->makeUser(['role_id' => $this->makeRole('teacher')->id, 'school_id' => $school->id]);
        $student = $this->makeUser(['role_id' => $this->makeRole('student')->id, 'school_id' => $school->id]);
        $this->makeStudentEnrollment($school, $academicYear, $classSection, $student);

        $response = $this->actingAs($otherTeacher)->post('/teacher/subject-attendance', [
            'timetable_slot_id' => $slot->id,
            'student_id' => $student->id,
            'date' => '2026-09-05',
            'status' => 'present',
        ]);

        $response->assertNotFound();
        $this->assertDatabaseCount('subject_attendance_records', 0);
    }

    public function test_marking_a_non_enrolled_student_returns_a_form_error_not_a_500(): void
    {
        $school = $this->makeSchool();
        $academicYear = $this->makeAcademicYear($school);
        $teacher = $this->makeUser(['role_id' => $this->makeRole('teacher')->id, 'school_id' => $school->id]);
        $classSection = $this->makeClassSection($school, $academicYear, $teacher);
        $subject = $this->makeSubject($school);
        $slot = $this->makeTimetableSlot($school, $academicYear, $classSection, $subject, $teacher);
        $student = $this->makeUser(['role_id' => $this->makeRole('student')->id, 'school_id' => $school->id]);
        // Deliberately no makeStudentEnrollment() call.

        $response = $this->actingAs($teacher)->post('/teacher/subject-attendance', [
            'timetable_slot_id' => $slot->id,
            'student_id' => $student->id,
            'date' => '2026-09-05',
            'status' => 'present',
        ]);

        $response->assertSessionHasErrors('student_id');
        $this->assertDatabaseCount('subject_attendance_records', 0);
    }

    public function test_correcting_without_a_reason_returns_a_form_error_not_a_500(): void
    {
        $school = $this->makeSchool();
        $academicYear = $this->makeAcademicYear($school);
        $teacher = $this->makeUser(['role_id' => $this->makeRole('teacher')->id, 'school_id' => $school->id]);
        $classSection = $this->makeClassSection($school, $academicYear, $teacher);
        $subject = $this->makeSubject($school);
        $slot = $this->makeTimetableSlot($school, $academicYear, $classSection, $subject, $teacher);
        $student = $this->makeUser(['role_id' => $this->makeRole('student')->id, 'school_id' => $school->id]);
        $this->makeStudentEnrollment($school, $academicYear, $classSection, $student);

        $this->actingAs($teacher)->post('/teacher/subject-attendance', [
            'timetable_slot_id' => $slot->id,
            'student_id' => $student->id,
            'date' => '2026-09-05',
            'status' => 'absent',
        ]);

        $response = $this->actingAs($teacher)->post('/teacher/subject-attendance', [
            'timetable_slot_id' => $slot->id,
            'student_id' => $student->id,
            'date' => '2026-09-05',
            'status' => 'present',
        ]);

        $response->assertSessionHasErrors('reason');
        $this->assertDatabaseHas('subject_attendance_records', [
            'school_id' => $school->id,
            'student_id' => $student->id,
            'date' => '2026-09-05',
            'status' => 'absent',
        ]);
        $this->assertDatabaseCount('subject_attendance_corrections', 0);
    }

    public function test_correcting_with_a_reason_updates_the_existing_row(): void
    {
        $school = $this->makeSchool();
        $academicYear = $this->makeAcademicYear($school);
        $teacher = $this->makeUser(['role_id' => $this->makeRole('teacher')->id, 'school_id' => $school->id]);
        $classSection = $this->makeClassSection($school, $academicYear, $teacher);
        $subject = $this->makeSubject($school);
        $slot = $this->makeTimetableSlot($school, $academicYear, $classSection, $subject, $teacher);
        $student = $this->makeUser(['role_id' => $this->makeRole('student')->id, 'school_id' => $school->id]);
        $this->makeStudentEnrollment($school, $academicYear, $classSection, $student);

        $this->actingAs($teacher)->post('/teacher/subject-attendance', [
            'timetable_slot_id' => $slot->id,
            'student_id' => $student->id,
            'date' => '2026-09-05',
            'status' => 'absent',
        ]);

        $response = $this->actingAs($teacher)->post('/teacher/subject-attendance', [
            'timetable_slot_id' => $slot->id,
            'student_id' => $student->id,
            'date' => '2026-09-05',
            'status' => 'present',
            'reason' => 'Marked absent by mistake, student was present',
        ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();
        $this->assertDatabaseCount('subject_attendance_records', 1);
        $this->assertDatabaseHas('subject_attendance_records', [
            'school_id' => $school->id,
            'student_id' => $student->id,
            'date' => '2026-09-05',
            'status' => 'present',
        ]);
        $this->assertDatabaseCount('subject_attendance_corrections', 1);
    }

    public function test_a_student_from_another_school_fails_request_validation(): void
    {
        $school = $this->makeSchool();
        $otherSchool = $this->makeSchool();
        $academicYear = $this->makeAcademicYear($school);
        $teacher = $this->makeUser(['role_id' => $this->makeRole('teacher')->id, 'school_id' => $school->id]);
        $classSection = $this->makeClassSection($school, $academicYear, $teacher);
        $subject = $this->makeSubject($school);
        $slot = $this->makeTimetableSlot($school, $academicYear, $classSection, $subject, $teacher);
        $studentInOtherSchool = $this->makeUser([
            'role_id' => $this->makeRole('student')->id,
            'school_id' => $otherSchool->id,
        ]);

        $response = $this->actingAs($teacher)->post('/teacher/subject-attendance', [
            'timetable_slot_id' => $slot->id,
            'student_id' => $studentInOtherSchool->id,
            'date' => '2026-09-05',
            'status' => 'present',
        ]);

        $response->assertSessionHasErrors('student_id');
        $this->assertDatabaseCount('subject_attendance_records', 0);
    }

    public function test_teacher_can_record_a_topic_for_a_slot_they_teach(): void
    {
        $school = $this->makeSchool();
        $academicYear = $this->makeAcademicYear($school);
        $teacher = $this->makeUser(['role_id' => $this->makeRole('teacher')->id, 'school_id' => $school->id]);
        $classSection = $this->makeClassSection($school, $academicYear, $teacher);
        $subject = $this->makeSubject($school);
        $slot = $this->makeTimetableSlot($school, $academicYear, $classSection, $subject, $teacher);

        $response = $this->actingAs($teacher)->post('/teacher/subject-attendance/topic', [
            'timetable_slot_id' => $slot->id,
            'date' => '2026-09-05',
            'topic' => 'Introduction to fractions',
        ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('subject_attendance_topics', [
            'timetable_slot_id' => $slot->id,
            'date' => '2026-09-05',
            'topic' => 'Introduction to fractions',
            'recorded_by' => $teacher->id,
        ]);
    }

    public function test_a_teacher_who_does_not_teach_the_slot_cannot_record_its_topic(): void
    {
        $school = $this->makeSchool();
        $academicYear = $this->makeAcademicYear($school);
        $ownerTeacher = $this->makeUser(['role_id' => $this->makeRole('teacher')->id, 'school_id' => $school->id]);
        $classSection = $this->makeClassSection($school, $academicYear, $ownerTeacher);
        $subject = $this->makeSubject($school);
        $slot = $this->makeTimetableSlot($school, $academicYear, $classSection, $subject, $ownerTeacher);
        $otherTeacher = $this->makeUser(['role_id' => $this->makeRole('teacher')->id, 'school_id' => $school->id]);

        $response = $this->actingAs($otherTeacher)->post('/teacher/subject-attendance/topic', [
            'timetable_slot_id' => $slot->id,
            'date' => '2026-09-05',
            'topic' => 'Introduction to fractions',
        ]);

        $response->assertNotFound();
        $this->assertDatabaseCount('subject_attendance_topics', 0);
    }

    public function test_teacher_can_view_their_own_subject_attendance_index_and_show_pages(): void
    {
        $school = $this->makeSchool();
        $academicYear = $this->makeAcademicYear($school);
        $teacher = $this->makeUser(['role_id' => $this->makeRole('teacher')->id, 'school_id' => $school->id]);
        $classSection = $this->makeClassSection($school, $academicYear, $teacher);
        $subject = $this->makeSubject($school);
        $slot = $this->makeTimetableSlot($school, $academicYear, $classSection, $subject, $teacher);

        $indexResponse = $this->actingAs($teacher)->get('/teacher/subject-attendance');
        $indexResponse->assertOk();

        $showResponse = $this->actingAs($teacher)->get("/teacher/subject-attendance/{$slot->id}");
        $showResponse->assertOk();
    }

    public function test_a_teacher_cannot_view_a_slot_they_do_not_teach(): void
    {
        $school = $this->makeSchool();
        $academicYear = $this->makeAcademicYear($school);
        $ownerTeacher = $this->makeUser(['role_id' => $this->makeRole('teacher')->id, 'school_id' => $school->id]);
        $classSection = $this->makeClassSection($school, $academicYear, $ownerTeacher);
        $subject = $this->makeSubject($school);
        $slot = $this->makeTimetableSlot($school, $academicYear, $classSection, $subject, $ownerTeacher);
        $otherTeacher = $this->makeUser(['role_id' => $this->makeRole('teacher')->id, 'school_id' => $school->id]);

        $response = $this->actingAs($otherTeacher)->get("/teacher/subject-attendance/{$slot->id}");

        $response->assertNotFound();
    }
}
