<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\FinanceReportingService;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Design ref: 21-schoolos-finance-architecture.md §7,
 * 22-schoolos-finance-schema.md §6 (discovery §13).
 *
 * school_admin/accountant's read-only reporting surface — discovery
 * §13's four views (Revenue, Expenses, Cash Flow, Transactions), all of
 * which are FinanceReportingService queries over the acting admin's own
 * $actor->school_id (never client input directly, matching that
 * service's own Ground Rule 0 note). No route parameter — the whole
 * page is a report over the caller's own tenant, not a single record —
 * so it's exempt from Phase1TestGateTest's row 8 lint the same way
 * admin.dashboard is.
 *
 * $request->query('start_date'|'end_date') are optional inclusive date
 * bounds (Y-m-d), passed straight through to FinanceReportingService,
 * which already treats a null bound as open per its own doc comment —
 * this controller does no date validation/parsing of its own beyond
 * that.
 */
class FinanceDashboardController extends Controller
{
    public function index(Request $request, FinanceReportingService $reporting): View
    {
        $actor = $request->user();

        $startDate = $request->query('start_date');
        $endDate = $request->query('end_date');

        return view('admin.finance.dashboard', [
            'revenue' => $reporting->revenue($actor->school_id, $startDate, $endDate),
            'expenses' => $reporting->expenses($actor->school_id, $startDate, $endDate),
            'cashFlow' => $reporting->cashFlow($actor->school_id, $startDate, $endDate),
            'transactions' => $reporting->transactions($actor->school_id, $startDate, $endDate),
            'startDate' => $startDate,
            'endDate' => $endDate,
        ]);
    }
}
