<?php

namespace App\Http\Controllers\StaffPortal;

use App\Http\Controllers\Controller;
use App\Models\StaffAttendanceRecord;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Design ref: SchoolOS Account Creation & Onboarding plan, §3 (Staff
 * onboarding), Decision 3
 *
 * Deliberately minimal — a generic landing page for any
 * StaffAttendanceRepository::STAFF_ROLE_KEYS role that isn't teacher or
 * school_admin (both already have their own full dashboards). Richer
 * per-role feature sets (an accountant's finance view, a librarian's
 * catalog) don't exist anywhere in SchoolOS yet — inventing them here
 * would be building new product surface under the banner of "onboarding,"
 * not closing an onboarding gap. This shows only what already exists and
 * already applies to every staff role generically: their own StaffProfile
 * completion status (read-only — see this class's own note on why there's
 * no edit link here) and the existing staff-attendance self-check-in
 * action.
 */
class DashboardController extends Controller
{
    public function index(Request $request): View
    {
        $actor = $request->user();

        $todayCheckIn = StaffAttendanceRecord::where('user_id', $actor->id)
            ->whereDate('date', now()->toDateString())
            ->first();

        return view('staff.dashboard', [
            'staffProfile' => $actor->staffProfile,
            'todayCheckIn' => $todayCheckIn,
        ]);
    }
}
