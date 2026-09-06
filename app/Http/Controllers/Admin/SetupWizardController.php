<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Design ref: SchoolOS Account Creation & Onboarding plan, §1 (School
 * Admin onboarding)
 *
 * Deliberately thin: every actual piece of school configuration (academic
 * years, standards, sections, subjects, class sections, staff, parents,
 * students) already has a working form — all of them live on
 * Admin\DashboardController's single view (resources/views/admin/
 * dashboard.blade.php), which is also this school's only admin "dashboard"
 * today. This controller does not re-implement any of that; it is a
 * progress-tracked checklist that links into the already-existing
 * sections of that same page (via anchors added to their <x-card>
 * wrappers) plus the standalone Admin\SchoolProfileController screen, so
 * a brand-new admin gets a guided order to work through instead of one
 * long scrollable page with no sense of "what's next."
 *
 * setup_step/setup_status (School::$fillable excludes both, matching
 * `status`'s own pattern — see that model's doc comment) are read-only
 * progress signals, never an access gate: index() is reachable at any
 * setup_status, and Admin\DashboardController's own banner links back
 * here without ever redirecting away from the dashboard itself. advance()/
 * complete() have no route parameter (they mutate the acting admin's own
 * school, resolved from $request->user(), not a record looked up by id),
 * matching every other id-less write action in this bundle's exemption
 * from Phase1TestGateTest's row 8 scope-checked lint.
 */
class SetupWizardController extends Controller
{
    /**
     * @return list<array{key: string, title: string, description: string, anchor: ?string}>
     */
    public static function steps(): array
    {
        return [
            [
                'key' => 'school_information',
                'title' => 'School Information',
                'description' => 'Name, logo, contact details, and address.',
                'anchor' => null,
                'route' => 'admin.school-profile.edit',
            ],
            [
                'key' => 'academic_configuration',
                'title' => 'Academic Configuration',
                'description' => 'Academic year, working days, timetable periods, and your own classes/grades.',
                'anchor' => 'academic-years',
                'route' => 'admin.dashboard',
            ],
            [
                'key' => 'subjects',
                'title' => 'Subjects',
                'description' => 'Create subjects and assign them to classes.',
                'anchor' => 'subjects',
                'route' => 'admin.dashboard',
            ],
            [
                'key' => 'staff',
                'title' => 'Staff & Teachers',
                'description' => 'Create or invite teachers and non-teaching staff.',
                'anchor' => 'create-staff-account',
                'route' => 'admin.dashboard',
            ],
            [
                'key' => 'parents_students',
                'title' => 'Parents & Students',
                'description' => 'Enroll students, and create or invite parents.',
                'anchor' => 'enroll-a-student',
                'route' => 'admin.dashboard',
            ],
            [
                'key' => 'finish',
                'title' => 'Finish',
                'description' => 'Mark setup complete and head to your dashboard.',
                'anchor' => null,
                'route' => 'admin.dashboard',
            ],
        ];
    }

    public function index(Request $request): View
    {
        return view('admin.setup.index', [
            'school' => $request->user()->school,
            'steps' => self::steps(),
        ]);
    }

    /**
     * Marks one step complete and advances setup_step past it. Steps can
     * be completed out of order (a step number lower than the school's
     * current setup_step is a no-op re-submission, not an error) — this
     * is a checklist, not a linear gate, matching the plan's "never
     * force every optional configuration" decision.
     */
    public function advance(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'step' => ['required', 'integer', 'min:1', 'max:' . count(self::steps())],
        ]);

        $school = $request->user()->school;

        if ($data['step'] >= $school->setup_step) {
            $school->setup_step = min($data['step'] + 1, count(self::steps()) + 1);
        }

        if ($school->setup_status === 'pending') {
            $school->setup_status = 'in_progress';
        }

        $school->save();

        return back()->with('status', 'Progress saved.');
    }

    public function complete(Request $request): RedirectResponse
    {
        $school = $request->user()->school;
        $school->setup_status = 'complete';
        $school->save();

        return redirect()->route('admin.dashboard')->with('status', 'School setup complete.');
    }
}
