<?php

namespace App\Repositories;

use App\Models\Subject;

/**
 * Design ref: 13-schoolos-database-schema-v2.md §3
 * Decision ref: 15-academic-domain-map.md §2 (Subjects flagged as
 * "CRUD only, admin-gated, lower risk" — the first Phase 4 slice built,
 * deliberately not Exams/Marks/Timetable, whose 15 §1 core-vs-addon
 * decision is still open)
 *
 * The write path that creates a subjects row — previously the only way
 * to produce one outside a test fixture was
 * CreatesSchoolOsFixtures::makeSubject() or a raw Tinker call, the same
 * gap AcademicYearRepository/StandardRepository/SectionRepository closed
 * for their own tables.
 *
 * Deliberately thinner than ClassSectionRepository, for the same reason
 * AcademicYearRepository's doc comment gives: there is no cross-role
 * invariant on a subject the way D3's class_teacher_id check exists —
 * creating a subject doesn't assert anything about another user's role,
 * so there is no gate to write here. Not audit-logged either, matching
 * ClassTeacherAssignmentRepository::assign()'s own reasoning: audit_logs
 * is scoped to the mechanisms D2/D3 specifically depend on, and this
 * isn't one of them.
 */
class SubjectRepository
{
    /**
     * $schoolId is taken from the caller's own scoped context, never
     * from client input directly, per Ground Rule 0. $code is nullable
     * at the schema level (no uniqueness constraint on either name or
     * code) — this method doesn't invent validation the migration
     * doesn't back.
     */
    public function create(int $schoolId, string $name, ?string $code): Subject
    {
        return Subject::create([
            'school_id' => $schoolId,
            'name' => $name,
            'code' => $code,
        ]);
    }
}
