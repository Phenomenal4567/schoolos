<?php

namespace App\Http\Controllers\StaffAttendance;

use App\Exceptions\StaffAttendance\DuplicateStaffAttendanceFailure;
use App\Http\Controllers\Controller;
use App\Repositories\StaffAttendanceRepository;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Design ref: discovery §5. Execution ref:
 * 20-phase-6-8-execution-prompt.md §4.
 *
 * Self-service check-in, reachable by every StaffAttendanceRepository
 * ::STAFF_ROLE_KEYS role (not just 'staff' — see routes/web.php's staff
 * attendance group), matching this bundle's "route middleware and
 * repository eligibility check read from the same role list" discipline
 * (EnsureUserHasRole's own doc comment). Always checks the authenticated
 * user in as themselves via the 'manual' method — a staff member cannot
 * submit someone else's user id here, since $request->user() is the
 * only identity this controller ever passes to the repository.
 *
 * The 'qr' method's self-check-in analog (a staff member scanning a
 * school-displayed code, as opposed to an admin scanning the staff
 * member's card) is not built in this pass — discovery §5 names it as
 * a possible method, but nothing in this bundle yet distinguishes "a
 * kiosk/school-displayed QR" as a payload from "the staff member's own
 * ID-card QR," which QrTokenService already issues for the
 * admin-scans-the-card flow (see Admin\StaffAttendanceController::scan()).
 * Extending this controller to accept a scanned token is additive, not
 * a breaking change, once that distinction is made.
 */
class CheckInController extends Controller
{
    public function store(Request $request, StaffAttendanceRepository $repository): RedirectResponse
    {
        $actor = $request->user();

        try {
            $repository->checkIn($actor, 'manual');
        } catch (DuplicateStaffAttendanceFailure $e) {
            return back()->withErrors(['check_in' => $e->getMessage()]);
        }

        return back()->with('status', 'Checked in.');
    }
}
