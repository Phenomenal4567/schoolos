<?php

namespace Tests\Feature;

use App\Events\SubjectAttendanceCorrected;
use App\Events\SubjectAttendanceMarked;
use App\Exceptions\Attendance\StudentNotEnrolledFailure;
use App\Repositories\SubjectAttendanceRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\Concerns\CreatesSchoolOsFixtures;
use Tests\TestCase;

/**
 * Design ref: 26-discovery-hierarchy-status.md ("Subject Attendance" gap)
 *
 * Mirrors AttendanceRepositoryTest's shape for
 * SubjectAttendanceRepository::mark()/recordTopic() — direct,
 * non-concurrent coverage of the write path itself: first mark vs.
 * correction, the reason requirement, tenant/year consistency (derived
 * from the timetable slot rather than passed directly), the enrollment
 * guard, and the topic-taught upsert.
 */
class SubjectAttendanceRepositoryTest extends TestCase
{
    use RefreshDatabase;
    use CreatesSchoolOsFixtures;

    public function test_first_mark_creates_a_row(): void
    {
        $school = $this->makeSchool();
        $academicYear = $this->makeAcademicYear($school);
        $teacher = $this->makeUser(['role_id' => $this->makeRole('teacher')->id, 'school_id' => $school->id]);
        $classSection = $this->makeClassSection($school, $academicYear, $teacher);
        $subject = $this->makeSubject($school);
        $slot = $this->makeTimetableSlot($school, $academicYear, $classSection, $subject, $teacher);
        $student = $this->makeUser(['role_id' => $this->makeRole('student')->id, 'school_id' => $school->id]);
        $this->makeStudentEnrollment($school, $academicYear, $classSection, $student);

        $record = (new SubjectAttendanceRepository())->mark(
            $school->id,
            $academicYear->id,
            $slot->id,
            $student->id,
            '2026-09-05',
            'present',
            $teacher
        );

        $this->assertDatabaseHas('subject_attendance_records', [
            'id' => $record->id,
            'school_id' => $school->id,
            'academic_year_id' => $academicYear->id,
            'class_section_id' => $classSection->id,
            'subject_id' => $subject->id,
            'timetable_slot_id' => $slot->id,
            'student_id' => $student->id,
            'recorded_by' => $teacher->id,
            'date' => '2026-09-05',
            'status' => 'present',
        ]);
        $this->assertDatabaseCount('subject_attendance_records', 1);
        $this->assertDatabaseCount('subject_attendance_corrections', 0);

        $this->assertDatabaseHas('audit_logs', [
            'school_id' => $school->id,
            'actor_id' => $teacher->id,
            'action' => 'subject_attendance.marked',
            'entity_type' => 'SubjectAttendanceRecord',
            'entity_id' => $record->id,
        ]);
    }

    public function test_marking_the_same_student_date_slot_again_corrects_the_existing_row(): void
    {
        $school = $this->makeSchool();
        $academicYear = $this->makeAcademicYear($school);
        $teacher = $this->makeUser(['role_id' => $this->makeRole('teacher')->id, 'school_id' => $school->id]);
        $classSection = $this->makeClassSection($school, $academicYear, $teacher);
        $subject = $this->makeSubject($school);
        $slot = $this->makeTimetableSlot($school, $academicYear, $classSection, $subject, $teacher);
        $student = $this->makeUser(['role_id' => $this->makeRole('student')->id, 'school_id' => $school->id]);
        $this->makeStudentEnrollment($school, $academicYear, $classSection, $student);

        $repository = new SubjectAttendanceRepository();

        $first = $repository->mark(
            $school->id,
            $academicYear->id,
            $slot->id,
            $student->id,
            '2026-09-05',
            'absent',
            $teacher
        );

        $second = $repository->mark(
            $school->id,
            $academicYear->id,
            $slot->id,
            $student->id,
            '2026-09-05',
            'present',
            $teacher,
            null,
            'Student arrived late, marked present after roll call'
        );

        $this->assertSame($first->id, $second->id);
        $this->assertDatabaseCount('subject_attendance_records', 1);

        $this->assertDatabaseHas('subject_attendance_records', [
            'id' => $first->id,
            'status' => 'present',
        ]);

        $this->assertDatabaseCount('subject_attendance_corrections', 1);
        $this->assertDatabaseHas('subject_attendance_corrections', [
            'subject_attendance_record_id' => $first->id,
            'previous_status' => 'absent',
            'new_status' => 'present',
            'corrected_by' => $teacher->id,
            'reason' => 'Student arrived late, marked present after roll call',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'school_id' => $school->id,
            'actor_id' => $teacher->id,
            'action' => 'subject_attendance.corrected',
            'entity_type' => 'SubjectAttendanceRecord',
            'entity_id' => $first->id,
        ]);
    }

    public function test_correcting_without_a_reason_throws(): void
    {
        $school = $this->makeSchool();
        $academicYear = $this->makeAcademicYear($school);
        $teacher = $this->makeUser(['role_id' => $this->makeRole('teacher')->id, 'school_id' => $school->id]);
        $classSection = $this->makeClassSection($school, $academicYear, $teacher);
        $subject = $this->makeSubject($school);
        $slot = $this->makeTimetableSlot($school, $academicYear, $classSection, $subject, $teacher);
        $student = $this->makeUser(['role_id' => $this->makeRole('student')->id, 'school_id' => $school->id]);
        $this->makeStudentEnrollment($school, $academicYear, $classSection, $student);

        $repository = new SubjectAttendanceRepository();

        $repository->mark($school->id, $academicYear->id, $slot->id, $student->id, '2026-09-05', 'absent', $teacher);

        $this->expectException(\InvalidArgumentException::class);

        $repository->mark($school->id, $academicYear->id, $slot->id, $student->id, '2026-09-05', 'present', $teacher);
    }

    public function test_marking_against_a_slot_from_another_school_is_rejected(): void
    {
        $school = $this->makeSchool();
        $otherSchool = $this->makeSchool();
        $academicYear = $this->makeAcademicYear($school);
        $otherAcademicYear = $this->makeAcademicYear($otherSchool);
        $teacherInOtherSchool = $this->makeUser([
            'role_id' => $this->makeRole('teacher')->id,
            'school_id' => $otherSchool->id,
        ]);
        $classSectionInOtherSchool = $this->makeClassSection($otherSchool, $otherAcademicYear, $teacherInOtherSchool);
        $subjectInOtherSchool = $this->makeSubject($otherSchool);
        $slotInOtherSchool = $this->makeTimetableSlot(
            $otherSchool,
            $otherAcademicYear,
            $classSectionInOtherSchool,
            $subjectInOtherSchool,
            $teacherInOtherSchool
        );
        $student = $this->makeUser(['role_id' => $this->makeRole('student')->id, 'school_id' => $school->id]);
        $teacher = $this->makeUser(['role_id' => $this->makeRole('teacher')->id, 'school_id' => $school->id]);

        $this->expectException(\InvalidArgumentException::class);

        (new SubjectAttendanceRepository())->mark(
            $school->id,
            $academicYear->id,
            $slotInOtherSchool->id,
            $student->id,
            '2026-09-05',
            'present',
            $teacher
        );
    }

    public function test_marking_against_a_slot_from_another_academic_year_is_rejected(): void
    {
        $school = $this->makeSchool();
        $academicYear = $this->makeAcademicYear($school);
        $otherAcademicYear = $this->makeAcademicYear($school);
        $teacher = $this->makeUser(['role_id' => $this->makeRole('teacher')->id, 'school_id' => $school->id]);
        $classSection = $this->makeClassSection($school, $otherAcademicYear, $teacher);
        $subject = $this->makeSubject($school);
        $slot = $this->makeTimetableSlot($school, $otherAcademicYear, $classSection, $subject, $teacher);
        $student = $this->makeUser(['role_id' => $this->makeRole('student')->id, 'school_id' => $school->id]);

        $this->expectException(\InvalidArgumentException::class);

        (new SubjectAttendanceRepository())->mark(
            $school->id,
            $academicYear->id,
            $slot->id,
            $student->id,
            '2026-09-05',
            'present',
            $teacher
        );
    }

    public function test_marking_a_non_enrolled_student_throws(): void
    {
        $school = $this->makeSchool();
        $academicYear = $this->makeAcademicYear($school);
        $teacher = $this->makeUser(['role_id' => $this->makeRole('teacher')->id, 'school_id' => $school->id]);
        $classSection = $this->makeClassSection($school, $academicYear, $teacher);
        $subject = $this->makeSubject($school);
        $slot = $this->makeTimetableSlot($school, $academicYear, $classSection, $subject, $teacher);
        $student = $this->makeUser(['role_id' => $this->makeRole('student')->id, 'school_id' => $school->id]);
        // Deliberately no makeStudentEnrollment() call.

        $this->expectException(StudentNotEnrolledFailure::class);

        (new SubjectAttendanceRepository())->mark(
            $school->id,
            $academicYear->id,
            $slot->id,
            $student->id,
            '2026-09-05',
            'present',
            $teacher
        );
    }

    public function test_first_mark_dispatches_subject_attendance_marked_with_no_correction_event(): void
    {
        Event::fake();

        $school = $this->makeSchool();
        $academicYear = $this->makeAcademicYear($school);
        $teacher = $this->makeUser(['role_id' => $this->makeRole('teacher')->id, 'school_id' => $school->id]);
        $classSection = $this->makeClassSection($school, $academicYear, $teacher);
        $subject = $this->makeSubject($school);
        $slot = $this->makeTimetableSlot($school, $academicYear, $classSection, $subject, $teacher);
        $student = $this->makeUser(['role_id' => $this->makeRole('student')->id, 'school_id' => $school->id]);
        $this->makeStudentEnrollment($school, $academicYear, $classSection, $student);

        $record = (new SubjectAttendanceRepository())->mark(
            $school->id,
            $academicYear->id,
            $slot->id,
            $student->id,
            '2026-09-05',
            'absent',
            $teacher
        );

        Event::assertDispatched(SubjectAttendanceMarked::class, function (SubjectAttendanceMarked $event) use ($record, $teacher) {
            return $event->record->is($record)
                && $event->record->status === 'absent'
                && $event->actor->is($teacher);
        });
        Event::assertNotDispatched(SubjectAttendanceCorrected::class);
    }

    public function test_correcting_dispatches_subject_attendance_corrected_not_marked(): void
    {
        $school = $this->makeSchool();
        $academicYear = $this->makeAcademicYear($school);
        $teacher = $this->makeUser(['role_id' => $this->makeRole('teacher')->id, 'school_id' => $school->id]);
        $classSection = $this->makeClassSection($school, $academicYear, $teacher);
        $subject = $this->makeSubject($school);
        $slot = $this->makeTimetableSlot($school, $academicYear, $classSection, $subject, $teacher);
        $student = $this->makeUser(['role_id' => $this->makeRole('student')->id, 'school_id' => $school->id]);
        $this->makeStudentEnrollment($school, $academicYear, $classSection, $student);

        $repository = new SubjectAttendanceRepository();

        $first = $repository->mark($school->id, $academicYear->id, $slot->id, $student->id, '2026-09-05', 'absent', $teacher);

        Event::fake();

        $repository->mark(
            $school->id,
            $academicYear->id,
            $slot->id,
            $student->id,
            '2026-09-05',
            'present',
            $teacher,
            null,
            'Student arrived late, marked present after roll call'
        );

        Event::assertNotDispatched(SubjectAttendanceMarked::class);
        Event::assertDispatched(
            SubjectAttendanceCorrected::class,
            fn (SubjectAttendanceCorrected $event) => $event->record->is($first)
                && $event->previousStatus === 'absent'
                && $event->newStatus === 'present'
                && $event->actor->is($teacher)
        );
    }

    public function test_record_topic_creates_a_row(): void
    {
        $school = $this->makeSchool();
        $academicYear = $this->makeAcademicYear($school);
        $teacher = $this->makeUser(['role_id' => $this->makeRole('teacher')->id, 'school_id' => $school->id]);
        $classSection = $this->makeClassSection($school, $academicYear, $teacher);
        $subject = $this->makeSubject($school);
        $slot = $this->makeTimetableSlot($school, $academicYear, $classSection, $subject, $teacher);

        $topic = (new SubjectAttendanceRepository())->recordTopic(
            $school->id,
            $slot->id,
            '2026-09-05',
            'Introduction to fractions',
            $teacher
        );

        $this->assertDatabaseHas('subject_attendance_topics', [
            'id' => $topic->id,
            'school_id' => $school->id,
            'class_section_id' => $classSection->id,
            'subject_id' => $subject->id,
            'timetable_slot_id' => $slot->id,
            'date' => '2026-09-05',
            'topic' => 'Introduction to fractions',
            'recorded_by' => $teacher->id,
        ]);
    }

    public function test_record_topic_again_for_the_same_slot_and_date_updates_the_existing_row(): void
    {
        $school = $this->makeSchool();
        $academicYear = $this->makeAcademicYear($school);
        $teacher = $this->makeUser(['role_id' => $this->makeRole('teacher')->id, 'school_id' => $school->id]);
        $classSection = $this->makeClassSection($school, $academicYear, $teacher);
        $subject = $this->makeSubject($school);
        $slot = $this->makeTimetableSlot($school, $academicYear, $classSection, $subject, $teacher);

        $repository = new SubjectAttendanceRepository();

        $first = $repository->recordTopic($school->id, $slot->id, '2026-09-05', 'Introduction to fractions', $teacher);
        $second = $repository->recordTopic($school->id, $slot->id, '2026-09-05', 'Fractions — corrected topic', $teacher);

        $this->assertSame($first->id, $second->id);
        $this->assertDatabaseCount('subject_attendance_topics', 1);
        $this->assertDatabaseHas('subject_attendance_topics', [
            'id' => $first->id,
            'topic' => 'Fractions — corrected topic',
        ]);
    }

    public function test_recording_a_topic_against_a_slot_from_another_school_is_rejected(): void
    {
        $school = $this->makeSchool();
        $otherSchool = $this->makeSchool();
        $otherAcademicYear = $this->makeAcademicYear($otherSchool);
        $teacherInOtherSchool = $this->makeUser([
            'role_id' => $this->makeRole('teacher')->id,
            'school_id' => $otherSchool->id,
        ]);
        $classSectionInOtherSchool = $this->makeClassSection($otherSchool, $otherAcademicYear, $teacherInOtherSchool);
        $subjectInOtherSchool = $this->makeSubject($otherSchool);
        $slotInOtherSchool = $this->makeTimetableSlot(
            $otherSchool,
            $otherAcademicYear,
            $classSectionInOtherSchool,
            $subjectInOtherSchool,
            $teacherInOtherSchool
        );
        $teacher = $this->makeUser(['role_id' => $this->makeRole('teacher')->id, 'school_id' => $school->id]);

        $this->expectException(\InvalidArgumentException::class);

        (new SubjectAttendanceRepository())->recordTopic(
            $school->id,
            $slotInOtherSchool->id,
            '2026-09-05',
            'Introduction to fractions',
            $teacher
        );
    }
}
