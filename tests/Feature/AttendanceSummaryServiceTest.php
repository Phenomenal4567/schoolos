<?php

namespace Tests\Feature;

use App\Services\AttendanceSummaryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesSchoolOsFixtures;
use Tests\TestCase;

/**
 * Design ref: 26-discovery-hierarchy-status.md ("Rich Report Card Fields").
 *
 * AttendanceSummaryService::forStudent() is the one place "times school
 * opened" / "times the student attended" is computed for a report card.
 * Central assertions: within a configured AcademicTerm covering the
 * exam's exam_date, only that term's attendance_records count; with no
 * such term, every attendance_records row for the exam's academic_year
 * counts instead (the graceful-degradation path); "school opened" counts
 * distinct dates for the whole class_section, "days attended" counts
 * only the target student's present/late dates.
 */
class AttendanceSummaryServiceTest extends TestCase
{
    use RefreshDatabase;
    use CreatesSchoolOsFixtures;

    public function test_counts_are_scoped_to_the_term_covering_the_exam_date(): void
    {
        $service = app(AttendanceSummaryService::class);

        $school = $this->makeSchool();
        $year = $this->makeAcademicYear($school);
        $subject = $this->makeSubject($school);
        $teacher = $this->makeRoleUser('teacher', $school);
        $section = $this->makeClassSection($school, $year, $teacher);
        $student = $this->makeRoleUser('student', $school);
        $this->makeStudentEnrollment($school, $year, $section, $student);

        $this->makeAcademicTerm($school, $year, [
            'start_date' => '2026-01-01',
            'end_date' => '2026-03-31',
        ]);

        $exam = $this->makeExam($school, $year, $section, $subject, ['exam_date' => '2026-02-15']);

        // Inside the term: 3 distinct school days, student present on 2.
        $this->makeAttendanceRecord($school, $year, $section, $student, ['date' => '2026-02-01', 'status' => 'present']);
        $this->makeAttendanceRecord($school, $year, $section, $student, ['date' => '2026-02-02', 'status' => 'absent']);
        $otherStudent = $this->makeRoleUser('student', $school);
        $this->makeAttendanceRecord($school, $year, $section, $otherStudent, ['date' => '2026-02-03', 'status' => 'present']);
        $this->makeAttendanceRecord($school, $year, $section, $student, ['date' => '2026-02-03', 'status' => 'late']);

        // Outside the term entirely — must not be counted.
        $this->makeAttendanceRecord($school, $year, $section, $student, ['date' => '2026-05-01', 'status' => 'present']);

        $summary = $service->forStudent($exam, $student->id);

        $this->assertSame(3, $summary['school_days']);
        $this->assertSame(2, $summary['days_attended']);
        $this->assertSame('2026-01-01', $summary['period_start']);
        $this->assertSame('2026-03-31', $summary['period_end']);
    }

    public function test_falls_back_to_the_whole_academic_year_when_no_term_covers_the_exam_date(): void
    {
        $service = app(AttendanceSummaryService::class);

        $school = $this->makeSchool();
        $year = $this->makeAcademicYear($school);
        $subject = $this->makeSubject($school);
        $teacher = $this->makeRoleUser('teacher', $school);
        $section = $this->makeClassSection($school, $year, $teacher);
        $student = $this->makeRoleUser('student', $school);
        $this->makeStudentEnrollment($school, $year, $section, $student);

        // No AcademicTerm configured at all.
        $exam = $this->makeExam($school, $year, $section, $subject, ['exam_date' => '2026-02-15']);

        $this->makeAttendanceRecord($school, $year, $section, $student, ['date' => '2026-01-10', 'status' => 'present']);
        $this->makeAttendanceRecord($school, $year, $section, $student, ['date' => '2026-06-10', 'status' => 'present']);

        $summary = $service->forStudent($exam, $student->id);

        $this->assertSame(2, $summary['school_days']);
        $this->assertSame(2, $summary['days_attended']);
        $this->assertNull($summary['period_start']);
        $this->assertNull($summary['period_end']);
    }
}
