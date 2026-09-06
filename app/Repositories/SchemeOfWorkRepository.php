<?php

namespace App\Repositories;

use App\Models\AcademicTerm;
use App\Models\ClassSection;
use App\Models\SchemeOfWork;
use App\Models\Subject;
use App\Models\User;

/**
 * Design ref: 19-discovery-hierarchy-gap-closure-plan.md §6, discovery
 * §7.1 ("School/admin can upload... Teachers can then see the relevant
 * scheme").
 *
 * The one write path for scheme_of_work — school_admin only, no teacher
 * authoring branch (unlike AnnouncementRepository::create()'s two-role
 * shape), matching discovery §7.1's "School/admin can upload" wording
 * literally: nothing in the discovery doc or 19 §6 describes a teacher
 * authoring path for this resource, so this class doesn't invent one.
 * Cross-school FK validation (academic_term_id/class_section_id/
 * subject_id must all belong to the requested school) mirrors
 * LessonPlanRepository::create()'s identical checks for
 * class_section_id/subject_id — same "reject loudly before any write
 * happens" posture, extended to the one additional FK this resource
 * carries that LessonPlan doesn't (academic_term_id; LessonPlan reads
 * its academic_year_id off the resolved class_section instead of
 * taking it as a separate parameter).
 *
 * No audit_logs entry — ordinary content creation, same reasoning
 * LessonPlanRepository::create()'s and AnnouncementRepository::create()'s
 * own doc comments give for their create() paths (audit_logs is scoped
 * to D2/D3-shaped workflow-integrity transitions, not first-time
 * content authoring).
 */
class SchemeOfWorkRepository
{
    /**
     * @throws \InvalidArgumentException if $actor's role isn't
     *         school_admin, or if $academicTermId/$classSectionId/
     *         $subjectId doesn't belong to $schoolId.
     */
    public function create(
        int $schoolId,
        User $actor,
        int $academicTermId,
        int $classSectionId,
        int $subjectId,
        string $content,
    ): SchemeOfWork {
        if (($actor->role->key ?? null) !== 'school_admin') {
            $roleKey = $actor->role->key ?? 'none';

            throw new \InvalidArgumentException(
                "SchemeOfWorkRepository::create(): role '{$roleKey}' may not author scheme of work."
            );
        }

        $academicTerm = AcademicTerm::findOrFail($academicTermId);

        if ($academicTerm->school_id !== $schoolId) {
            throw new \InvalidArgumentException(
                "SchemeOfWorkRepository::create(): academic_term #{$academicTermId} belongs to school "
                . "#{$academicTerm->school_id}, not the requested school #{$schoolId}."
            );
        }

        $classSection = ClassSection::findOrFail($classSectionId);

        if ($classSection->school_id !== $schoolId) {
            throw new \InvalidArgumentException(
                "SchemeOfWorkRepository::create(): class_section #{$classSectionId} belongs to school "
                . "#{$classSection->school_id}, not the requested school #{$schoolId}."
            );
        }

        $subject = Subject::findOrFail($subjectId);

        if ($subject->school_id !== $schoolId) {
            throw new \InvalidArgumentException(
                "SchemeOfWorkRepository::create(): subject #{$subjectId} belongs to school "
                . "#{$subject->school_id}, not the requested school #{$schoolId}."
            );
        }

        return SchemeOfWork::create([
            'school_id' => $schoolId,
            'academic_term_id' => $academicTermId,
            'class_section_id' => $classSectionId,
            'subject_id' => $subjectId,
            'content' => $content,
            'uploaded_by' => $actor->id,
        ]);
    }
}
