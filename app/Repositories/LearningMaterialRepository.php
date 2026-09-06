<?php

namespace App\Repositories;

use App\Models\ClassSection;
use App\Models\LearningMaterial;
use App\Models\Subject;
use App\Models\User;
use App\Services\ScopeService;
use Illuminate\Database\Eloquent\Collection;

/**
 * Design ref: 19-discovery-hierarchy-gap-closure-plan.md §6, discovery
 * §7.3 ("School can provide: E-textbooks, Digital learning materials...").
 *
 * create(): school_admin only, matching SchemeOfWorkRepository's
 * "School/admin can upload" reading of the discovery doc — neither
 * class_section_id nor subject_id is required (19 §6: "nullable if some
 * materials are school-wide, not class-specific"), and when one is
 * supplied it's cross-school-validated the same way LessonPlanRepository/
 * SchemeOfWorkRepository validate theirs, so a school-wide material is a
 * first-class case, not merely tolerated absence of a check.
 *
 * visibleTo()/findVisibleTo(): the identical two-branch merge
 * AnnouncementRepository uses for the same nullable-class_section_id
 * problem (see that class's doc comment, and this table's migration
 * comment, for why ScopeService::relationshipScope()'s generic
 * class_section_id dispatch isn't sufficient on its own here) —
 * every school-wide (class_section_id IS NULL) row in the actor's
 * tenant, merged with every class-scoped row relationshipScope()'s
 * existing per-role branch would surface. Not paginated, unlike
 * AnnouncementRepository::visibleTo() — a school's material library is
 * closer in growth shape to LessonPlan's per-class-per-subject list
 * (bounded by class_section x subject combinations) than an
 * ever-growing announcement feed, so this follows
 * ResolvesScopedAcademicResource::scopedIndex()'s unpaginated ->get()
 * convention instead.
 */
class LearningMaterialRepository
{
    /**
     * @throws \InvalidArgumentException if $actor's role isn't
     *         school_admin, or if a supplied $classSectionId/$subjectId
     *         doesn't belong to $schoolId.
     */
    public function create(
        int $schoolId,
        User $actor,
        string $title,
        string $filePath,
        string $materialType,
        ?int $classSectionId,
        ?int $subjectId,
    ): LearningMaterial {
        if (($actor->role->key ?? null) !== 'school_admin') {
            $roleKey = $actor->role->key ?? 'none';

            throw new \InvalidArgumentException(
                "LearningMaterialRepository::create(): role '{$roleKey}' may not upload learning materials."
            );
        }

        if (! in_array($materialType, ['textbook', 'notes', 'other'], true)) {
            throw new \InvalidArgumentException(
                "LearningMaterialRepository::create(): invalid material_type '{$materialType}'."
            );
        }

        if ($classSectionId !== null) {
            $classSection = ClassSection::findOrFail($classSectionId);

            if ($classSection->school_id !== $schoolId) {
                throw new \InvalidArgumentException(
                    "LearningMaterialRepository::create(): class_section #{$classSectionId} belongs to school "
                    . "#{$classSection->school_id}, not the requested school #{$schoolId}."
                );
            }
        }

        if ($subjectId !== null) {
            $subject = Subject::findOrFail($subjectId);

            if ($subject->school_id !== $schoolId) {
                throw new \InvalidArgumentException(
                    "LearningMaterialRepository::create(): subject #{$subjectId} belongs to school "
                    . "#{$subject->school_id}, not the requested school #{$schoolId}."
                );
            }
        }

        return LearningMaterial::create([
            'school_id' => $schoolId,
            'class_section_id' => $classSectionId,
            'subject_id' => $subjectId,
            'title' => $title,
            'file_path' => $filePath,
            'material_type' => $materialType,
        ]);
    }

    public function visibleTo(User $actor, ScopeService $scope): Collection
    {
        $schoolWideIds = $scope
            ->tenantScope(LearningMaterial::query(), $actor)
            ->whereNull('class_section_id')
            ->pluck('id');

        $classScopedIds = $scope
            ->relationshipScope(
                $scope->tenantScope(LearningMaterial::query(), $actor)->whereNotNull('class_section_id'),
                $actor,
                LearningMaterial::class,
            )
            ->pluck('id');

        return LearningMaterial::whereIn('id', $schoolWideIds->merge($classScopedIds))
            ->with(['classSection.standard', 'classSection.section', 'subject'])
            ->orderByDesc('created_at')
            ->get();
    }

    public function findVisibleTo(int $id, User $actor, ScopeService $scope): ?LearningMaterial
    {
        $schoolWideMatch = $scope
            ->tenantScope(LearningMaterial::query(), $actor)
            ->whereNull('class_section_id')
            ->where('id', $id)
            ->with(['classSection.standard', 'classSection.section', 'subject'])
            ->first();

        if ($schoolWideMatch !== null) {
            return $schoolWideMatch;
        }

        return $scope
            ->relationshipScope(
                $scope->tenantScope(LearningMaterial::query(), $actor)->whereNotNull('class_section_id'),
                $actor,
                LearningMaterial::class,
            )
            ->where('id', $id)
            ->with(['classSection.standard', 'classSection.section', 'subject'])
            ->first();
    }
}
