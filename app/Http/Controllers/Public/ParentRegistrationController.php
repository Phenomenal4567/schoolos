<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\School;
use App\Models\User;
use App\Services\AuthenticationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Design ref: SchoolOS Account Creation & Onboarding plan, §4 (Parent
 * onboarding)
 *
 * Guest-facing self-registration, same `?school=` short_code pattern as
 * Public\AdmissionApplicationController — see that controller's own doc
 * comment for why a query parameter, not a route parameter, and why the
 * school is still resolved server-side from short_code rather than
 * trusted from client input regardless.
 *
 * Deliberately creates the parent User row only — it never links that
 * parent to any student. Linking is a separate, authenticated step
 * (ParentPortal\ChildLinkRequestController::store(), which creates a
 * 'pending' request via ParentLinkRepository::request()) that still
 * requires an admin approval action before any student data becomes
 * reachable (Admin\ParentLinkController::approve()) — this keeps
 * ParentLinkRepository::link()'s existing "admin-initiated, not
 * self-service" contract exactly true: nothing this controller does ever
 * grants access to a student record on its own.
 */
class ParentRegistrationController extends Controller
{
    public function create(Request $request): View
    {
        $school = School::where('short_code', $request->query('school'))->firstOrFail();

        return view('public.parents.register', ['school' => $school]);
    }

    public function store(Request $request, AuthenticationService $authService): RedirectResponse
    {
        $school = School::where('short_code', $request->input('school'))->firstOrFail();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required_without:mobile_no', 'nullable', 'email', 'max:255', 'unique:users,email'],
            'mobile_no' => ['required_without:email', 'nullable', 'string', 'max:255', 'unique:users,mobile_no'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $parentRole = Role::where('key', 'parent')->firstOrFail();

        $parent = User::create([
            'school_id' => $school->id,
            'role_id' => $parentRole->id,
            'name' => $data['name'],
            'email' => $data['email'] ?? null,
            'mobile_no' => $data['mobile_no'] ?? null,
            'password' => $data['password'],
            'status' => 'active',
        ]);

        $authService->issueSession($parent);

        return redirect($authService->dashboardPathFor($parent))
            ->with('status', 'Welcome! Next, tell us which student to connect to your account.');
    }
}
