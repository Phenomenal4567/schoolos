<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CalendarEvent;
use App\Repositories\CalendarEventRepository;
use App\Services\ScopeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Design ref: 19-discovery-hierarchy-gap-closure-plan.md §3,
 * 20-phase-6-8-execution-prompt.md §1.
 *
 * school_admin's authoring surface: store/update/destroy, matching this
 * bundle's admin-write-path-focused convention (no index/show here —
 * every existing Admin/* controller with a mutable resource follows the
 * same shape, e.g. ParentLinkController's store/destroy pair; a full
 * admin read UI wasn't built for this pass for the same reason
 * Admin\AnnouncementController's doc comment gives for its own
 * store-only surface). RedirectResponse + back()->with(...)/withErrors()
 * throughout, matching the majority Blade-form convention this bundle
 * uses everywhere except Announcement's JSON-only surface.
 *
 * update()/destroy() resolve an existing calendar_events row by route
 * parameter, so both carry 'scope.checked' at the route table and
 * resolve {calendarEvent} through ScopeService::tenantScope() here
 * before doing anything else with it — a wrong-tenant id 404s rather
 * than reaching the repository at all, same discipline as
 * ParentLinkController::destroy().
 */
class CalendarEventController extends Controller
{
    public function store(Request $request, CalendarEventRepository $repository): RedirectResponse
    {
        $actor = $request->user();
        $data = $this->validated($request);

        try {
            $repository->create($actor->school_id, $actor, $data);
        } catch (\InvalidArgumentException $e) {
            return back()->withErrors(['calendar_event' => $e->getMessage()])->withInput();
        }

        return back()->with('status', 'Calendar event created.');
    }

    public function update(
        Request $request,
        ScopeService $scope,
        CalendarEventRepository $repository,
        int $calendarEvent
    ): RedirectResponse {
        $actor = $request->user();
        $event = $scope->tenantScope(CalendarEvent::query(), $actor)->findOrFail($calendarEvent);
        $data = $this->validated($request);

        try {
            $repository->update($event, $actor, $data);
        } catch (\InvalidArgumentException $e) {
            return back()->withErrors(['calendar_event' => $e->getMessage()])->withInput();
        }

        return back()->with('status', 'Calendar event updated.');
    }

    public function destroy(
        Request $request,
        ScopeService $scope,
        CalendarEventRepository $repository,
        int $calendarEvent
    ): RedirectResponse {
        $actor = $request->user();
        $event = $scope->tenantScope(CalendarEvent::query(), $actor)->findOrFail($calendarEvent);

        $repository->delete($event, $actor);

        return back()->with('status', 'Calendar event removed.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'academic_year_id' => ['required', 'integer'],
            'academic_term_id' => ['nullable', 'integer'],
            'title' => ['required', 'string', 'max:255'],
            'event_type' => [
                'required',
                'string',
                'in:term_date,mid_term_break,exam_period,activity,holiday,closing_date,other',
            ],
            'start_date' => ['required', 'date'],
            'end_date' => ['nullable', 'date'],
            'visible_to_parents' => ['nullable', 'boolean'],
        ]);
    }
}