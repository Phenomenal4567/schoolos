@extends('layouts.app')

@section('title', 'Expenses — SchoolOS')
@section('page-title', 'Expenses')

@section('content')
    <x-card>
        <h2 class="text-sm font-semibold text-gray-900">Record expense</h2>
        <form method="POST" action="{{ route('admin.expenses.store') }}" class="mt-3 space-y-3">
            @csrf
            <div class="grid gap-3 sm:grid-cols-2">
                <x-select
                    name="category"
                    label="Category"
                    :options="['staff_payment' => 'Staff payment', 'operational' => 'Operational', 'other' => 'Other']"
                    required
                />
                <x-input type="number" name="amount" label="Amount" step="0.01" required />
            </div>
            <x-input type="date" name="incurred_on" label="Incurred on" required />
            <x-input name="description" label="Description" />
            <x-button type="submit" variant="primary" size="sm">Record expense</x-button>
        </form>
    </x-card>

    <x-card :padded="false">
        <div class="border-b border-gray-200 px-4 py-3 sm:px-6">
            <h2 class="text-sm font-semibold text-gray-900">Expenses</h2>
        </div>
        @if ($expenses->isEmpty())
            <div class="p-4 sm:p-6">
                <x-empty-state title="No expenses recorded" description="Expenses you record above will appear here, most recent first." />
            </div>
        @else
            <div class="divide-y divide-gray-100">
                @foreach ($expenses as $expense)
                    <div class="flex items-center justify-between gap-3 px-4 py-3 sm:px-6">
                        <div class="min-w-0">
                            <p class="text-sm font-medium text-gray-900">{{ ucwords(str_replace('_', ' ', $expense->category)) }}</p>
                            @if ($expense->description)
                                <p class="mt-0.5 truncate text-sm text-gray-600">{{ $expense->description }}</p>
                            @endif
                            <p class="mt-0.5 text-xs text-gray-500">{{ $expense->incurred_on?->format('M j, Y') }}</p>
                        </div>
                        <span class="text-sm font-semibold text-gray-900">{{ number_format((float) $expense->amount, 2) }}</span>
                    </div>
                @endforeach
            </div>
        @endif
    </x-card>
@endsection
