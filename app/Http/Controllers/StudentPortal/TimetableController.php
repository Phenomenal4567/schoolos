<?php

namespace App\Http\Controllers\StudentPortal;

use App\Http\Controllers\Concerns\ResolvesScopedAcademicResource;
use App\Http\Controllers\Controller;
use App\Models\TimetableSlot;
use App\Services\ScopeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Design ref: 17-schoolos-academic-domain-map.md §3, §6
 *
 * Read-only counterpart to TeacherPortal\TimetableController — see
 * TeacherPortal\LessonPlanController's doc comment for the shape shared
 * across all four generic Phase 4 resources and all three portals. A
 * student sees their own class_section's timetable slots via
 * ScopeService::studentRelationshipScope()'s class_section_id branch
 * (17 §3, resolved via the student's own current StudentEnrollment
 * row(s)). No store() — students never author content for these
 * resources.
 */
class TimetableController extends Controller
{
    use ResolvesScopedAcademicResource;

    public function index(Request $request, ScopeService $scope): View
    {
        $slots = $this->scopedIndex($request, $scope, TimetableSlot::class, ['classSection.standard', 'classSection.section', 'subject', 'teacher']);

        return view('student.timetable.index', ['slots' => $slots]);
    }

    public function show(Request $request, ScopeService $scope, int $timetableSlot): View
    {
        $slot = $this->scopedFind($request, $scope, TimetableSlot::class, $timetableSlot, ['classSection.standard', 'classSection.section', 'subject', 'teacher']);

        if ($slot === null) {
            abort(404);
        }

        return view('student.timetable.show', ['slot' => $slot]);
    }
}
