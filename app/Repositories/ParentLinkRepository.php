<?php

namespace App\Repositories;

use App\Exceptions\ParentLink\InvalidParentLinkTargetFailure;
use App\Models\StudentParentLink;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * Design ref: 13-schoolos-database-schema-v2.md §3
 * Decision ref: 14-schoolos-implementation-plan.md §2
 *
 * Reconstructed from ParentLinkController's doc comments and the shape
 * ClassTeacherAssignmentRepository::assign() explicitly says it follows
 * ("the same shape ParentLinkRepository::link() established"). Neither
 * of those doc comments describes this file's exact contents, so this is
 * a best-effort match to the established pattern, not a recovered
 * original — confirm against whatever already lives at this path in the
 * real project before trusting it over that version.
 *
 * Admin-initiated, not self-service (per ParentLinkController's own doc
 * comment — there is deliberately no parent-facing self-linking route
 * anywhere in this codebase), writing to student_parent_links only —
 * there is no second mechanism to keep in sync, unlike GegoK12's ref_id
 * duplication (08-parent-domain-map.md §3 / 13 §3's "explicitly not
 * carried over" note), so there's nothing here that can drift out of
 * sync with itself. Deliberately does not audit-log, matching
 * ClassTeacherAssignmentRepository::assign()'s own doc comment on why
 * neither of these two closest structural analogs do.
 */
class ParentLinkRepository
{
    /**
     * Create (or reactivate) the student_parent_links row for
     * (parent_id, student_id). Verifies both target users' roles before
     * writing: $parentId must resolve to 'parent', $studentId to
     * 'student'.
     *
     * Same idempotent shape ClassTeacherAssignmentRepository::assign()
     * says it follows: a duplicate link() call for an already-active
     * pair is a no-op, matching a double-submitted admin form. Unlike
     * assign(), this table has a status column (13 §3) — a link()
     * call against a pair whose existing row is 'inactive' (previously
     * unlink()'d) reactivates it rather than erroring on the UNIQUE
     * (parent_id, student_id) constraint, since that constraint applies
     * regardless of status and student_parent_links is the only path
     * for this relationship (13 §3's "no second, competing mechanism").
     *
     * @throws InvalidParentLinkTargetFailure if $parentId's role does
     *         not resolve to 'parent', or $studentId's role does not
     *         resolve to 'student'. Thrown before the transaction opens,
     *         matching the other repositories' gates.
     */
    public function link(int $schoolId, int $parentId, int $studentId, User $actor): StudentParentLink
    {
        $parent = $this->assertHasRole($parentId, 'parent');
        $student = $this->assertHasRole($studentId, 'student');

        return DB::transaction(function () use ($schoolId, $parent, $student) {
            // lockForUpdate: two concurrent link() calls for the same
            // pair (e.g. a double-submitted admin form) must not both
            // pass the "does a row exist" check and then both attempt an
            // insert — same race ClassTeacherAssignmentRepository::
            // assign() guards against.
            $existing = StudentParentLink::where('parent_id', $parent->id)
                ->where('student_id', $student->id)
                ->lockForUpdate()
                ->first();

            if ($existing) {
                if ($existing->status !== 'active') {
                    $existing->status = 'active';
                    $existing->save();
                }

                return $existing;
            }

            try {
                return StudentParentLink::create([
                    'school_id' => $schoolId,
                    'parent_id' => $parent->id,
                    'student_id' => $student->id,
                    'status' => 'active',
                ]);
            } catch (QueryException $e) {
                if ($e->getCode() !== '23000') {
                    throw $e;
                }

                // Lost the race: another concurrent call inserted the
                // row between our lockForUpdate() finding nothing and
                // our own insert. Re-fetch, reactivate if needed, and
                // return it — same "already-true fact" outcome as the
                // $existing branch above.
                $link = StudentParentLink::where('parent_id', $parent->id)
                    ->where('student_id', $student->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                if ($link->status !== 'active') {
                    $link->status = 'active';
                    $link->save();
                }

                return $link;
            }
        });
    }

    /**
     * Design ref: SchoolOS Account Creation & Onboarding plan, §4 (Parent
     * onboarding)
     *
     * The self-service counterpart to link() — creates a 'pending' row
     * instead of 'active'. Deliberately does NOT reuse link()'s body with
     * a parameterized status: link() reactivates an existing 'inactive'
     * row straight to 'active' and is meant to be trusted that way
     * because every caller of link() is admin-facing (per this class's
     * own doc comment); request() must never silently promote an existing
     * row to 'active' on a parent's say-so, so an existing row of ANY
     * status (active, inactive, or already-pending) short-circuits to a
     * no-op here rather than being touched. Only
     * Admin\ParentLinkController::approve() — which calls link(), not
     * this method — ever moves a row to 'active'. Same role-gate and
     * lockForUpdate()-then-insert-or-refetch shape as link() otherwise.
     *
     * @throws InvalidParentLinkTargetFailure if $parentId's role does not
     *         resolve to 'parent', or $studentId's role does not resolve
     *         to 'student'.
     */
    public function request(int $schoolId, int $parentId, int $studentId, User $actor): StudentParentLink
    {
        $parent = $this->assertHasRole($parentId, 'parent');
        $student = $this->assertHasRole($studentId, 'student');

        return DB::transaction(function () use ($schoolId, $parent, $student) {
            $existing = StudentParentLink::where('parent_id', $parent->id)
                ->where('student_id', $student->id)
                ->lockForUpdate()
                ->first();

            if ($existing) {
                return $existing;
            }

            try {
                return StudentParentLink::create([
                    'school_id' => $schoolId,
                    'parent_id' => $parent->id,
                    'student_id' => $student->id,
                    'status' => 'pending',
                ]);
            } catch (QueryException $e) {
                if ($e->getCode() !== '23000') {
                    throw $e;
                }

                return StudentParentLink::where('parent_id', $parent->id)
                    ->where('student_id', $student->id)
                    ->lockForUpdate()
                    ->firstOrFail();
            }
        });
    }

    /**
     * Deletes a still-'pending' row outright rather than marking it
     * 'inactive' — unlike unlink() (which soft-unlinks a link that really
     * was active at some point, worth keeping a trace of), a rejected
     * request was never granted access to anything, so there is no state
     * worth preserving. A future request() call for the same pair simply
     * creates a fresh row rather than needing to "reactivate" this one.
     *
     * Same bare-$linkId-no-school-parameter contract as unlink() — the
     * caller (Admin\ParentLinkController::reject()) is responsible for
     * resolving {parentLink} through ScopeService::tenantScope() first.
     */
    public function reject(int $linkId): void
    {
        DB::transaction(function () use ($linkId) {
            $link = StudentParentLink::lockForUpdate()->findOrFail($linkId);

            if ($link->status === 'pending') {
                $link->delete();
            }
        });
    }

    /**
     * Soft-unlink: sets status to 'inactive' rather than deleting the
     * row, so a later link() call for the same pair reactivates it
     * instead of colliding with UNIQUE(parent_id, student_id) — matching
     * ClassTeacherAssignmentRepository's own doc comment describing why
     * this table (unlike class_teacher_assignments) supports an
     * unassign-equivalent at all: it has a status column to do it with.
     *
     * Takes only a bare $linkId with no school parameter — per
     * ParentLinkController::destroy()'s own doc comment, the caller is
     * responsible for resolving {parentLink} through
     * ScopeService::tenantScope() before calling this, so a link
     * belonging to another school never reaches here in the first place.
     */
    public function unlink(int $linkId): void
    {
        DB::transaction(function () use ($linkId) {
            $link = StudentParentLink::lockForUpdate()->findOrFail($linkId);
            $link->status = 'inactive';
            $link->save();
        });
    }

    /**
     * Shared role gate for both sides of a link() call. Mirrors
     * ClassSectionRepository::assertIsTeacher()'s shape, generalized to
     * the role being checked since this repository checks two different
     * roles rather than one.
     *
     * @throws InvalidParentLinkTargetFailure if the target user's role
     *         does not resolve to $expectedRole.
     */
    private function assertHasRole(int $userId, string $expectedRole): User
    {
        $targetUser = User::with('role')->findOrFail($userId);

        if (($targetUser->role->key ?? null) !== $expectedRole) {
            throw new InvalidParentLinkTargetFailure($targetUser, $expectedRole);
        }

        return $targetUser;
    }
}
