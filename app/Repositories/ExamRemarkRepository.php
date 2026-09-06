<?php

namespace App\Repositories;

use App\Exceptions\Academic\TeacherNotAssignedToSectionFailure;
use App\Models\Exam;
use App\Models\ExamRemark;
use App\Models\StudentEnrollment;
use App\Models\User;
use App\Repositories\Concerns\AssertsTeacherAssignedToSection;

/**
 * Design ref: 26-discovery-hierarchy-status.md ("Rich Report Card Fields").
 *
 * The one write path for exam_remarks, mirroring ExamMarkRepository's
 * own shape: an upsert-by-natural-key (exam_id, student_id — the
 * migration's UNIQUE constraint) rather than a separate create/update
 * pair, since a remark has no prior "unremarked" row a caller needs to
 * distinguish from a correction. Two methods, not one, because the two
 * remark types have different authors and different authorization:
 * recordClassTeacherRemark() reuses AssertsTeacherAssignedToSection the
 * same way ExamMarkRepository::record() does (only the exam's own
 * class_section's teacher may write it); recordProprietorRemark() has no
 * such check — a school_admin may remark on any of that school's exams,
 * per this class's own note on the "proprietor" role gap.
 */
class ExamRemarkRepository
{
    use AssertsTeacherAssignedToSection;

    /**
     * @throws TeacherNotAssignedToSectionFailure if $teacher has no
     *         class_teacher_assignments row for $examId's class_section
     *         and is not its class_teacher_id.
     * @throws \InvalidArgumentException if $examId doesn't belong to
     *         $schoolId, or $studentId has no active enrollment in the
     *         exam's class_section.
     */
    public function recordClassTeacherRemark(
        int $schoolId,
        int $examId,
        int $studentId,
        string $remark,
        User $teacher,
    ): ExamRemark {
        $exam = $this->assertExamBelongsToSchool($examId, $schoolId, 'recordClassTeacherRemark');

        $this->assertTeacherAssignedToSection($teacher, $exam->class_section_id);
        $this->assertStudentEnrolled($studentId, $exam->class_section_id, 'recordClassTeacherRemark');

        return ExamRemark::updateOrCreate(
            ['exam_id' => $exam->id, 'student_id' => $studentId],
            [
                'school_id' => $schoolId,
                'class_teacher_remark' => $remark,
                'class_teacher_remark_by' => $teacher->id,
            ]
        );
    }

    /**
     * @throws \InvalidArgumentException if $examId doesn't belong to
     *         $schoolId, or $studentId has no active enrollment in the
     *         exam's class_section.
     */
    public function recordProprietorRemark(
        int $schoolId,
        int $examId,
        int $studentId,
        string $remark,
        User $admin,
    ): ExamRemark {
        $exam = $this->assertExamBelongsToSchool($examId, $schoolId, 'recordProprietorRemark');

        $this->assertStudentEnrolled($studentId, $exam->class_section_id, 'recordProprietorRemark');

        return ExamRemark::updateOrCreate(
            ['exam_id' => $exam->id, 'student_id' => $studentId],
            [
                'school_id' => $schoolId,
                'proprietor_remark' => $remark,
                'proprietor_remark_by' => $admin->id,
            ]
        );
    }

    private function assertExamBelongsToSchool(int $examId, int $schoolId, string $method): Exam
    {
        $exam = Exam::findOrFail($examId);

        if ($exam->school_id !== $schoolId) {
            throw new \InvalidArgumentException(
                "ExamRemarkRepository::{$method}(): exam #{$examId} belongs to school "
                . "#{$exam->school_id}, not the requested school #{$schoolId}."
            );
        }

        return $exam;
    }

    private function assertStudentEnrolled(int $studentId, int $classSectionId, string $method): void
    {
        $isEnrolled = StudentEnrollment::where('student_id', $studentId)
            ->where('class_section_id', $classSectionId)
            ->where('status', 'active')
            ->exists();

        if (! $isEnrolled) {
            throw new \InvalidArgumentException(
                "ExamRemarkRepository::{$method}(): student #{$studentId} has no active enrollment "
                . "in class_section #{$classSectionId}."
            );
        }
    }
}
