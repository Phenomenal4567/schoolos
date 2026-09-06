<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\StaffDocument;
use App\Models\StaffProfile;
use App\Models\User;
use App\Services\ScopeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * Design ref: 26-discovery-hierarchy-status.md ("Rich Teacher/Staff
 * Enrollment And Profiles").
 *
 * Read-only oversight for a school_admin: qualifications,
 * responsibilities, CV, supporting documents, and rules-acknowledgement
 * status for any of that school's own staff. No update() for that
 * self-authored content — a staff member's own profile is authored by
 * themselves through TeacherPortal\ProfileController, not edited on
 * their behalf here, same "self-service, admin views" split
 * ParentPortal/StudentPortal controllers already establish for other
 * resources.
 *
 * updatePermissions() is the one narrow exception: staff_profiles.
 * can_manage_timetable is not self-authored content like qualifications
 * or a CV, it's a school_admin-granted delegation (see
 * EnsureCanManageTimetable's doc comment) — the staff member is the
 * subject of that grant, not its author, so it doesn't belong behind
 * TeacherPortal\ProfileController the way the rest of this profile
 * does. Deliberately its own action rather than folded into a general
 * update() for this controller, so the "read-only oversight" rule
 * above still holds for everything else on this screen.
 */
class StaffProfileController extends Controller
{
    public function show(Request $request, ScopeService $scope, int $user): View
    {
        $actor = $request->user();
        $staff = $scope->tenantScope(User::query(), $actor)->with('role')->findOrFail($user);

        return view('admin.staff.profile', [
            'staff' => $staff,
            'staffProfile' => StaffProfile::where('user_id', $staff->id)->first(),
            'documents' => StaffDocument::where('user_id', $staff->id)->orderByDesc('created_at')->get(),
        ]);
    }

    public function downloadDocument(Request $request, ScopeService $scope, int $user, int $document): Response
    {
        $actor = $request->user();
        $staff = $scope->tenantScope(User::query(), $actor)->findOrFail($user);

        $staffDocument = StaffDocument::where('school_id', $actor->school_id)
            ->where('user_id', $staff->id)
            ->find($document);

        if ($staffDocument === null || ! Storage::disk('local')->exists($staffDocument->file_path)) {
            abort(404);
        }

        return Storage::disk('local')->download($staffDocument->file_path, $staffDocument->label);
    }

    public function updatePermissions(Request $request, ScopeService $scope, int $user): \Illuminate\Http\RedirectResponse
    {
        $actor = $request->user();
        $staff = $scope->tenantScope(User::query(), $actor)->findOrFail($user);

        $data = $request->validate([
            'can_manage_timetable' => ['sometimes', 'boolean'],
        ]);

        StaffProfile::updateOrCreate(
            ['school_id' => $actor->school_id, 'user_id' => $staff->id],
            ['can_manage_timetable' => (bool) ($data['can_manage_timetable'] ?? false)],
        );

        return back()->with('status', 'Permissions updated.');
    }
}
