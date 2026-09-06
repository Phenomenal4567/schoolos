<?php

namespace App\Repositories;

use App\Models\AcademicYear;

/**
 * Design ref: 13-schoolos-database-schema-v2.md §3
 * Decision ref: 14-schoolos-implementation-plan.md §2
 *
 * The write path that creates an academic_years row — previously the
 * only way to produce one outside a test fixture was
 * CreatesSchoolOsFixtures::makeAcademicYear() or a raw Tinker call, the
 * same gap ClassSectionRepository::create() closed for class_sections.
 *
 * Deliberately thinner than ClassSectionRepository: that repository's
 * gate (assertIsTeacher()) exists because D3 identified a real
 * cross-role invariant on class_teacher_id ("must reference a
 * role='teacher' user"). Nothing about academic_years has an equivalent
 * constraint — a school_admin creating an academic year isn't asserting
 * anything about another user's role, so there is no gate to write here.
 * This is a deliberate observation from reading the template, not a
 * skipped step: adding a role-check or audit-log call here to "match"
 * ClassSectionRepository would be inventing an invariant the schema and
 * decisions register never called for.
 */
class AcademicYearRepository
{
    /**
     * $schoolId is taken from the caller's own scoped context (the
     * acting admin's own school_id), never from client input directly,
     * per Ground Rule 0 — the controller enforces this the same way
     * ClassSectionController::store() does for its own $schoolId
     * parameter.
     */
    public function create(int $schoolId, string $label, bool $isCurrent): AcademicYear
    {
        return AcademicYear::create([
            'school_id' => $schoolId,
            'label' => $label,
            'is_current' => $isCurrent,
        ]);
    }
}
