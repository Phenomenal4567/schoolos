<?php

namespace App\Repositories;

use App\Exceptions\Academic\TeacherNotAssignedToSectionFailure;
use App\Models\Assignment;
use App\Models\ClassSection;
use App\Models\Subject;
use App\Models\User;
use App\Repositories\Concerns\AssertsTeacherAssignedToSection;

/**
 * Design ref: 17-schoolos-academic-domain-map.md §2, §3, §5
 *
 * Covers only Assignment itself (the "a teacher creates the Assignment"
 * half 17 §2 distinguishes from AssignmentSubmission's "a teacher also
 * writes the grade" half) — grading a submission is explicitly out of
 * scope for this pass (see this task's own scoping note: "AssignmentSubmission/
 * ExamMark ... have their own write-authorization shape ... a dedicated
 * task, not a bolt-on here"). No submissions()-touching method belongs on
 * this class.
 *
 * Same shape as TimetableRepository::create() and
 * LessonPlanRepository::create() — see either's doc comment for the
 * reasoning shared across all three (teacher_id always $actor->id,
 * academic_year_id read off $classSection, no audit log).
 */
class AssignmentRepository
{
    use AssertsTeacherAssignedToSection;

    /**
     * @throws TeacherNotAssignedToSectionFailure if $actor has no
     *         class_teacher_assignments row for $classSectionId and is
     *         not its class_teacher_id.
     * @throws \InvalidArgumentException if $classSectionId or $subjectId
     *         doesn't belong to $schoolId.
     */
    public function create(
        int $schoolId,
        int $classSectionId,
        int $subjectId,
        string $title,
        ?string $description,
        ?string $dueDate,
        User $actor
    ): Assignment {
        $this->assertTeacherAssignedToSection($actor, $classSectionId);

        $classSection = ClassSection::findOrFail($classSectionId);

        if ($classSection->school_id !== $schoolId) {
            throw new \InvalidArgumentException(
                "AssignmentRepository::create(): class_section #{$classSectionId} belongs to "
                . "school #{$classSection->school_id}, not the requested school #{$schoolId}."
            );
        }

        $subject = Subject::findOrFail($subjectId);

        if ($subject->school_id !== $schoolId) {
            throw new \InvalidArgumentException(
                "AssignmentRepository::create(): subject #{$subjectId} belongs to school "
                . "#{$subject->school_id}, not the requested school #{$schoolId}."
            );
        }

        return Assignment::create([
            'school_id' => $schoolId,
            'academic_year_id' => $classSection->academic_year_id,
            'class_section_id' => $classSection->id,
            'subject_id' => $subjectId,
            'teacher_id' => $actor->id,
            'title' => $title,
            'description' => $description,
            'due_date' => $dueDate,
        ]);
    }
}
