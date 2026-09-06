<?php

namespace App\Http\Controllers\StudentPortal;

use App\Http\Controllers\Controller;
use App\Models\AttendanceRecord;
use App\Models\User;
use App\Services\ScopeService;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Design ref: this session's own task doc ("SchoolOS — Student Portal
 * (read-only: own profile + own attendance)"), closing the gap that doc
 * identifies: 16-schoolos-decisions-register.md's corrections section and
 * ScopeService::studentRelationshipScope()'s own doc comment already
 * establish that a student gets a dedicated, self-only surface distinct
 * from ParentPortal — this controller is that surface's first
 * implementation.
 *
 * Scoped identically to what ParentPortal\DashboardController::showChild()
 * and childAttendance() already expose about a linked child (profile:
 * name, email, mobile_no, registration_number, status; attendance: full
 * history, most recent first), except there is no route parameter and no
 * "is this the right student" resolution step at all — the acting user's
 * own id is the record, by construction of studentRelationshipScope().
 *
 * Both actions are id-less, read-only listings of the acting user's own
 * data, the same shape as ParentPortal\DashboardController::index() and
 * TeacherPortal\DashboardController::index() — neither of those carries
 * 'scope.checked' either, since that middleware (and Phase1TestGateTest's
 * route-table lint) only concerns routes that resolve an *existing record
 * by a route parameter*; there is no such parameter here to check.
 */
class DashboardController extends Controller
{
    /**
     * The acting student's own profile — name, email, mobile_no,
     * registration_number, status. Same fields as parent/child.blade.php
     * shows for a linked child; no enrollment/class data, no
     * StudentHealthProfile data, matching ParentPortal\DashboardController's
     * own doc comment on why that data stays out until Phase 4/5's access
     * policy exists.
     *
     * Deliberately still routed through
     * relationshipScope(tenantScope(...), $actor, User::class)->find($actor->id)
     * rather than just returning $actor directly. studentRelationshipScope()
     * on User::class is self-only by construction, so the two are
     * equivalent in practice today — but going through ScopeService keeps
     * this controller honest to the one pattern every other controller in
     * this bundle uses (never fetch-then-check, always intersect the
     * scope into the query first), so a future change to that scope
     * definition is guaranteed to apply here too without this controller
     * needing to change.
     */
    public function index(Request $request, ScopeService $scope): View
    {
        $actor = $request->user();

        $profile = $scope
            ->relationshipScope(
                $scope->tenantScope(User::query(), $actor),
                $actor,
                User::class
            )
            ->findOrFail($actor->id);

        return view('student.dashboard', ['profile' => $profile]);
    }

    /**
     * The acting student's own attendance history, most recent first.
     * relationshipScope() against AttendanceRecord already constrains this
     * to rows whose student_id is the actor's own id (see
     * ScopeService::studentRelationshipScope()'s doc comment) — unlike
     * childAttendance()'s ->where('student_id', $child->id), there is no
     * separate defense-in-depth filter to add here, because there is no
     * second, independently-resolved id to cross-check against: $actor->id
     * *is* the only id involved anywhere in this action.
     */
    public function attendance(Request $request, ScopeService $scope): View
    {
        $actor = $request->user();

        $records = $scope
            ->relationshipScope(AttendanceRecord::query(), $actor, AttendanceRecord::class)
            ->orderByDesc('date')
            ->get();

        return view('student.attendance', ['profile' => $actor, 'records' => $records]);
    }
}
