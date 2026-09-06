<?php

namespace App\Http\Controllers\Admin;

use App\Models\Role;
use App\Models\User;
use App\Http\Controllers\Controller;
use App\Repositories\InvitationRepository;
use App\Repositories\ParentLinkRepository;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Design ref: SchoolOS Account Creation & Onboarding plan, §4 (Parent
 * onboarding)
 *
 * `invite` path is additive — see Admin\StaffController's own doc
 * comment for the identical reasoning applied here.
 */
class ParentEnrollmentController extends Controller
{
    public function store(Request $request, ParentLinkRepository $parentLinks, InvitationRepository $invitations): RedirectResponse
    {
        $actor = $request->user();

        // See Admin\StaffController::store()'s identical doc comment for
        // why this is resolved before validate() rather than expressed as
        // required_if:invite,false.
        $invite = $request->boolean('invite');

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required_without:mobile_no', 'nullable', 'email', 'max:255', 'unique:users,email'],
            'mobile_no' => ['required_without:email', 'nullable', 'string', 'max:255', 'unique:users,mobile_no'],
            'password' => [Rule::requiredIf(! $invite), 'nullable', 'string', 'min:8'],
            'student_ids' => ['required', 'array', 'min:1'],
            'student_ids.*' => [
                'integer',
                'distinct',
                Rule::exists('users', 'id')->where('school_id', $actor->school_id),
            ],
        ]);

        $parentRole = Role::where('key', 'parent')->firstOrFail();

        try {
            $parent = DB::transaction(function () use ($actor, $data, $parentRole, $parentLinks, $invite): User {
                $parent = User::create([
                    'school_id' => $actor->school_id,
                    'role_id' => $parentRole->id,
                    'name' => $data['name'],
                    'email' => $data['email'] ?? null,
                    'mobile_no' => $data['mobile_no'] ?? null,
                    'password' => $invite ? Str::random(32) : $data['password'],
                    'status' => 'active',
                ]);

                foreach ($data['student_ids'] as $studentId) {
                    $parentLinks->link($actor->school_id, $parent->id, (int) $studentId, $actor);
                }

                return $parent;
            });
        } catch (\Throwable $e) {
            report($e);

            return back()->withErrors(['student_ids' => 'Parent account could not be linked to the selected students.'])->withInput();
        }

        if ($invite) {
            $invitations->issue($parent, $actor);

            return back()->with('status', 'Parent invitation sent and linked.');
        }

        return back()->with('status', 'Parent account created and linked.');
    }
}
