<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Expense;
use App\Repositories\ExpenseRepository;
use App\Services\ScopeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Design ref: 22-schoolos-finance-schema.md §5 (discovery §13.2).
 *
 * school_admin/accountant's write and read surface for expenses —
 * admin-only, no parent visibility, tenantScope() only (no
 * relationshipScope dimension — expenses have no per-student
 * relationship, per that schema section's own note). index has no route
 * parameter, exempt from row 8's lint the same way admin.dashboard is.
 * Returns a View/RedirectResponse, not JSON, matching this whole
 * finance block's convention (Admin\FeeAssessmentController's own doc
 * comment).
 */
class ExpenseController extends Controller
{
    public function index(Request $request, ScopeService $scope): View
    {
        $actor = $request->user();

        $expenses = $scope->tenantScope(Expense::query(), $actor)
            ->orderByDesc('incurred_on')
            ->get();

        return view('admin.expenses.index', ['expenses' => $expenses]);
    }

    public function store(Request $request, ExpenseRepository $repository): RedirectResponse
    {
        $actor = $request->user();

        $data = $request->validate([
            'category' => ['required', 'string', 'in:staff_payment,operational,other'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'description' => ['nullable', 'string'],
            'incurred_on' => ['required', 'date'],
        ]);

        $repository->create(
            $actor->school_id,
            $data['category'],
            (string) $data['amount'],
            $data['description'] ?? null,
            $data['incurred_on'],
            $actor,
        );

        return redirect()->route('admin.expenses.index')->with('status', 'Expense recorded.');
    }
}
