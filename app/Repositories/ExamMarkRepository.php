<?php

namespace App\Repositories;

use App\Exceptions\Academic\TeacherNotAssignedToSectionFailure;
use App\Models\Exam;
use App\Models\ExamComponent;
use App\Models\ExamMark;
use App\Models\StudentEnrollment;
use App\Models\User;
use App\Repositories\Concerns\AssertsTeacherAssignedToSection;

/**
 * Design ref: 17-schoolos-academic-domain-map.md §2, §3, §5, §6 (F29)
 *
 * The write half of ExamMark the prior Phase 4 passes deliberately left
 * out (see ExamRepository's own doc comment: "entering marks is
 * explicitly out of scope for this pass"). Unlike AssignmentSubmission,
 * there is no student-initiated half here — a student never creates
 * their own exam_marks row, only a teacher records one — so this
 * repository has one write method, not two.
 *
 * record() reuses AssertsTeacherAssignedToSection the same way
 * TimetableRepository/LessonPlanRepository/AssignmentRepository/
 * ExamRepository's own create() methods do, checked against the target
 * Exam's own class_section_id (an exam mark has no class_section_id
 * directly, so this resolves it one level up through the parent Exam,
 * the same join ScopeService::teacherRelationshipScope()'s new
 * exam_id branch performs for reads). This is the direct fix for F29's
 * write-side gap on this resource type, mirroring
 * AssignmentSubmissionRepository::grade()'s reasoning but as an
 * upsert-by-assignment rather than a lock-and-mutate, since an exam mark
 * has no prior "ungraded" row for a student the way a submission does —
 * updateOrCreate() covers both first-entry and correction in one write,
 * per the exam_marks migration's UNIQUE(exam_id, student_id) constraint.
 *
 * Also verifies $studentId carries an active StudentEnrollment in the
 * exam's own class_section — belt-and-braces, matching this bundle's
 * "each write path separately verifies its own foreign keys" posture
 * (AttendanceRepository::mark(), EnrollmentRepository::enroll()) rather
 * than trusting the caller-supplied student_id alone.
 */
class ExamMarkRepository
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
    public function record(
        int $schoolId,
        int $examId,
        int $examComponentId,
        int $studentId,
        float $marksObtained,
        float $maxMarks,
        User $teacher
    ): ExamMark {
        $exam = Exam::findOrFail($examId);

        if ($exam->school_id !== $schoolId) {
            throw new \InvalidArgumentException(
                "ExamMarkRepository::record(): exam #{$examId} belongs to school "
                . "#{$exam->school_id}, not the requested school #{$schoolId}."
            );
        }

        $this->assertTeacherAssignedToSection($teacher, $exam->class_section_id);

        $component = ExamComponent::findOrFail($examComponentId);

        if ($component->school_id !== $schoolId) {
            throw new \InvalidArgumentException(
                "ExamMarkRepository::record(): component #{$examComponentId} belongs to school "
                . "#{$component->school_id}, not the requested school #{$schoolId}."
            );
        }

        $isEnrolled = StudentEnrollment::where('student_id', $studentId)
            ->where('class_section_id', $exam->class_section_id)
            ->where('status', 'active')
            ->exists();

        if (! $isEnrolled) {
            throw new \InvalidArgumentException(
                "ExamMarkRepository::record(): student #{$studentId} has no active enrollment "
                . "in exam #{$examId}'s class_section #{$exam->class_section_id}."
            );
        }

        return ExamMark::updateOrCreate(
            [
                'exam_id' => $exam->id,
                'student_id' => $studentId,
                'exam_component_id' => $component->id,
            ],
            [
                'school_id' => $schoolId,
                'marks_obtained' => $marksObtained,
                'max_marks' => $maxMarks,
                'status' => ExamMark::STATUS_DRAFT,
                'recorded_by' => $teacher->id,
                'recorded_at' => now(),
            ]
        );
    }
}
