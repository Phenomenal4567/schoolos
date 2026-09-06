<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\QrTokenService;
use App\Services\ScopeService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * Design ref: school_management_system_discovery_hierarchy.md §2.3
 * (student ID card), §5 ("Staff ID Card" — photo, ID, name, position,
 * QR code). Decision ref: 16-schoolos-decisions-register.md D12.
 * Execution ref: 20-phase-6-8-execution-prompt.md §4 ("ID card
 * rendering (presentation-layer, from existing User/ClassSection data
 * — no new domain table beyond the ID fields)").
 *
 * Presentation-layer only, as the execution prompt specifies — this
 * controller writes nothing. show() resolves the target user through
 * ScopeService::tenantScope() (school_admin's tenant only, same
 * discipline as Admin\CalendarEventController's update()/destroy()) and
 * renders a standalone, print-oriented Blade view: no app-shell
 * sidebar/nav, since a printed/laminated card isn't a page anyone
 * navigates from.
 *
 * The QR embedded on the card is a fresh QrTokenService::issueIdCardToken()
 * call made on every render, not a value stored anywhere — see that
 * method's doc comment on why this is safe (verification only cares
 * what a token resolves to, never whether it matches some stored
 * original).
 */
class IdCardController extends Controller
{
    public function show(Request $request, ScopeService $scope, QrTokenService $qrTokens, int $user): View
    {
        $actor = $request->user();

        $target = $scope->tenantScope(User::with('role')->newQuery(), $actor)
            ->findOrFail($user);

        if ($target->student_id === null && $target->staff_id === null) {
            abort(422, 'This user has no student_id or staff_id yet — an ID card cannot be issued.');
        }

        return view('admin.id-cards.show', [
            'target' => $target,
            'identifier' => $target->student_id ?? $target->staff_id,
            'position' => $target->role->label ?? null,
            // Resolved here, not in the Blade view, since this bundle's
            // views don't reference facades directly (no global facade
            // aliases registered) — see this class's doc comment.
            'photoUrl' => $target->photo_path ? Storage::url($target->photo_path) : null,
            'qrToken' => $qrTokens->issueIdCardToken($target),
        ]);
    }
}
