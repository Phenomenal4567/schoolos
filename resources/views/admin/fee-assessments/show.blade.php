@extends('layouts.app')

@section('title', 'Fee Assessment — SchoolOS')
@section('page-title', 'Fee Assessment')

@section('content')
    <a href="{{ route('admin.fee-assessments.index') }}" class="inline-flex items-center gap-1 text-sm text-gray-500 hover:text-gray-700">
        <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor"><path d="M13 15l-6-5 6-5"/></svg>
        All assessments
    </a>

    <x-card>
        <div class="flex items-start justify-between gap-3">
            <div>
                <h2 class="text-base font-semibold text-gray-900">{{ $assessment->student?->name ?? 'Unknown student' }}</h2>
                <p class="mt-0.5 text-sm text-gray-600">{{ $assessment->feeCategory?->label ?? 'Unknown category' }}</p>
            </div>
            <x-badge
                variant="{{ match ($assessment->status) {
                    'paid' => 'success',
                    'partially_paid' => 'warning',
                    'void' => 'neutral',
                    default => 'danger',
                } }}"
            >
                {{ ucwords(str_replace('_', ' ', $assessment->status)) }}
            </x-badge>
        </div>

        @if ($assessment->rolledOverFrom)
            <p class="mt-2 text-xs text-gray-500">
                Rolled over from assessment #{{ $assessment->rolled_over_from_assessment_id }}
            </p>
        @endif

        <dl class="mt-4 grid grid-cols-2 gap-4 sm:grid-cols-4">
            <div>
                <dt class="text-xs text-gray-500">Base amount</dt>
                <dd class="mt-0.5 text-sm font-medium text-gray-900">{{ number_format((float) $assessment->base_amount, 2) }}</dd>
            </div>
            <div>
                <dt class="text-xs text-gray-500">Discount</dt>
                <dd class="mt-0.5 text-sm font-medium text-gray-900">{{ number_format((float) $assessment->discount_amount, 2) }}</dd>
            </div>
            <div>
                <dt class="text-xs text-gray-500">Scholarship</dt>
                <dd class="mt-0.5 text-sm font-medium text-gray-900">{{ number_format((float) $assessment->scholarship_amount, 2) }}</dd>
            </div>
            <div>
                <dt class="text-xs text-gray-500">Amount due</dt>
                <dd class="mt-0.5 text-sm font-medium text-gray-900">{{ number_format((float) $assessment->amount_due, 2) }}</dd>
            </div>
        </dl>

        <div class="mt-4 rounded-md bg-gray-50 px-3 py-2">
            <p class="text-xs text-gray-500">Amount remaining</p>
            <p class="text-lg font-semibold text-gray-900">{{ number_format((float) $amountRemaining, 2) }}</p>
        </div>
    </x-card>

    <x-card :padded="false">
        <div class="border-b border-gray-200 px-4 py-3 sm:px-6">
            <h2 class="text-sm font-semibold text-gray-900">Payments</h2>
        </div>
        @if ($assessment->payments->isEmpty())
            <div class="p-4 sm:p-6">
                <x-empty-state title="No payments yet" description="Payments recorded against this assessment will appear here." />
            </div>
        @else
            <div class="divide-y divide-gray-100">
                @foreach ($assessment->payments->sortByDesc('created_at') as $payment)
                    <div class="flex items-center justify-between gap-3 px-4 py-3 sm:px-6">
                        <div class="min-w-0">
                            <p class="text-sm font-medium text-gray-900">
                                {{ ucfirst($payment->processor) }}
                                @if ($payment->receipt_upload_path)
                                    &middot; receipt attached
                                @endif
                            </p>
                            <p class="mt-0.5 text-xs text-gray-500">{{ $payment->created_at?->format('M j, Y g:ia') }}</p>
                        </div>
                        <div class="flex items-center gap-2">
                            <x-badge
                                variant="{{ match ($payment->status) {
                                    'confirmed' => 'success',
                                    'pending' => 'warning',
                                    default => 'danger',
                                } }}"
                            >
                                {{ ucfirst($payment->status) }}
                            </x-badge>
                            <span class="text-sm font-semibold text-gray-900">{{ number_format((float) $payment->amount, 2) }}</span>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </x-card>

    @if ($assessment->discounts->isNotEmpty())
        <x-card :padded="false">
            <div class="border-b border-gray-200 px-4 py-3 sm:px-6">
                <h2 class="text-sm font-semibold text-gray-900">Discounts</h2>
            </div>
            <div class="divide-y divide-gray-100">
                @foreach ($assessment->discounts as $discount)
                    <div class="flex items-center justify-between gap-3 px-4 py-3 sm:px-6">
                        <div class="min-w-0">
                            <p class="text-sm font-medium text-gray-900">{{ ucfirst($discount->type) }}</p>
                            @if ($discount->reason)
                                <p class="mt-0.5 truncate text-xs text-gray-500">{{ $discount->reason }}</p>
                            @endif
                        </div>
                        <span class="text-sm font-semibold text-gray-900">
                            {{ $discount->type === 'percentage' ? $discount->value . '%' : number_format((float) $discount->value, 2) }}
                        </span>
                    </div>
                @endforeach
            </div>
        </x-card>
    @endif

    <div class="grid gap-4 lg:grid-cols-2">
        <x-card>
            <h2 class="text-sm font-semibold text-gray-900">Record manual payment</h2>
            <p class="mt-1 text-xs text-gray-500">Cash, bank transfer, or a receipt handed over in person.</p>
            <form method="POST" action="{{ route('admin.fee-assessments.payments.store', $assessment) }}" class="mt-3 space-y-3">
                @csrf
                <x-input type="number" name="amount" label="Amount" step="0.01" required />
                <x-input name="receipt_upload_path" label="Receipt path (optional)" />
                <x-button type="submit" variant="primary" size="sm">Record payment</x-button>
            </form>
        </x-card>

        <x-card>
            <h2 class="text-sm font-semibold text-gray-900">Adjust (post-payment discount)</h2>
            <p class="mt-1 text-xs text-gray-500">
                D11's escape hatch — creates a linked adjustment line rather than rewriting the amount already paid against.
            </p>
            <form method="POST" action="{{ route('admin.fee-assessments.adjust', $assessment) }}" class="mt-3 space-y-3">
                @csrf
                <x-select
                    name="discount_type"
                    label="Type"
                    :options="['individual' => 'Individual', 'percentage' => 'Percentage', 'fixed' => 'Fixed amount']"
                    required
                />
                <x-input type="number" name="discount_value" label="Value" step="0.01" required />
                <x-input name="discount_reason" label="Reason" />
                <x-button type="submit" variant="secondary" size="sm">Apply adjustment</x-button>
            </form>
        </x-card>
    </div>
@endsection
