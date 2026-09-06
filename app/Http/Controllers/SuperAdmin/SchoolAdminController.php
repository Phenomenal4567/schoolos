<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Role;
use App\Models\School;
use App\Models\User;
use App\Repositories\InvitationRepository;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Design ref: SchoolOS Account Creation & Onboarding plan, §1 (School
 * Admin onboarding)
 *
 * `invite` path is additive — see Admin\StaffController's own doc
 * comment for the identical reasoning applied here.
 */
class SchoolAdminController extends Controller
{
    public function store(Request $request, School $school, InvitationRepository $invitations): RedirectResponse
    {
        // See Admin\StaffController::store()'s identical doc comment for
        // why this is resolved before validate() rather than expressed as
        // required_if:invite,false.
        $invite = $request->boolean('invite');

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required_without:mobile_no', 'nullable', 'email', 'max:255', 'unique:users,email'],
            'mobile_no' => ['required_without:email', 'nullable', 'string', 'max:255', 'unique:users,mobile_no'],
            'password' => [Rule::requiredIf(! $invite), 'nullable', 'string', 'min:8'],
        ]);

        $role = Role::where('key', 'school_admin')->firstOrFail();
        $actor = $request->user();

        $admin = User::create([
            'school_id' => $school->id,
            'role_id' => $role->id,
            'name' => $data['name'],
            'email' => $data['email'] ?? null,
            'mobile_no' => $data['mobile_no'] ?? null,
            'password' => $invite ? Str::random(32) : $data['password'],
            'status' => 'active',
        ]);

        AuditLog::create([
            'school_id' => $school->id,
            'actor_id' => $actor->id,
            'action' => 'school_admin.created',
            'entity_type' => 'User',
            'entity_id' => $admin->id,
            'before_state' => null,
            'after_state' => [
                'school_id' => $school->id,
                'role' => 'school_admin',
                'name' => $admin->name,
                'email' => $admin->email,
                'mobile_no' => $admin->mobile_no,
            ],
        ]);

        if ($invite) {
            $invitations->issue($admin, $actor);

            return back()->with('status', 'School admin invitation sent.');
        }

        return back()->with('status', 'School admin account created.');
    }
}
