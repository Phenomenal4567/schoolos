<?php

namespace App\Repositories;

use App\Exceptions\Academic\TeacherNotAssignedToSectionFailure;
use App\Models\ClassSection;
use App\Models\Exam;
use App\Models\Subject;
use App\Models\User;
use App\Repositories\Concerns\AssertsTeacherAssignedToSection;

/**
 * Design ref: 17-schoolos-academic-domain-map.md §2, §3, §5, §7
 *
 * Same shape as TimetableRepository::create()/AssignmentRepository::
 * create() (see either's doc comment for the reasoning shared across the
 * three), with one difference: the Exam model carries no teacher_id
 * column at all (see that model's own fillable list) — unlike
 * TimetableSlot/LessonPlan/Assignment, an exam has no per-row "authored
 * by" attribution in this schema (17 §7 leaves exam authorship/
 * scheduling ownership an open question this pass doesn't need to
 * resolve). $actor is still required and still gates the write via
 * assertTeacherAssignedToSection() — a teacher may create an exam only
 * for a section they're assigned to, the same F18-generalizing
 * authorization 17 §5 requires for all four resource types — it's just
 * not persisted onto the row itself.
 */
class ExamRepository
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
        string $name,
        string $examDate,
        User $actor
    ): Exam {
        $this->assertTeacherAssignedToSection($actor, $classSectionId);

        $classSection = ClassSection::findOrFail($classSectionId);

        if ($classSection->school_id !== $schoolId) {
            throw new \InvalidArgumentException(
                "ExamRepository::create(): class_section #{$classSectionId} belongs to school "
                . "#{$classSection->school_id}, not the requested school #{$schoolId}."
            );
        }

        $subject = Subject::findOrFail($subjectId);

        if ($subject->school_id !== $schoolId) {
            throw new \InvalidArgumentException(
                "ExamRepository::create(): subject #{$subjectId} belongs to school "
                . "#{$subject->school_id}, not the requested school #{$schoolId}."
            );
        }

        return Exam::create([
            'school_id' => $schoolId,
            'academic_year_id' => $classSection->academic_year_id,
            'class_section_id' => $classSection->id,
            'subject_id' => $subjectId,
            'name' => $name,
            'exam_date' => $examDate,
        ]);
    }
}
