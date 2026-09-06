<?php

namespace App\Http\Controllers\TeacherPortal;

use App\Http\Controllers\Concerns\ResolvesScopedAcademicResource;
use App\Http\Controllers\Controller;
use App\Models\TimetableSlot;
use App\Services\ScopeService;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Design ref: 17-schoolos-academic-domain-map.md §3, §5, §6
 * Timetable Management module (School Admin build/publish requirement).
 *
 * Read-only, per the Timetable Management module's decision that the
 * timetable is built and owned by the admin (or a delegated staff
 * member — see EnsureCanManageTimetable), not by individual teachers.
 * This controller previously exposed store() so a teacher could book
 * their own slots directly; that write path (and its /teacher/timetable
 * POST route) has been removed in favor of Admin\TimetableController's
 * full CRUD + conflict-detection surface. index()/show() are unchanged.
 */
class TimetableController extends Controller
{
    use ResolvesScopedAcademicResource;

    public function index(Request $request, ScopeService $scope): View
    {
        $slots = $this->scopedIndex($request, $scope, TimetableSlot::class, ['classSection.standard', 'classSection.section', 'subject', 'teacher']);

        return view('teacher.timetable.index', ['slots' => $slots]);
    }

    /**
     * scope.checked (route-level, see routes/web.php) — resolves one row
     * through the identical tenantScope()->relationshipScope()
     * intersection index() uses, narrowed to $timetableSlot. 404-not-403
     * discipline: a teacher supplying a slot id outside their assigned
     * sections gets the same response as a nonexistent id, matching
     * AttendanceController::show()'s own doc comment.
     */
    public function show(Request $request, ScopeService $scope, int $timetableSlot): View
    {
        $slot = $this->scopedFind($request, $scope, TimetableSlot::class, $timetableSlot, ['classSection.standard', 'classSection.section', 'subject', 'teacher']);

        if ($slot === null) {
            abort(404);
        }

        return view('teacher.timetable.show', ['slot' => $slot]);
    }
}
