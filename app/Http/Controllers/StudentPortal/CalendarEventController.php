<?php

namespace App\Http\Controllers\StudentPortal;

use App\Http\Controllers\Controller;
use App\Repositories\CalendarEventRepository;
use App\Services\ScopeService;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Design ref: 19-discovery-hierarchy-gap-closure-plan.md §3,
 * 20-phase-6-8-execution-prompt.md §1.
 *
 * Read-only. Every calendar_events row in this student's tenant with
 * visible_to_parents = true, via CalendarEventRepository::visibleTo() —
 * tenant scope only, no relationship narrowing (see CalendarEvent's own
 * doc comment for why). No store() — students never author calendar
 * events.
 */
class CalendarEventController extends Controller
{
    public function index(Request $request, ScopeService $scope, CalendarEventRepository $repository): View
    {
        $events = $repository->visibleTo($request->user(), $scope);

        return view('student.calendar-events.index', ['events' => $events]);
    }

    public function show(Request $request, ScopeService $scope, CalendarEventRepository $repository, int $calendarEvent): View
    {
        $event = $repository->findVisibleTo($calendarEvent, $request->user(), $scope);

        if ($event === null) {
            abort(404);
        }

        return view('student.calendar-events.show', ['event' => $event]);
    }
}
