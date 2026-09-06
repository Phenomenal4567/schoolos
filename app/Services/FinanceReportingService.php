<?php

namespace App\Services;

use App\Models\Expense;
use App\Models\FeeAssessment;
use App\Models\Payment;
use Illuminate\Support\Collection;

/**
 * Design ref: 21-schoolos-finance-architecture.md §7 (discovery §13).
 *
 * Read-only reporting queries over fee_assessments/payments/expenses —
 * no new write paths (§7's own framing). Every method takes $schoolId
 * from the caller's own scoped context, never client input directly,
 * matching `13`'s Ground Rule 0 language verbatim — this is exactly the
 * kind of aggregate-across-everything view where a missing tenant
 * filter would be catastrophic, not merely a single-record IDOR.
 *
 * $startDate/$endDate are inclusive date strings (Y-m-d); null on
 * either side leaves that bound open, matching ExportJobRepository's
 * own nullable date-range shape for its 'custom' range type.
 */
class FinanceReportingService
{
    public function revenue(int $schoolId, ?string $startDate, ?string $endDate): array
    {
        $query = Payment::where('school_id', $schoolId)->where('status', 'confirmed');
        $this->applyDateRange($query, 'created_at', $startDate, $endDate);

        return [
            'total' => (string) $query->sum('amount'),
            'by_processor' => $query->clone()->selectRaw('processor, SUM(amount) as total')
                ->groupBy('processor')
                ->pluck('total', 'processor'),
        ];
    }

    public function expenses(int $schoolId, ?string $startDate, ?string $endDate): array
    {
        $query = Expense::where('school_id', $schoolId);
        $this->applyDateRange($query, 'incurred_on', $startDate, $endDate);

        return [
            'total' => (string) $query->sum('amount'),
            'by_category' => $query->clone()->selectRaw('category, SUM(amount) as total')
                ->groupBy('category')
                ->pluck('total', 'category'),
        ];
    }

    /**
     * Revenue minus expenses for the range, plus outstanding/rolled-over
     * debt figures traced through fee_assessments'
     * rolled_over_from_assessment_id chain (21 §4) rather than a
     * separately-maintained running total.
     */
    public function cashFlow(int $schoolId, ?string $startDate, ?string $endDate): array
    {
        $revenue = $this->revenue($schoolId, $startDate, $endDate);
        $expenses = $this->expenses($schoolId, $startDate, $endDate);

        $outstanding = FeeAssessment::where('school_id', $schoolId)
            ->get()
            ->sum(fn (FeeAssessment $assessment) => (float) $assessment->amountRemaining());

        $rolledOver = FeeAssessment::where('school_id', $schoolId)
            ->whereNotNull('rolled_over_from_assessment_id')
            ->sum('amount_due');

        return [
            'revenue' => $revenue['total'],
            'expenses' => $expenses['total'],
            'net' => bcsub($revenue['total'], $expenses['total'], 2),
            'outstanding_debt' => number_format($outstanding, 2, '.', ''),
            'rolled_over_debt' => (string) $rolledOver,
        ];
    }

    /**
     * Unified payments + expenses history for the range, paginated by
     * the caller via $perPage/$page — a single chronological feed, per
     * discovery §13's "Transactions" view.
     */
    public function transactions(int $schoolId, ?string $startDate, ?string $endDate): Collection
    {
        $paymentsQuery = Payment::where('school_id', $schoolId);
        $this->applyDateRange($paymentsQuery, 'created_at', $startDate, $endDate);

        $expensesQuery = Expense::where('school_id', $schoolId);
        $this->applyDateRange($expensesQuery, 'incurred_on', $startDate, $endDate);

        $payments = $paymentsQuery->get()->map(fn (Payment $payment) => [
            'type' => 'payment',
            'amount' => (string) $payment->amount,
            'date' => optional($payment->created_at)->toDateString(),
            'reference' => $payment,
        ]);

        $expenses = $expensesQuery->get()->map(fn (Expense $expense) => [
            'type' => 'expense',
            'amount' => (string) $expense->amount,
            'date' => optional($expense->incurred_on)->toDateString(),
            'reference' => $expense,
        ]);

        return $payments->concat($expenses)->sortByDesc('date')->values();
    }

    private function applyDateRange($query, string $column, ?string $startDate, ?string $endDate): void
    {
        if ($startDate !== null) {
            $query->whereDate($column, '>=', $startDate);
        }

        if ($endDate !== null) {
            $query->whereDate($column, '<=', $endDate);
        }
    }
}
