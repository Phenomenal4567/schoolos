<?php

namespace App\Http\Controllers\ParentPortal;

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
 * Read-only counterpart to TeacherPortal\TimetableController — see that
 * class's doc comment (via LessonPlanController's) for the shape shared
 * across all four generic Phase 4 resources and all three portals. A
 * parent sees their linked children's class_section(s)' timetable slots
 * via ScopeService::parentRelationshipScope()'s class_section_id branch
 * (17 §3) — no controller-level filtering beyond what
 * ResolvesScopedAcademicResource's tenantScope()->relationshipScope()
 * intersection already provides. No store() — parents never author
 * content for these four resources.
 */
class TimetableController extends Controller
{
    use ResolvesScopedAcademicResource;

    public function index(Request $request, ScopeService $scope): View
    {
        $slots = $this->scopedIndex($request, $scope, TimetableSlot::class, ['classSection.standard', 'classSection.section', 'subject', 'teacher']);

        return view('parent.timetable.index', ['slots' => $slots]);
    }

    /**
     * scope.checked (route-level, see routes/web.php). 404-not-403: an
     * unlinked parent (or a linked parent whose child isn't enrolled in
     * this slot's class_section) gets the same response as a
     * nonexistent slot id.
     */
    public function show(Request $request, ScopeService $scope, int $timetableSlot): View
    {
        $slot = $this->scopedFind($request, $scope, TimetableSlot::class, $timetableSlot, ['classSection.standard', 'classSection.section', 'subject', 'teacher']);

        if ($slot === null) {
            abort(404);
        }

        return view('parent.timetable.show', ['slot' => $slot]);
    }
}
