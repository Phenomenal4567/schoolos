<?php

namespace App\Services;

use App\Models\Assignment;
use App\Models\ClassSection;
use App\Models\ClassTeacherAssignment;
use App\Models\Exam;
use App\Models\StudentEnrollment;
use App\Models\StudentParentLink;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;

/**
 * Design ref: 12-schoolos-architecture.md §3a
 * Decision ref: 16-schoolos-decisions-register.md's corrections section
 * (studentRelationshipScope() self-only vs. parentRelationshipScope()
 * self-or-linked-children — see 14 §8)
 *
 * Real contract confirmed against tests/Feature/Phase1TestGateTest.php
 * (rows 6–7) and app/Http/Controllers/ParentPortal/DashboardController.php
 * — both call relationshipScope(Builder $query, User $user, string
 * $modelClass): Builder and intersect it with tenantScope() into the
 * query *before* it runs, never fetch-then-check. The previous version of
 * this file (a boolean $user/$model check) did not match that contract
 * and has been replaced.
 *
 * Exists to make §3a's "tier 3" (F21/F22 — no scoping check of any kind)
 * structurally unreachable: tenantScope() and relationshipScope() are
 * the one shared mechanism every controller in this bundle calls, rather
 * than each controller author re-deriving its own ad hoc ownership
 * check the way GegoK12's per-endpoint Gates did.
 */
class ScopeService
{
    /**
     * Tenant scope: constrains $query to the acting user's own school.
     * Superadmin-exempt (F3's fix, D5) — a super_admin has school_id
     * === null and is not constrained by this at all, since D5 resolved
     * that exemption as a single, centrally-checked branch here rather
     * than a per-call-site bypass.
     */
    public function tenantScope(Builder $query, User $user): Builder
    {
        if (($user->role->key ?? null) === 'super_admin') {
            return $query;
        }

        return $query->where('school_id', $user->school_id);
    }

    /**
     * Relationship scope: intersects $query with the acting user's
     * specific edge to the rows it would otherwise return, beyond tenant
     * membership. Dispatches on the acting user's role — 'teacher' joins
     * against class_teacher_assignments / class_teacher_id (F18's fix),
     * 'parent' joins against student_parent_links generalizing
     * ChildrenController::showChildren()'s intersect-before-query pattern
     * (F20's fix), 'student' is self-only per 16's corrections section.
     * Every other role (school_admin, super_admin) is tenant-scope-only
     * for Phase 1 — the "default branch" every admin controller in this
     * bundle's doc comments refers to — and returns $query unchanged,
     * since tenantScope() has already run and is the only check that
     * role needs.
     *
     * $wideVisibilityRoles (15-academic-domain-map.md §4/§5): an optional,
     * caller-supplied list of role keys for which the relationship
     * narrowing below is skipped entirely, modeled directly on the
     * reference codebase's correct `!Auth::user()->hasRole('principal')`
     * branch in `LessonPlanController::index()`. This is *broadening*,
     * not bypassing: tenantScope() has already run on $query before this
     * method ever sees it (see this class's doc comment / every call
     * site in this bundle), so a role in this list still only ever sees
     * "every row this tenant scope alone lets through" — never another
     * school's rows. The list is deliberately empty by default and
     * supplied by the caller rather than hardcoded here, so a future
     * resource type (e.g. a Lesson Plan controller giving 'principal'
     * wide visibility) can define its own wide-visibility roles without
     * this method's core dispatch logic changing again — the "add a
     * role, don't touch the service" property Ground Rule 0 (12/13/14)
     * already establishes for tenantScope()'s super_admin exemption.
     *
     * @param  list<string>  $wideVisibilityRoles
     */
    public function relationshipScope(Builder $query, User $user, string $modelClass, array $wideVisibilityRoles = []): Builder
    {
        $roleKey = $user->role->key ?? null;

        if ($roleKey !== null && in_array($roleKey, $wideVisibilityRoles, true)) {
            return $query;
        }

        return match ($roleKey) {
            'teacher' => $this->teacherRelationshipScope($query, $user, $modelClass),
            'parent' => $this->parentRelationshipScope($query, $user, $modelClass),
            'student' => $this->studentRelationshipScope($query, $user, $modelClass),
            default => $query,
        };
    }

    /**
     * F18's fix: a teacher may only see ClassSection rows (or rows of any
     * other model carrying a class_section_id column, e.g.
     * StudentEnrollment) whose class_section_id has a
     * class_teacher_assignments row for this teacher, or whose
     * class_teacher_id is this teacher (the homeroom relationship, D3).
     *
     * Originally scoped to what Phase 1–3 had models for (ClassSection,
     * and anything joining through class_section_id). Phase 4
     * (17-schoolos-academic-domain-map.md §3) added the assignment_id/
     * exam_id branches below for AssignmentSubmission/ExamMark — the two
     * new resource types that don't carry class_section_id directly (a
     * submission/mark belongs to a student, not a section) and so need
     * one extra join, through their parent Assignment/Exam, to reach the
     * same class_section_id this method already resolves teacher access
     * against. This is the direct schema/scope-level fix for F29 (any
     * teacher, any school, could grade any student's submission).
     */
    private function teacherRelationshipScope(Builder $query, User $teacher, string $modelClass): Builder
    {
        $assignedSectionIds = ClassTeacherAssignment::where('teacher_id', $teacher->id)
            ->pluck('class_section_id')
            ->merge(
                ClassSection::where('class_teacher_id', $teacher->id)->pluck('id')
            )
            ->unique();

        if ($modelClass === ClassSection::class) {
            return $query->whereIn('id', $assignedSectionIds);
        }

        $table = (new $modelClass())->getTable();

        if (Schema::hasColumn($table, 'class_section_id')) {
            return $query->whereIn('class_section_id', $assignedSectionIds);
        }

        // AssignmentSubmission-shaped: no class_section_id of its own,
        // but its parent Assignment has one. Join through assignment_id
        // rather than trying to teach this method about student_id for
        // the teacher case — teachers have no student-relationship table
        // to join against (student_parent_links is parent-only, 13 §3),
        // so the section-membership check has to happen one level up,
        // through the parent resource.
        if (Schema::hasColumn($table, 'assignment_id')) {
            return $query->whereIn(
                'assignment_id',
                Assignment::whereIn('class_section_id', $assignedSectionIds)->pluck('id')
            );
        }

        // ExamMark-shaped: same join, through Exam instead of Assignment.
        if (Schema::hasColumn($table, 'exam_id')) {
            return $query->whereIn(
                'exam_id',
                Exam::whereIn('class_section_id', $assignedSectionIds)->pluck('id')
            );
        }

        // Model shape this role has no defined join for — deny by
        // returning an always-empty query rather than the unscoped one.
        return $query->whereRaw('1 = 0');
    }

    /**
     * F20's fix, generalizing ChildrenController::showChildren()'s
     * intersect-before-query pattern: a parent may only see the User row
     * that is their own, or User rows with an active student_parent_links
     * row linking them to that student — or, for any other model
     * carrying a student_id column (e.g. StudentEnrollment), rows whose
     * student_id is one of those linked children's. An unlink()'d
     * (inactive) link does not grant access.
     *
     * class_section_id branch (17-schoolos-academic-domain-map.md §3,
     * closing the gap Phase4TestGateTest's Row 3 doc comment flagged):
     * for a model carrying class_section_id but no student_id of its own
     * (TimetableSlot, LessonPlan — Assignment/Exam are covered too, but
     * school_admin/teacher are the only roles that write those directly)
     * a linked parent's visibility is resolved through their linked
     * children's current StudentEnrollment rows, the same join style
     * teacherRelationshipScope() already uses for its assignment_id/
     * exam_id branches. This only runs when the model has no student_id
     * column — a model with both columns (none currently exist) would
     * still take the more specific student_id branch above.
     */
    private function parentRelationshipScope(Builder $query, User $parent, string $modelClass): Builder
    {
        $linkedStudentIds = StudentParentLink::where('parent_id', $parent->id)
            ->where('status', 'active')
            ->pluck('student_id');

        if ($modelClass === User::class) {
            return $query->where(function (Builder $q) use ($parent, $linkedStudentIds) {
                $q->where('id', $parent->id)
                    ->orWhereIn('id', $linkedStudentIds);
            });
        }

        $table = (new $modelClass())->getTable();

        if (Schema::hasColumn($table, 'student_id')) {
            return $query->whereIn('student_id', $linkedStudentIds);
        }

        if (Schema::hasColumn($table, 'class_section_id')) {
            return $query->whereIn(
                'class_section_id',
                StudentEnrollment::whereIn('student_id', $linkedStudentIds)->pluck('class_section_id')
            );
        }

        return $query->whereRaw('1 = 0');
    }

    /**
     * Self-only, per 16-schoolos-decisions-register.md's corrections
     * section (14 §8: "dedicated namespace, not shared" — students don't
     * get the parent-facing self-or-linked-children surface). A student
     * may only see the User row that is their own, or — for any other
     * model carrying a student_id column — rows whose student_id is
     * their own id.
     *
     * class_section_id branch: same gap and same fix as
     * parentRelationshipScope()'s (see that method's doc comment) — a
     * model with no student_id of its own but a class_section_id is
     * resolved through the student's own current StudentEnrollment
     * row(s) rather than denied outright.
     */
    private function studentRelationshipScope(Builder $query, User $student, string $modelClass): Builder
    {
        if ($modelClass === User::class) {
            return $query->where('id', $student->id);
        }

        $table = (new $modelClass())->getTable();

        if (Schema::hasColumn($table, 'student_id')) {
            return $query->where('student_id', $student->id);
        }

        if (Schema::hasColumn($table, 'class_section_id')) {
            return $query->whereIn(
                'class_section_id',
                StudentEnrollment::where('student_id', $student->id)->pluck('class_section_id')
            );
        }

        return $query->whereRaw('1 = 0');
    }

    /**
     * Design ref: 20-phase-6-8-execution-prompt.md §4 (Track 6b).
     *
     * Dedicated to StaffAttendanceRecord (and any future user_id-keyed,
     * staff-facing resource with the same "admin sees all, everyone else
     * sees only their own" shape) — deliberately a new method rather than
     * a new branch inside relationshipScope()'s dispatch. That method's
     * existing branches (teacher/parent/student) each resolve a specific
     * ownership *relationship* (class assignment, linked child, self);
     * "any staff role that isn't an admin role sees only its own user_id"
     * is a different, simpler shape that doesn't fit that dispatch
     * without either hardcoding every current and future staff role key
     * into relationshipScope() itself, or silently changing what its
     * existing default branch (currently unrestricted-within-tenant) means
     * for school_admin/super_admin on every model it's ever called
     * against — this class's own doc comment already establishes "add a
     * method, don't touch what's shared" as the extensibility rule
     * (relationshipScope()'s $wideVisibilityRoles parameter is the same
     * principle applied in the opposite, broadening direction).
     *
     * $adminRoleKeys defaults to school_admin/super_admin — the two roles
     * D-register/ScopeService's own doc comment already treat as
     * tenant-scope-only "sees everything in the tenant" by convention
     * (relationshipScope()'s default branch). Every other role key,
     * including ones this schema hasn't seeded yet, is self-scoped by
     * default — new staff-type roles need no change here to get the safe
     * (self-only) behavior, matching D4's "roles are global vocabulary"
     * framing: a role this method has never heard of is narrowed, not
     * exempted.
     *
     * @param  list<string>  $adminRoleKeys
     */
    public function staffSelfScope(Builder $query, User $user, array $adminRoleKeys = ['school_admin', 'super_admin']): Builder
    {
        $roleKey = $user->role->key ?? null;

        if ($roleKey !== null && in_array($roleKey, $adminRoleKeys, true)) {
            return $query;
        }

        return $query->where('user_id', $user->id);
    }
}
