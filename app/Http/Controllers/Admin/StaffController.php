<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\User;
use App\Repositories\InvitationRepository;
use App\Repositories\StaffAttendanceRepository;
use App\Services\IdentifierService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Design ref: SchoolOS Account Creation & Onboarding plan, §2/§3
 * (Teacher/Staff onboarding)
 *
 * store()'s allowed-role list is now StaffAttendanceRepository::
 * STAFF_ROLE_KEYS minus 'school_admin' — that constant already is this
 * codebase's single source of truth for "which role keys count as staff"
 * (StaffAttendanceRepository::checkIn() gates against it too); this
 * controller previously hand-maintained its own separate copy that had
 * drifted to omit 'school_admin' implicitly by just never including it,
 * which happened to be correct but wasn't derived from anything.
 *
 * The `invite` path is additive, not a replacement (SchoolOS Account
 * Creation & Onboarding plan, Decision — "invite is additive"): a school
 * that already knows how to hand a new hire a password can still supply
 * one directly; `invite: true` instead creates the account with no
 * usable password and sends/returns an activation link via
 * InvitationRepository::issue(), the same one write path every other
 * invite-capable controller in this pass uses.
 */
class StaffController extends Controller
{
    public function store(Request $request, IdentifierService $identifiers, InvitationRepository $invitations): RedirectResponse
    {
        $actor = $request->user();
        $allowedRoles = array_values(array_diff(StaffAttendanceRepository::STAFF_ROLE_KEYS, ['school_admin']));

        // Computed before validate() rather than expressed as
        // required_if:invite,false — that rule string-matches invite
        // against the literal value 'false', which never fires when
        // invite is simply absent from the request (the common case for
        // an "I'll type a password" submission), leaving password
        // silently optional. Rule::requiredIf() takes an already-resolved
        // boolean instead, so "no invite flag at all" and "invite=false"
        // behave identically.
        $invite = $request->boolean('invite');

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required_without:mobile_no', 'nullable', 'email', 'max:255', 'unique:users,email'],
            'mobile_no' => ['required_without:email', 'nullable', 'string', 'max:255', 'unique:users,mobile_no'],
            'role' => ['required', Rule::in($allowedRoles)],
            'password' => [Rule::requiredIf(! $invite), 'nullable', 'string', 'min:8'],
        ]);

        $role = Role::where('key', $data['role'])->firstOrFail();

        $staff = User::create([
            'school_id' => $actor->school_id,
            'role_id' => $role->id,
            'name' => $data['name'],
            'email' => $data['email'] ?? null,
            'mobile_no' => $data['mobile_no'] ?? null,
            'password' => $invite ? Str::random(32) : $data['password'],
            'status' => 'active',
        ]);

        $identifiers->generateStaffId($staff->fresh(['role', 'school']));

        if ($invite) {
            $invitations->issue($staff, $actor);

            return back()->with('status', 'Staff invitation sent.');
        }

        return back()->with('status', 'Staff account created.');
    }
}
