<?php

namespace App\Http\Controllers\ParentPortal;

use App\Http\Controllers\Concerns\ResolvesScopedAcademicResource;
use App\Http\Controllers\Controller;
use App\Models\Assignment;
use App\Services\ScopeService;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Design ref: 17-schoolos-academic-domain-map.md §3, §6
 *
 * Read-only counterpart to TeacherPortal\AssignmentController — see
 * TeacherPortal\LessonPlanController's doc comment for the shape shared
 * across all four generic Phase 4 resources and all three portals.
 * Covers Assignment only, not AssignmentSubmission (a parent/student's
 * visibility into their own child's/own submissions is a distinct,
 * still-out-of-scope surface — see AssignmentRepository's own doc
 * comment). No store() — parents never author assignments.
 */
class AssignmentController extends Controller
{
    use ResolvesScopedAcademicResource;

    public function index(Request $request, ScopeService $scope): View
    {
        $assignments = $this->scopedIndex($request, $scope, Assignment::class, ['classSection.standard', 'classSection.section', 'subject', 'teacher']);

        return view('parent.assignments.index', ['assignments' => $assignments]);
    }

    public function show(Request $request, ScopeService $scope, int $assignment): View
    {
        $resolved = $this->scopedFind($request, $scope, Assignment::class, $assignment, ['classSection.standard', 'classSection.section', 'subject', 'teacher']);

        if ($resolved === null) {
            abort(404);
        }

        return view('parent.assignments.show', ['assignment' => $resolved]);
    }
}
