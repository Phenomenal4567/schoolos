<?php

namespace App\Http\Controllers\TeacherPortal;

use App\Http\Controllers\Controller;
use App\Repositories\CalendarEventRepository;
use App\Services\ScopeService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CalendarEventController extends Controller
{
    public function index(Request $request, ScopeService $scope, CalendarEventRepository $repository): View
    {
        $events = $repository->visibleTo($request->user(), $scope);

        return view('teacher.calendar-events.index', ['events' => $events]);
    }

    public function show(Request $request, ScopeService $scope, CalendarEventRepository $repository, int $calendarEvent): View
    {
        $event = $repository->findVisibleTo($calendarEvent, $request->user(), $scope);

        if ($event === null) {
            abort(404);
        }

        return view('teacher.calendar-events.show', ['event' => $event]);
    }
}
