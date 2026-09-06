<?php

namespace App\Http\Controllers\TeacherPortal;

use App\Http\Controllers\Controller;
use App\Models\ClassSection;
use App\Services\ScopeService;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Design ref: 12-schoolos-architecture.md §3a, 14-schoolos-implementation-plan.md §4
 *
 * Teacher-facing landing page, the same role the SessionController's own
 * doc comment on dashboardPathFor() describes as "falls through to '/'
 * ... until Phase 2+ builds their dashboards" — this is that dashboard.
 * Deliberately index()-only for now: it lists what a teacher can already
 * reach (their assigned class sections, via Attendance), nothing else,
 * since Phase 4 (homework/exams/etc.) is explicitly out of scope for this
 * pass. Follows ParentPortal\DashboardController's own precedent —
 * relationshipScope() intersected before the query runs, no route
 * parameter here so no 'scope.checked' marker is needed (matching that
 * class's index() method, not its by-ID actions).
 */
class DashboardController extends Controller
{
    public function index(Request $request, ScopeService $scope): View
    {
        $actor = $request->user();

        $classSectionCount = $scope
            ->relationshipScope(
                $scope->tenantScope(ClassSection::query(), $actor),
                $actor,
                ClassSection::class
            )
            ->count();

        return view('teacher.dashboard', [
            'classSectionCount' => $classSectionCount,
        ]);
    }
}
