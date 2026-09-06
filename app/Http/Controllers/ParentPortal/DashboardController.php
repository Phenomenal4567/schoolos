<?php

namespace App\Http\Controllers\ParentPortal;

use App\Http\Controllers\Controller;
use App\Models\AttendanceRecord;
use App\Models\Role;
use App\Models\StudentParentLink;
use App\Models\User;
use App\Services\ScopeService;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Design ref: 16-schoolos-decisions-register.md (D-register, confirmed):
 * "Parent web dashboard: build it, scoped to attendance + announcements +
 * profile only in its first version." Attendance (Phase 3) landed with
 * childAttendance() below; announcements (Phase 5) still has no backing
 * table, so it stays out of this controller until that phase.
 *
 * Also intentionally excluded: StudentHealthProfile data — F14 (this
 * document's own open item list, and 14-schoolos-implementation-plan.md)
 * leaves that visibility/access-policy question undecided by design, so
 * it does not belong on a profile view until that policy exists.
 *
 * 12-schoolos-architecture.md §3a (ScopeService contract)
 *
 * Namespaced ParentPortal, not Parent — App\Http\Controllers\Parent as a
 * literal namespace segment reads confusingly next to PHP's own `parent::`
 * keyword and Eloquent's parent()/student() relation method names already
 * used on StudentParentLink; ParentPortal says the same thing without the
 * collision.
 *
 * First real controller in SchoolOS, and written to be the pattern Phase
 * 4's new endpoints copy (14 §4 flags that domain as needing the same
 * scrutiny this audit found missing). Every action below resolves records
 * by intersecting tenantScope() and relationshipScope() into the query
 * *before* it runs — never fetch-then-check — per ScopeService's own
 * doc comment on why that ordering matters. showChild() and
 * childAttendance() additionally carry the 'scope.checked' route
 * middleware so the F22 route-table lint (Phase1TestGateTest) can confirm
 * this at the route-table level too, not only by reading this file.
 */
class DashboardController extends Controller
{
    /**
     * List the acting parent's own linked children. relationshipScope()
     * against the User model already constrains this to rows reachable via
     * an active student_parent_links row for this parent (see
     * ScopeService::parentRelationshipScope()'s doc comment) — the
     * role_id filter below is defense-in-depth, not the actual boundary,
     * since only student rows should ever appear in student_parent_links
     * by construction of the parent-child linking write path.
     */
    public function index(Request $request, ScopeService $scope): View
    {
        $actor = $request->user();

        $children = $scope
            ->relationshipScope(
                $scope->tenantScope(User::query(), $actor),
                $actor,
                User::class
            )
            ->where('role_id', $this->studentRoleId())
            ->orderBy('name')
            ->get();

        // SchoolOS Account Creation & Onboarding plan, §4: the acting
        // parent's own not-yet-approved self-service requests
        // (ParentLinkRepository::request()) — deliberately a plain
        // ->where('parent_id', $actor->id) rather than routed through
        // ScopeService::relationshipScope(), since that method's own
        // parentRelationshipScope() branch resolves via *active* linked
        // children only (see its own doc comment) and would never surface
        // a 'pending' row at all; a parent still needs to see their own
        // pending requests to know a submission went through.
        $pendingRequests = StudentParentLink::where('parent_id', $actor->id)
            ->where('status', 'pending')
            ->with('student')
            ->get();

        return view('parent.dashboard', ['children' => $children, 'pendingRequests' => $pendingRequests]);
    }

    /**
     * A single child's basic profile — name, contact identifiers, status.
     * Deliberately not enrollment or class data; see this class's doc
     * comment. $student is a route parameter, not trusted input into the
     * query directly — findOrFail() runs against the already-scoped
     * query, so a $student id belonging to another parent's child (or
     * another school entirely) resolves to a 404, identically to
     * "doesn't exist," never a 403 that would confirm the id is valid for
     * someone else. This is the direct pattern F20 existed to close: no
     * student_id reaches a query unintersected against
     * student_parent_links.
     */
    public function showChild(Request $request, ScopeService $scope, int $student): View
    {
        $actor = $request->user();

        $child = $scope
            ->relationshipScope(
                $scope->tenantScope(User::query(), $actor),
                $actor,
                User::class
            )
            ->where('role_id', $this->studentRoleId())
            ->findOrFail($student);

        return view('parent.child', ['child' => $child]);
    }

    /**
     * A single child's attendance history, most recent first. Phase 3's
     * first widening of this controller beyond profile-only — see this
     * class's doc comment.
     *
     * Two intersected scope checks, deliberately not one: the first
     * (identical to showChild()'s) confirms $student really is this
     * parent's linked child — a 404 if not, same reasoning as showChild().
     * The second applies relationshipScope() to AttendanceRecord itself
     * (its student_id column is what makes that generic join work, see
     * ScopeService's own doc comment) and additionally constrains to
     * ->where('student_id', $student) — defense-in-depth once $student is
     * already confirmed above, not the actual boundary, matching index()'s
     * role_id filter precedent.
     */
    public function childAttendance(Request $request, ScopeService $scope, int $student): View
    {
        $actor = $request->user();

        $child = $scope
            ->relationshipScope(
                $scope->tenantScope(User::query(), $actor),
                $actor,
                User::class
            )
            ->where('role_id', $this->studentRoleId())
            ->findOrFail($student);

        $records = $scope
            ->relationshipScope(AttendanceRecord::query(), $actor, AttendanceRecord::class)
            ->where('student_id', $child->id)
            ->orderByDesc('date')
            ->get();

        return view('parent.attendance', ['child' => $child, 'records' => $records]);
    }

    protected function studentRoleId(): int
    {
        return Role::where('key', 'student')->value('id');
    }
}
