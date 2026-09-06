<?php

namespace App\Http\Controllers\StudentPortal;

use App\Http\Controllers\Concerns\ResolvesScopedAcademicResource;
use App\Http\Controllers\Controller;
use App\Models\LessonPlan;
use App\Services\ScopeService;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Design ref: 17-schoolos-academic-domain-map.md §3, §6
 *
 * Read-only counterpart to TeacherPortal\LessonPlanController — see that
 * class's doc comment for the shape shared across all four generic
 * Phase 4 resources and all three portals. No store() — students never
 * author lesson plans.
 */
class LessonPlanController extends Controller
{
    use ResolvesScopedAcademicResource;

    public function index(Request $request, ScopeService $scope): View
    {
        $lessonPlans = $this->scopedIndex($request, $scope, LessonPlan::class, ['classSection.standard', 'classSection.section', 'subject', 'teacher']);

        return view('student.lesson-plans.index', ['lessonPlans' => $lessonPlans]);
    }

    public function show(Request $request, ScopeService $scope, int $lessonPlan): View
    {
        $resolved = $this->scopedFind($request, $scope, LessonPlan::class, $lessonPlan, ['classSection.standard', 'classSection.section', 'subject', 'teacher']);

        if ($resolved === null) {
            abort(404);
        }

        return view('student.lesson-plans.show', ['lessonPlan' => $resolved]);
    }
}
