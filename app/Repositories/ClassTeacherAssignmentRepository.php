<?php

namespace App\Repositories;

use App\Exceptions\ClassTeacherAssignment\InvalidAssignmentTargetFailure;
use App\Models\ClassSection;
use App\Models\ClassTeacherAssignment;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * Design ref: 13-schoolos-database-schema-v2.md §3
 * Decision ref: 16-schoolos-decisions-register.md D3 (same write-path
 * shape); 12 §3a (this table is what ScopeService::teacherRelationshipScope()
 * joins against)
 *
 * Before this repository existed, class_teacher_assignments had no write
 * path anywhere in the codebase — the only thing that ever populated it
 * was CreatesSchoolOsFixtures::makeClassTeacherAssignment(), a bare
 * Eloquent ::create() with no role check, cross-tenant guard, or
 * concurrency handling. That's the exact gap F18 traces back to: GegoK12
 * had this same data as class_teacher_links and never wired its Gates to
 * consult it. A populated-only-in-tests table would have reproduced that
 * failure mode, just with the missing wiring on the write side instead of
 * the read side — ScopeService::teacherRelationshipScope() would have
 * nothing real to join against in production, so no teacher could ever be
 * authorized for anything.
 *
 * Follows the same shape ParentLinkRepository::link() established: one
 * repository method is the only intended write path (by convention, not a
 * $fillable-level guard — class_teacher_assignments.teacher_id has no
 * equivalent of class_teacher_id's mass-assignment exclusion), it verifies
 * the target user's role before writing, and duplicate calls for the same
 * tuple are idempotent rather than erroring, matching link()'s handling of
 * a double-submitted admin form.
 *
 * Deliberately does not audit-log, unlike ClassSectionRepository's D3
 * methods: audit_logs' own docblock scopes it to the mechanism D2
 * (school-suspension transitions) and D3 (class_teacher_id writes)
 * specifically depend on, and neither EnrollmentRepository::enroll() nor
 * ParentLinkRepository::link() — the two closest structural analogs,
 * also admin-initiated relationship writes — audit-log either. Adding it
 * here without a documented decision would be scope creep against that
 * established pattern, not a fix for a gap; worth revisiting only if a
 * decision explicitly extends audit_logs' scope to this table.
 */
class ClassTeacherAssignmentRepository
{
    /**
     * Create (or return the existing) class_teacher_assignments row for
     * (class_section, subject, teacher, academic_year).
     *
     * $schoolId is taken from the caller's own scoped context, never from
     * client input directly, per Ground Rule 0. The cross-tenant guards
     * below catch a caller-side bug — a class_section or subject that
     * doesn't actually belong to $schoolId — before it reaches the
     * database, the same role EnrollmentRepository::enroll()'s guards
     * play; tenant scoping has already happened upstream of this call.
     *
     * No unassign()/revoke() is provided here: the schema has no status
     * column on this table the way student_parent_links does for
     * unlink(), so removing an assignment would mean a hard delete. Not
     * built speculatively — add it if and when a real caller needs to
     * revoke a teacher's assignment, per the same restraint
     * EnrollmentRepository's docblock applies to widening $fillable.
     *
     * @throws InvalidAssignmentTargetFailure if the target user's role
     *         does not resolve to 'teacher'. Thrown before the
     *         transaction opens, matching ClassSectionRepository's gate.
     * @throws \InvalidArgumentException if $classSectionId doesn't belong
     *         to $schoolId/$academicYearId, or $subjectId doesn't belong
     *         to $schoolId.
     */
    public function assign(
        int $schoolId,
        int $academicYearId,
        int $classSectionId,
        int $subjectId,
        int $teacherId,
        User $actor
    ): ClassTeacherAssignment {
        $teacher = User::with('role')->findOrFail($teacherId);

        if (($teacher->role->key ?? null) !== 'teacher') {
            throw new InvalidAssignmentTargetFailure($teacher);
        }

        $classSection = ClassSection::findOrFail($classSectionId);

        if ($classSection->school_id !== $schoolId) {
            throw new \InvalidArgumentException(
                "ClassTeacherAssignmentRepository::assign(): class_section #{$classSectionId} "
                . "belongs to school #{$classSection->school_id}, not the requested school "
                . "#{$schoolId}."
            );
        }

        if ($classSection->academic_year_id !== $academicYearId) {
            throw new \InvalidArgumentException(
                "ClassTeacherAssignmentRepository::assign(): class_section #{$classSectionId} "
                . "belongs to academic year #{$classSection->academic_year_id}, not the requested "
                . "academic year #{$academicYearId}."
            );
        }

        $subject = Subject::findOrFail($subjectId);

        if ($subject->school_id !== $schoolId) {
            throw new \InvalidArgumentException(
                "ClassTeacherAssignmentRepository::assign(): subject #{$subjectId} belongs to "
                . "school #{$subject->school_id}, not the requested school #{$schoolId}."
            );
        }

        return DB::transaction(function () use (
            $schoolId,
            $academicYearId,
            $classSectionId,
            $subjectId,
            $teacherId
        ) {
            // lockForUpdate: two concurrent assign() calls for the same
            // tuple (e.g. a double-submitted admin form) must not both
            // pass the "does a row exist" check and then both attempt an
            // insert — same race ParentLinkRepository::link() guards
            // against. Only locks a row that already exists, though — the
            // catch clause on the insert below is what closes the
            // narrower race where two brand-new-tuple calls both reach it.
            $existing = ClassTeacherAssignment::where('class_section_id', $classSectionId)
                ->where('subject_id', $subjectId)
                ->where('teacher_id', $teacherId)
                ->where('academic_year_id', $academicYearId)
                ->lockForUpdate()
                ->first();

            if ($existing) {
                // Same tuple already assigned: idempotent no-op, not an
                // error. Unlike EnrollmentRepository's UNIQUE(student_id,
                // academic_year_id) — where a second call is a genuine
                // business-rule violation (a student enrolling twice) —
                // re-assigning the exact same (class_section, subject,
                // teacher, academic_year) tuple isn't a mistake worth
                // surfacing as one; it's the same outcome the caller asked
                // for, already true.
                return $existing;
            }

            try {
                return ClassTeacherAssignment::create([
                    'school_id' => $schoolId,
                    'academic_year_id' => $academicYearId,
                    'class_section_id' => $classSectionId,
                    'subject_id' => $subjectId,
                    'teacher_id' => $teacherId,
                ]);
            } catch (QueryException $e) {
                if ($e->getCode() !== '23000') {
                    throw $e;
                }

                // Lost the race: another concurrent call inserted the row
                // between our lockForUpdate() finding nothing and our own
                // insert. Re-fetch and return it, rather than surfacing a
                // raw constraint-violation exception for what is, from
                // the caller's point of view, the same idempotent-no-op
                // outcome as the $existing branch above.
                return ClassTeacherAssignment::where('class_section_id', $classSectionId)
                    ->where('subject_id', $subjectId)
                    ->where('teacher_id', $teacherId)
                    ->where('academic_year_id', $academicYearId)
                    ->lockForUpdate()
                    ->firstOrFail();
            }
        });
    }
}
