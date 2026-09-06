<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\ClassSection;
use App\Models\School;
use App\Models\Subject;
use App\Models\TimetablePeriod;
use App\Models\TimetablePublication;
use App\Models\TimetableSlot;
use App\Models\User;
use App\Repositories\TimetableRepository;
use App\Services\ScopeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Design ref: Timetable Management module (School Admin: build the
 * timetable, assign teachers/subjects/rooms to slots, detect conflicts,
 * preview before publishing, publish/unpublish, delegate to staff).
 *
 * Reachable by school_admin or any staff member delegated via
 * staff_profiles.can_manage_timetable (route-level 'timetable.manage'
 * middleware — see EnsureCanManageTimetable's own doc comment). Unlike
 * the rest of the admin.* group, these routes are NOT gated to
 * 'role:school_admin' alone for that reason (see routes/web.php's own
 * admin.timetable.* group).
 *
 * index()/show() are unchanged in shape from the read-only controller
 * this replaces. create()/store()/edit()/update()/destroy() are new —
 * the admin write path this module was missing. settings()/
 * updateSettings() own the period-grid + working-days screen. publish()/
 * unpublish() flip the one TimetablePublication header row for the
 * school's current academic year.
 */
class TimetableController extends Controller
{
    public function index(Request $request, ScopeService $scope): View
    {
        $actor = $request->user();

        $slots = $scope
            ->tenantScope(TimetableSlot::query(), $actor)
            ->with(['classSection.standard', 'classSection.section', 'subject', 'teacher'])
            ->get();

        $currentYear = AcademicYear::where('school_id', $actor->school_id)->where('is_current', true)->first();

        $publication = $currentYear
            ? TimetablePublication::where('school_id', $actor->school_id)
                ->where('academic_year_id', $currentYear->id)
                ->first()
            : null;

        return view('admin.timetable.index', [
            'slots' => $slots,
            'currentYear' => $currentYear,
            'isPublished' => (bool) $publication?->is_published,
        ]);
    }

    public function show(Request $request, ScopeService $scope, int $timetableSlot): View
    {
        $actor = $request->user();

        $slot = $scope
            ->tenantScope(TimetableSlot::query(), $actor)
            ->with(['classSection.standard', 'classSection.section', 'subject', 'teacher'])
            ->findOrFail($timetableSlot);

        return view('admin.timetable.show', ['slot' => $slot]);
    }

    public function create(Request $request): View
    {
        return view('admin.timetable.create', $this->formOptions($request));
    }

    public function store(Request $request, TimetableRepository $repository): RedirectResponse
    {
        $actor = $request->user();
        $data = $this->validated($request);

        try {
            $repository->create(
                $actor->school_id,
                $data['class_section_id'],
                $data['subject_id'],
                $data['teacher_id'],
                $data['day_of_week'],
                $data['period_number'],
                $data['room'] ?? null,
            );
        } catch (\InvalidArgumentException $e) {
            return back()->withErrors(['class_section_id' => $e->getMessage()])->withInput();
        }

        return redirect()->route('admin.timetable.index')->with('status', 'Timetable slot added.');
    }

    public function edit(Request $request, ScopeService $scope, int $timetableSlot): View
    {
        $actor = $request->user();

        $slot = $scope->tenantScope(TimetableSlot::query(), $actor)->findOrFail($timetableSlot);

        return view('admin.timetable.edit', $this->formOptions($request) + ['slot' => $slot]);
    }

    public function update(Request $request, ScopeService $scope, TimetableRepository $repository, int $timetableSlot): RedirectResponse
    {
        $actor = $request->user();
        $slot = $scope->tenantScope(TimetableSlot::query(), $actor)->findOrFail($timetableSlot);
        $data = $this->validated($request);

        try {
            $repository->update(
                $slot,
                $data['class_section_id'],
                $data['subject_id'],
                $data['teacher_id'],
                $data['day_of_week'],
                $data['period_number'],
                $data['room'] ?? null,
            );
        } catch (\InvalidArgumentException $e) {
            return back()->withErrors(['class_section_id' => $e->getMessage()])->withInput();
        }

        return redirect()->route('admin.timetable.index')->with('status', 'Timetable slot updated.');
    }

    public function destroy(Request $request, ScopeService $scope, TimetableRepository $repository, int $timetableSlot): RedirectResponse
    {
        $actor = $request->user();
        $slot = $scope->tenantScope(TimetableSlot::query(), $actor)->findOrFail($timetableSlot);

        $repository->delete($slot);

        return redirect()->route('admin.timetable.index')->with('status', 'Timetable slot removed.');
    }

    /**
     * "Configure period start/end times" / "Select the school days."
     */
    public function settings(Request $request): View
    {
        $actor = $request->user();

        $periods = TimetablePeriod::where('school_id', $actor->school_id)
            ->orderBy('period_number')
            ->get();

        $school = School::findOrFail($actor->school_id);

        return view('admin.timetable.settings', [
            'periods' => $periods,
            'workingDays' => $school->working_days ?? ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'],
        ]);
    }

    public function updateSettings(Request $request, TimetableRepository $repository): RedirectResponse
    {
        $actor = $request->user();

        $data = $request->validate([
            'periods' => ['required', 'array', 'min:1'],
            'periods.*.period_number' => ['required', 'integer', 'min:1', 'distinct'],
            'periods.*.start_time' => ['required', 'date_format:H:i'],
            'periods.*.end_time' => ['required', 'date_format:H:i', 'after:periods.*.start_time'],
            'working_days' => ['required', 'array', 'min:1'],
            'working_days.*' => [Rule::in(['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'])],
        ]);

        $repository->replaceSettings($actor->school_id, $data['periods'], $data['working_days']);

        return back()->with('status', 'Timetable settings saved.');
    }

    public function publish(Request $request, TimetableRepository $repository): RedirectResponse
    {
        $actor = $request->user();
        $currentYear = AcademicYear::where('school_id', $actor->school_id)->where('is_current', true)->first();

        if ($currentYear === null) {
            return back()->withErrors(['academic_year' => 'No current academic year is configured for this school.']);
        }

        $repository->publish($actor->school_id, $currentYear->id, $actor);

        return back()->with('status', 'Timetable published.');
    }

    public function unpublish(Request $request, TimetableRepository $repository): RedirectResponse
    {
        $actor = $request->user();
        $currentYear = AcademicYear::where('school_id', $actor->school_id)->where('is_current', true)->first();

        if ($currentYear === null) {
            return back()->withErrors(['academic_year' => 'No current academic year is configured for this school.']);
        }

        $repository->unpublish($actor->school_id, $currentYear->id);

        return back()->with('status', 'Timetable unpublished.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        return $request->validate([
            'class_section_id' => ['required', 'integer'],
            'subject_id' => ['required', 'integer'],
            'teacher_id' => ['required', 'integer'],
            'day_of_week' => ['required', Rule::in(['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'])],
            'period_number' => ['required', 'integer', 'min:1'],
            'room' => ['nullable', 'string', 'max:255'],
        ]);
    }

    /**
     * Shared select-option data for the create/edit forms — every
     * class_section, subject, and teacher belonging to the actor's own
     * school (no relationshipScope() narrowing: an admin/delegated
     * staff member building the whole school's timetable needs every
     * section and teacher in scope, not just their own assigned ones).
     *
     * @return array<string, mixed>
     */
    private function formOptions(Request $request): array
    {
        $actor = $request->user();

        return [
            'classSections' => ClassSection::where('school_id', $actor->school_id)
                ->with(['standard', 'section'])
                ->get(),
            'subjects' => Subject::where('school_id', $actor->school_id)->orderBy('name')->get(),
            'teachers' => User::where('school_id', $actor->school_id)
                ->whereHas('role', fn ($q) => $q->where('key', 'teacher'))
                ->orderBy('name')
                ->get(),
        ];
    }
}
