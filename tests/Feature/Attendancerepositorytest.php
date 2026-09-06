<?php

namespace Tests\Feature;

use App\Events\AttendanceCorrected;
use App\Events\AttendanceMarked;
use App\Exceptions\Attendance\StudentNotEnrolledFailure;
use App\Repositories\AttendanceRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\Concerns\CreatesSchoolOsFixtures;
use Tests\TestCase;

/**
 * Design ref: 13-schoolos-database-schema-v2.md §5
 * Decision ref: 14-schoolos-implementation-plan.md §3's Phase 3 test gate
 *
 * Mirrors EnrollmentRepositoryTest/ParentLinkRepositoryTest's shape for
 * AttendanceRepository::mark() — direct, non-concurrent, single-connection
 * coverage of the write path itself: first mark vs. correction, the
 * reason requirement, tenant/year consistency, and the enrollment guard.
 *
 * AttendanceConcurrencyTest's own doc comment explicitly defers to "this"
 * suite for the non-concurrent correction case — a single PHP process
 * calling mark() twice in sequence can only ever exercise the
 * "existing row found" branch, never two overlapping transactions, so
 * that's exactly what belongs here rather than there. This suite existing
 * is also what would have caught the AttendanceRecord `date` cast bug on
 * its own: the cast bug made the correction lookup's `where('date', ...)`
 * never match under SQLite, so a second mark() for the same
 * (student_id, date, session) silently took the "create" branch again and
 * hit the unique constraint instead of updating — see
 * test_marking_the_same_student_date_session_again_corrects_the_existing_row().
 */
class AttendanceRepositoryTest extends TestCase
{
    use RefreshDatabase;
    use CreatesSchoolOsFixtures;

    public function test_first_mark_creates_a_row(): void
    {
        $school = $this->makeSchool();
        $academicYear = $this->makeAcademicYear($school);
        $teacher = $this->makeUser(['role_id' => $this->makeRole('teacher')->id, 'school_id' => $school->id]);
        $classSection = $this->makeClassSection($school, $academicYear, $teacher);
        $student = $this->makeUser(['role_id' => $this->makeRole('student')->id, 'school_id' => $school->id]);
        $this->makeStudentEnrollment($school, $academicYear, $classSection, $student);

        $record = (new AttendanceRepository())->mark(
            $school->id,
            $academicYear->id,
            $classSection->id,
            $student->id,
            '2026-08-25',
            'morning',
            'present',
            $teacher
        );

        $this->assertDatabaseHas('attendance_records', [
            'id' => $record->id,
            'school_id' => $school->id,
            'academic_year_id' => $academicYear->id,
            'class_section_id' => $classSection->id,
            'student_id' => $student->id,
            'recorded_by' => $teacher->id,
            'date' => '2026-08-25',
            'session' => 'morning',
            'status' => 'present',
        ]);
        $this->assertDatabaseCount('attendance_records', 1);
        $this->assertDatabaseCount('attendance_corrections', 0);

        $this->assertDatabaseHas('audit_logs', [
            'school_id' => $school->id,
            'actor_id' => $teacher->id,
            'action' => 'attendance.marked',
            'entity_type' => 'AttendanceRecord',
            'entity_id' => $record->id,
        ]);
    }

    public function test_marking_the_same_student_date_session_again_corrects_the_existing_row(): void
    {
        // This is the test that would have caught the date-cast bug on
        // its own: with a bare 'date' cast (rather than 'date:Y-m-d'),
        // the second mark() call's `where('date', '2026-08-25')` lookup
        // fails to match the stored "2026-08-25 00:00:00" under SQLite,
        // so $existing comes back null, the code takes the "create a new
        // row" branch again, and AttendanceRecord::create() throws on the
        // (student_id, date, session) unique constraint instead of
        // reaching the correction branch this test asserts on.
        $school = $this->makeSchool();
        $academicYear = $this->makeAcademicYear($school);
        $teacher = $this->makeUser(['role_id' => $this->makeRole('teacher')->id, 'school_id' => $school->id]);
        $classSection = $this->makeClassSection($school, $academicYear, $teacher);
        $student = $this->makeUser(['role_id' => $this->makeRole('student')->id, 'school_id' => $school->id]);
        $this->makeStudentEnrollment($school, $academicYear, $classSection, $student);

        $repository = new AttendanceRepository();

        $first = $repository->mark(
            $school->id,
            $academicYear->id,
            $classSection->id,
            $student->id,
            '2026-08-25',
            'morning',
            'absent',
            $teacher
        );

        $second = $repository->mark(
            $school->id,
            $academicYear->id,
            $classSection->id,
            $student->id,
            '2026-08-25',
            'morning',
            'present',
            $teacher,
            null,
            'Student arrived late, marked present after registration closed'
        );

        $this->assertSame($first->id, $second->id);
        $this->assertDatabaseCount('attendance_records', 1);

        $this->assertDatabaseHas('attendance_records', [
            'id' => $first->id,
            'status' => 'present',
        ]);

        $this->assertDatabaseCount('attendance_corrections', 1);
        $this->assertDatabaseHas('attendance_corrections', [
            'attendance_record_id' => $first->id,
            'previous_status' => 'absent',
            'new_status' => 'present',
            'corrected_by' => $teacher->id,
            'reason' => 'Student arrived late, marked present after registration closed',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'school_id' => $school->id,
            'actor_id' => $teacher->id,
            'action' => 'attendance.corrected',
            'entity_type' => 'AttendanceRecord',
            'entity_id' => $first->id,
        ]);
    }

    public function test_correcting_without_a_reason_throws(): void
    {
        $school = $this->makeSchool();
        $academicYear = $this->makeAcademicYear($school);
        $teacher = $this->makeUser(['role_id' => $this->makeRole('teacher')->id, 'school_id' => $school->id]);
        $classSection = $this->makeClassSection($school, $academicYear, $teacher);
        $student = $this->makeUser(['role_id' => $this->makeRole('student')->id, 'school_id' => $school->id]);
        $this->makeStudentEnrollment($school, $academicYear, $classSection, $student);

        $repository = new AttendanceRepository();

        $repository->mark(
            $school->id,
            $academicYear->id,
            $classSection->id,
            $student->id,
            '2026-08-25',
            'morning',
            'absent',
            $teacher
        );

        $this->expectException(\InvalidArgumentException::class);

        $repository->mark(
            $school->id,
            $academicYear->id,
            $classSection->id,
            $student->id,
            '2026-08-25',
            'morning',
            'present',
            $teacher
        );
    }

    public function test_correcting_with_a_blank_reason_throws(): void
    {
        $school = $this->makeSchool();
        $academicYear = $this->makeAcademicYear($school);
        $teacher = $this->makeUser(['role_id' => $this->makeRole('teacher')->id, 'school_id' => $school->id]);
        $classSection = $this->makeClassSection($school, $academicYear, $teacher);
        $student = $this->makeUser(['role_id' => $this->makeRole('student')->id, 'school_id' => $school->id]);
        $this->makeStudentEnrollment($school, $academicYear, $classSection, $student);

        $repository = new AttendanceRepository();

        $repository->mark(
            $school->id,
            $academicYear->id,
            $classSection->id,
            $student->id,
            '2026-08-25',
            'morning',
            'absent',
            $teacher
        );

        $this->expectException(\InvalidArgumentException::class);

        $repository->mark(
            $school->id,
            $academicYear->id,
            $classSection->id,
            $student->id,
            '2026-08-25',
            'morning',
            'present',
            $teacher,
            null,
            '   '
        );
    }

    public function test_marking_against_a_class_section_from_another_school_is_rejected(): void
    {
        $school = $this->makeSchool();
        $otherSchool = $this->makeSchool();
        $academicYear = $this->makeAcademicYear($school);
        $teacherInOtherSchool = $this->makeUser([
            'role_id' => $this->makeRole('teacher')->id,
            'school_id' => $otherSchool->id,
        ]);
        $classSectionInOtherSchool = $this->makeClassSection(
            $otherSchool,
            $this->makeAcademicYear($otherSchool),
            $teacherInOtherSchool
        );
        $student = $this->makeUser(['role_id' => $this->makeRole('student')->id, 'school_id' => $school->id]);
        $teacher = $this->makeUser(['role_id' => $this->makeRole('teacher')->id, 'school_id' => $school->id]);

        $this->expectException(\InvalidArgumentException::class);

        (new AttendanceRepository())->mark(
            $school->id,
            $academicYear->id,
            $classSectionInOtherSchool->id,
            $student->id,
            '2026-08-25',
            'morning',
            'present',
            $teacher
        );
    }

    public function test_marking_against_a_class_section_from_another_academic_year_is_rejected(): void
    {
        $school = $this->makeSchool();
        $academicYear = $this->makeAcademicYear($school);
        $otherAcademicYear = $this->makeAcademicYear($school);
        $teacher = $this->makeUser(['role_id' => $this->makeRole('teacher')->id, 'school_id' => $school->id]);
        $classSection = $this->makeClassSection($school, $otherAcademicYear, $teacher);
        $student = $this->makeUser(['role_id' => $this->makeRole('student')->id, 'school_id' => $school->id]);

        $this->expectException(\InvalidArgumentException::class);

        (new AttendanceRepository())->mark(
            $school->id,
            $academicYear->id,
            $classSection->id,
            $student->id,
            '2026-08-25',
            'morning',
            'present',
            $teacher
        );
    }

    /**
     * Design ref: 09-attendance-map.md §5, 14-schoolos-implementation-plan.md §3
     *
     * Phase 3's job is the event contract, not parent notification
     * delivery (that's the Communication phase) — so this suite proves
     * mark() announces what happened, and stops there. No listener,
     * channel, or queue assertion belongs in this file.
     */
    public function test_first_mark_dispatches_attendance_marked_with_no_correction_event(): void
    {
        Event::fake();

        $school = $this->makeSchool();
        $academicYear = $this->makeAcademicYear($school);
        $teacher = $this->makeUser(['role_id' => $this->makeRole('teacher')->id, 'school_id' => $school->id]);
        $classSection = $this->makeClassSection($school, $academicYear, $teacher);
        $student = $this->makeUser(['role_id' => $this->makeRole('student')->id, 'school_id' => $school->id]);
        $this->makeStudentEnrollment($school, $academicYear, $classSection, $student);

        $record = (new AttendanceRepository())->mark(
            $school->id,
            $academicYear->id,
            $classSection->id,
            $student->id,
            '2026-08-25',
            'morning',
            'absent',
            $teacher
        );

        Event::assertDispatched(AttendanceMarked::class, function (AttendanceMarked $event) use ($record, $teacher) {
            return $event->record->is($record)
                && $event->record->status === 'absent'
                && $event->actor->is($teacher);
        });
        Event::assertNotDispatched(AttendanceCorrected::class);
    }

    public function test_marking_present_also_dispatches_attendance_marked(): void
    {
        // The event contract doesn't distinguish present/absent — that
        // branching (e.g. "only notify on absence") is Communication-
        // phase listener logic per 14 §3's test gate, not something
        // attendance's own event dispatch should encode.
        Event::fake();

        $school = $this->makeSchool();
        $academicYear = $this->makeAcademicYear($school);
        $teacher = $this->makeUser(['role_id' => $this->makeRole('teacher')->id, 'school_id' => $school->id]);
        $classSection = $this->makeClassSection($school, $academicYear, $teacher);
        $student = $this->makeUser(['role_id' => $this->makeRole('student')->id, 'school_id' => $school->id]);
        $this->makeStudentEnrollment($school, $academicYear, $classSection, $student);

        (new AttendanceRepository())->mark(
            $school->id,
            $academicYear->id,
            $classSection->id,
            $student->id,
            '2026-08-25',
            'morning',
            'present',
            $teacher
        );

        Event::assertDispatched(AttendanceMarked::class);
    }

    public function test_correcting_dispatches_attendance_corrected_not_attendance_marked(): void
    {
        $school = $this->makeSchool();
        $academicYear = $this->makeAcademicYear($school);
        $teacher = $this->makeUser(['role_id' => $this->makeRole('teacher')->id, 'school_id' => $school->id]);
        $classSection = $this->makeClassSection($school, $academicYear, $teacher);
        $student = $this->makeUser(['role_id' => $this->makeRole('student')->id, 'school_id' => $school->id]);
        $this->makeStudentEnrollment($school, $academicYear, $classSection, $student);

        $repository = new AttendanceRepository();

        // Real first mark, outside the fake, so the correction below has
        // an actual existing row to work against.
        $first = $repository->mark(
            $school->id,
            $academicYear->id,
            $classSection->id,
            $student->id,
            '2026-08-25',
            'morning',
            'absent',
            $teacher
        );

        Event::fake();

        $repository->mark(
            $school->id,
            $academicYear->id,
            $classSection->id,
            $student->id,
            '2026-08-25',
            'morning',
            'present',
            $teacher,
            null,
            'Student arrived late, marked present after registration closed'
        );

        Event::assertNotDispatched(AttendanceMarked::class);
        Event::assertDispatched(
            AttendanceCorrected::class,
            function (AttendanceCorrected $event) use ($first, $teacher) {
                return $event->record->is($first)
                    && $event->previousStatus === 'absent'
                    && $event->newStatus === 'present'
                    && $event->reason === 'Student arrived late, marked present after registration closed'
                    && $event->actor->is($teacher);
            }
        );
    }

    public function test_a_rejected_correction_dispatches_no_event(): void
    {
        // The InvalidArgumentException for a missing reason is thrown
        // inside the transaction closure, before it returns and before
        // mark() reaches either event() call — DB::transaction() rolls
        // back and rethrows, so nothing after it in mark() runs. This
        // pins that down explicitly rather than leaving it implied.
        $school = $this->makeSchool();
        $academicYear = $this->makeAcademicYear($school);
        $teacher = $this->makeUser(['role_id' => $this->makeRole('teacher')->id, 'school_id' => $school->id]);
        $classSection = $this->makeClassSection($school, $academicYear, $teacher);
        $student = $this->makeUser(['role_id' => $this->makeRole('student')->id, 'school_id' => $school->id]);
        $this->makeStudentEnrollment($school, $academicYear, $classSection, $student);

        $repository = new AttendanceRepository();

        $repository->mark(
            $school->id,
            $academicYear->id,
            $classSection->id,
            $student->id,
            '2026-08-25',
            'morning',
            'absent',
            $teacher
        );

        Event::fake();

        try {
            $repository->mark(
                $school->id,
                $academicYear->id,
                $classSection->id,
                $student->id,
                '2026-08-25',
                'morning',
                'present',
                $teacher
            );
            $this->fail('Expected InvalidArgumentException was not thrown.');
        } catch (\InvalidArgumentException $e) {
            // expected
        }

        Event::assertNotDispatched(AttendanceMarked::class);
        Event::assertNotDispatched(AttendanceCorrected::class);
    }

    public function test_marking_a_non_enrolled_student_throws(): void
    {
        $school = $this->makeSchool();
        $academicYear = $this->makeAcademicYear($school);
        $teacher = $this->makeUser(['role_id' => $this->makeRole('teacher')->id, 'school_id' => $school->id]);
        $classSection = $this->makeClassSection($school, $academicYear, $teacher);
        $student = $this->makeUser(['role_id' => $this->makeRole('student')->id, 'school_id' => $school->id]);
        // Deliberately no makeStudentEnrollment() call — $student has no
        // active student_enrollments row for $classSection.

        $this->expectException(StudentNotEnrolledFailure::class);

        (new AttendanceRepository())->mark(
            $school->id,
            $academicYear->id,
            $classSection->id,
            $student->id,
            '2026-08-25',
            'morning',
            'present',
            $teacher
        );
    }
}