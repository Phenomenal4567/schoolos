<?php

namespace Tests\Feature;

use App\Exceptions\Academic\TeacherNotAssignedToSectionFailure;
use App\Repositories\ExamRemarkRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesSchoolOsFixtures;
use Tests\TestCase;

/**
 * Design ref: 26-discovery-hierarchy-status.md ("Rich Report Card Fields").
 *
 * ExamRemarkRepository is the one write path for exam_remarks — this
 * file's central assertions mirror ExamMarkRepository's own test
 * coverage: recordClassTeacherRemark() is gated to the exam's own
 * class_section's teacher, recordProprietorRemark() carries no such
 * gate, both upsert by (exam_id, student_id) rather than duplicating a
 * row on a second call, and neither remark type overwrites the other's
 * column on that shared row.
 */
class ExamRemarkRepositoryTest extends TestCase
{
    use RefreshDatabase;
    use CreatesSchoolOsFixtures;

    public function test_class_teacher_remark_requires_the_exam_section_teacher(): void
    {
        $repository = app(ExamRemarkRepository::class);

        $school = $this->makeSchool();
        $year = $this->makeAcademicYear($school);
        $subject = $this->makeSubject($school);
        $teacher = $this->makeRoleUser('teacher', $school);
        $otherTeacher = $this->makeRoleUser('teacher', $school);
        $section = $this->makeClassSection($school, $year, $teacher);
        $exam = $this->makeExam($school, $year, $section, $subject);
        $student = $this->makeRoleUser('student', $school);
        $this->makeStudentEnrollment($school, $year, $section, $student);

        $this->expectException(TeacherNotAssignedToSectionFailure::class);

        $repository->recordClassTeacherRemark($school->id, $exam->id, $student->id, 'Needs improvement.', $otherTeacher);
    }

    public function test_class_teacher_remark_upserts_by_exam_and_student(): void
    {
        $repository = app(ExamRemarkRepository::class);

        $school = $this->makeSchool();
        $year = $this->makeAcademicYear($school);
        $subject = $this->makeSubject($school);
        $teacher = $this->makeRoleUser('teacher', $school);
        $section = $this->makeClassSection($school, $year, $teacher);
        $exam = $this->makeExam($school, $year, $section, $subject);
        $student = $this->makeRoleUser('student', $school);
        $this->makeStudentEnrollment($school, $year, $section, $student);

        $repository->recordClassTeacherRemark($school->id, $exam->id, $student->id, 'First draft.', $teacher);
        $remark = $repository->recordClassTeacherRemark($school->id, $exam->id, $student->id, 'Final remark.', $teacher);

        $this->assertDatabaseCount('exam_remarks', 1);
        $this->assertSame('Final remark.', $remark->class_teacher_remark);
        $this->assertSame($teacher->id, $remark->class_teacher_remark_by);
    }

    public function test_proprietor_remark_requires_no_teacher_assignment(): void
    {
        $repository = app(ExamRemarkRepository::class);

        $school = $this->makeSchool();
        $year = $this->makeAcademicYear($school);
        $subject = $this->makeSubject($school);
        $teacher = $this->makeRoleUser('teacher', $school);
        $section = $this->makeClassSection($school, $year, $teacher);
        $exam = $this->makeExam($school, $year, $section, $subject);
        $student = $this->makeRoleUser('student', $school);
        $this->makeStudentEnrollment($school, $year, $section, $student);
        $admin = $this->makeRoleUser('school_admin', $school);

        $remark = $repository->recordProprietorRemark($school->id, $exam->id, $student->id, 'Excellent term.', $admin);

        $this->assertSame('Excellent term.', $remark->proprietor_remark);
        $this->assertSame($admin->id, $remark->proprietor_remark_by);
    }

    public function test_the_two_remark_types_do_not_overwrite_each_other_on_the_shared_row(): void
    {
        $repository = app(ExamRemarkRepository::class);

        $school = $this->makeSchool();
        $year = $this->makeAcademicYear($school);
        $subject = $this->makeSubject($school);
        $teacher = $this->makeRoleUser('teacher', $school);
        $section = $this->makeClassSection($school, $year, $teacher);
        $exam = $this->makeExam($school, $year, $section, $subject);
        $student = $this->makeRoleUser('student', $school);
        $this->makeStudentEnrollment($school, $year, $section, $student);
        $admin = $this->makeRoleUser('school_admin', $school);

        $repository->recordClassTeacherRemark($school->id, $exam->id, $student->id, 'Teacher remark.', $teacher);
        $remark = $repository->recordProprietorRemark($school->id, $exam->id, $student->id, 'Proprietor remark.', $admin);

        $this->assertDatabaseCount('exam_remarks', 1);
        $this->assertSame('Teacher remark.', $remark->class_teacher_remark);
        $this->assertSame('Proprietor remark.', $remark->proprietor_remark);
    }

    public function test_a_student_not_enrolled_in_the_exam_section_is_rejected(): void
    {
        $repository = app(ExamRemarkRepository::class);

        $school = $this->makeSchool();
        $year = $this->makeAcademicYear($school);
        $subject = $this->makeSubject($school);
        $teacher = $this->makeRoleUser('teacher', $school);
        $section = $this->makeClassSection($school, $year, $teacher);
        $exam = $this->makeExam($school, $year, $section, $subject);
        $unenrolledStudent = $this->makeRoleUser('student', $school);
        $admin = $this->makeRoleUser('school_admin', $school);

        $this->expectException(\InvalidArgumentException::class);

        $repository->recordProprietorRemark($school->id, $exam->id, $unenrolledStudent->id, 'Remark.', $admin);
    }
}
