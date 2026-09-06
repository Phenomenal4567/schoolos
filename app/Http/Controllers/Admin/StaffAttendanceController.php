<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\QrToken\ExpiredQrTokenFailure;
use App\Exceptions\QrToken\InvalidQrTokenFailure;
use App\Exceptions\StaffAttendance\DuplicateStaffAttendanceFailure;
use App\Exceptions\StaffAttendance\NotAStaffMemberFailure;
use App\Http\Controllers\Controller;
use App\Repositories\StaffAttendanceRepository;
use App\Services\QrTokenService;
use App\Services\ScopeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Design ref: discovery §5. Decision ref:
 * 16-schoolos-decisions-register.md D12. Execution ref:
 * 20-phase-6-8-execution-prompt.md §4.
 *
 * school_admin's staff-attendance surface: scan a staff member's
 * ID-card QR to check them in, and view every attendance row for their
 * tenant. index() returns JSON, matching this group's existing
 * write-path-focused convention for resources with no full Blade read
 * UI (Admin\AnnouncementController's doc comment gives the same
 * reasoning) — a dedicated staff-attendance dashboard view is future
 * work, not required by this pass's build list.
 *
 * scan() deliberately does not take a target user id from the request
 * at all — the whole point of scanning is that the identity comes from
 * the token, not from a form field the admin fills in (which would
 * make the QR step theater). QrTokenService::verify() resolves who the
 * token belongs to; StaffAttendanceRepository::checkIn() re-verifies
 * the same token and re-derives the same identity, so there's no path
 * where the admin's claim and the token's claim about identity can
 * diverge.
 */
class StaffAttendanceController extends Controller
{
    public function index(Request $request, ScopeService $scope, StaffAttendanceRepository $repository): JsonResponse
    {
        $actor = $request->user();

        $records = $repository->visibleTo($actor, $scope)
            ->with('user:id,name,staff_id,role_id')
            ->orderByDesc('date')
            ->orderByDesc('check_in')
            ->get();

        return response()->json(['data' => $records]);
    }

    public function scan(
        Request $request,
        QrTokenService $qrTokens,
        StaffAttendanceRepository $repository
    ): RedirectResponse {
        $data = $request->validate(['qr_token' => ['required', 'string']]);

        try {
            $staff = $qrTokens->verify($data['qr_token']);

            $record = $repository->checkIn($staff, 'qr', $data['qr_token']);
        } catch (InvalidQrTokenFailure|ExpiredQrTokenFailure $e) {
            return back()->withErrors(['qr_token' => $e->getMessage()]);
        } catch (NotAStaffMemberFailure $e) {
            return back()->withErrors(['qr_token' => $e->getMessage()]);
        } catch (DuplicateStaffAttendanceFailure $e) {
            return back()->withErrors(['qr_token' => $e->getMessage()]);
        }

        return back()->with('status', "Checked in {$record->user->name} for {$record->date->toDateString()}.");
    }
}
