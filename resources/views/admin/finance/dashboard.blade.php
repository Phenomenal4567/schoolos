@extends('layouts.app')

@section('title', 'Finance Dashboard — SchoolOS')
@section('page-title', 'Finance Dashboard')

@section('content')
    {{-- Discovery §13's four views (Revenue, Expenses, Cash Flow,
         Transactions) — all FinanceReportingService queries over the
         acting admin's own tenant (Admin\FinanceDashboardController's
         own doc comment). Date range is a plain GET form so the whole
         page (including the filter) works with no JS. --}}
    <x-card>
        <form method="GET" action="{{ route('admin.finance.dashboard') }}" class="flex flex-wrap items-end gap-3">
            <x-input type="date" name="start_date" label="From" :value="$startDate" :useOld="false" />
            <x-input type="date" name="end_date" label="To" :value="$endDate" :useOld="false" />
            <x-button type="submit" variant="secondary" size="sm">Apply</x-button>
            @if ($startDate || $endDate)
                <x-button href="{{ route('admin.finance.dashboard') }}" variant="ghost" size="sm">Clear</x-button>
            @endif
        </form>
    </x-card>

    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <x-card>
            <p class="text-xs font-medium text-gray-500">Revenue</p>
            <p class="mt-1 text-2xl font-semibold text-gray-900">{{ number_format((float) $revenue['total'], 2) }}</p>
            <p class="mt-1 text-xs text-gray-500">Confirmed payments in range</p>
        </x-card>
        <x-card>
            <p class="text-xs font-medium text-gray-500">Expenses</p>
            <p class="mt-1 text-2xl font-semibold text-gray-900">{{ number_format((float) $expenses['total'], 2) }}</p>
            <p class="mt-1 text-xs text-gray-500">Recorded in range</p>
        </x-card>
        <x-card>
            <p class="text-xs font-medium text-gray-500">Net</p>
            <p class="mt-1 text-2xl font-semibold {{ (float) $cashFlow['net'] >= 0 ? 'text-green-700' : 'text-red-700' }}">
                {{ number_format((float) $cashFlow['net'], 2) }}
            </p>
            <p class="mt-1 text-xs text-gray-500">Revenue &minus; expenses</p>
        </x-card>
        <x-card>
            <p class="text-xs font-medium text-gray-500">Outstanding debt</p>
            <p class="mt-1 text-2xl font-semibold text-gray-900">{{ number_format((float) $cashFlow['outstanding_debt'], 2) }}</p>
            <p class="mt-1 text-xs text-gray-500">
                {{ number_format((float) $cashFlow['rolled_over_debt'], 2) }} rolled over from prior sessions
            </p>
        </x-card>
    </div>

    <div class="grid gap-4 lg:grid-cols-2">
        <x-card>
            <h2 class="text-sm font-semibold text-gray-900">Revenue by processor</h2>
            @if ($revenue['by_processor']->isEmpty())
                <p class="mt-2 text-sm text-gray-500">No confirmed payments in range.</p>
            @else
                <dl class="mt-3 space-y-2">
                    @foreach ($revenue['by_processor'] as $processor => $total)
                        <div class="flex items-center justify-between text-sm">
                            <dt class="text-gray-600">{{ ucfirst($processor) }}</dt>
                            <dd class="font-medium text-gray-900">{{ number_format((float) $total, 2) }}</dd>
                        </div>
                    @endforeach
                </dl>
            @endif
        </x-card>
        <x-card>
            <h2 class="text-sm font-semibold text-gray-900">Expenses by category</h2>
            @if ($expenses['by_category']->isEmpty())
                <p class="mt-2 text-sm text-gray-500">No expenses in range.</p>
            @else
                <dl class="mt-3 space-y-2">
                    @foreach ($expenses['by_category'] as $category => $total)
                        <div class="flex items-center justify-between text-sm">
                            <dt class="text-gray-600">{{ ucwords(str_replace('_', ' ', $category)) }}</dt>
                            <dd class="font-medium text-gray-900">{{ number_format((float) $total, 2) }}</dd>
                        </div>
                    @endforeach
                </dl>
            @endif
        </x-card>
    </div>

    <x-card :padded="false">
        <div class="border-b border-gray-200 px-4 py-3 sm:px-6">
            <h2 class="text-sm font-semibold text-gray-900">Transactions</h2>
        </div>
        @if ($transactions->isEmpty())
            <div class="p-4 sm:p-6">
                <x-empty-state
                    title="No transactions in range"
                    description="Payments and expenses in the selected date range will appear here."
                />
            </div>
        @else
            <div class="divide-y divide-gray-100">
                @foreach ($transactions as $transaction)
                    <div class="flex items-center justify-between gap-3 px-4 py-3 sm:px-6">
                        <div class="min-w-0">
                            <p class="text-sm font-medium text-gray-900">
                                @if ($transaction['type'] === 'payment')
                                    Payment &middot; {{ ucfirst($transaction['reference']->processor) }}
                                @else
                                    Expense &middot; {{ ucwords(str_replace('_', ' ', $transaction['reference']->category)) }}
                                @endif
                            </p>
                            <p class="mt-0.5 text-xs text-gray-500">{{ $transaction['date'] }}</p>
                        </div>
                        <div class="flex items-center gap-2">
                            <x-badge variant="{{ $transaction['type'] === 'payment' ? 'success' : 'neutral' }}">
                                {{ $transaction['type'] === 'payment' ? '+' : '-' }}{{ number_format((float) $transaction['amount'], 2) }}
                            </x-badge>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </x-card>
@endsection
