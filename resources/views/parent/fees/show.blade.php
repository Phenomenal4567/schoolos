@extends('layouts.app')

@section('title', 'Fee - SchoolOS')
@section('page-title', 'Fee')

@section('content')
    <a href="{{ route('parent.fees.index') }}" class="inline-flex items-center gap-1 text-sm text-gray-500 hover:text-gray-700">
        <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor"><path d="M13 15l-6-5 6-5"/></svg>
        All fees
    </a>

    <x-card>
        <div class="flex items-start justify-between gap-3">
            <div>
                <h2 class="text-base font-semibold text-gray-900">{{ $assessment->student?->name ?? 'Unknown' }}</h2>
                <p class="mt-0.5 text-sm text-gray-600">{{ $assessment->feeCategory?->label ?? 'Fee' }}</p>
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

        @if ($assessment->due_date)
            <p class="mt-1 text-xs text-gray-500">Due {{ $assessment->due_date->format('M j, Y') }}</p>
        @endif

        <dl class="mt-4 grid grid-cols-2 gap-4 sm:grid-cols-3">
            <div>
                <dt class="text-xs text-gray-500">Amount due</dt>
                <dd class="mt-0.5 text-sm font-medium text-gray-900">{{ number_format((float) $assessment->amount_due, 2) }}</dd>
            </div>
            @if ((float) $assessment->discount_amount > 0)
                <div>
                    <dt class="text-xs text-gray-500">Discount applied</dt>
                    <dd class="mt-0.5 text-sm font-medium text-gray-900">{{ number_format((float) $assessment->discount_amount, 2) }}</dd>
                </div>
            @endif
            @if ((float) $assessment->scholarship_amount > 0)
                <div>
                    <dt class="text-xs text-gray-500">Scholarship applied</dt>
                    <dd class="mt-0.5 text-sm font-medium text-gray-900">{{ number_format((float) $assessment->scholarship_amount, 2) }}</dd>
                </div>
            @endif
        </dl>

        <div class="mt-4 rounded-md bg-gray-50 px-3 py-2">
            <p class="text-xs text-gray-500">Amount remaining</p>
            <p class="text-lg font-semibold text-gray-900">{{ number_format((float) $amountRemaining, 2) }}</p>
        </div>
    </x-card>

    @if ((float) $amountRemaining > 0)
        <div class="grid gap-4 lg:grid-cols-2">
            <x-card>
                <h2 class="text-sm font-semibold text-gray-900">Pay now</h2>
                <form method="POST" action="{{ route('parent.fees.payments.store', $assessment) }}" class="mt-3 space-y-3">
                    @csrf
                    <input type="hidden" name="payment_method" value="paystack">
                    <x-input type="number" name="amount" label="Amount" step="0.01" :value="$amountRemaining" required />
                    <x-input name="paystack_reference" label="Paystack reference" required />
                    <x-button type="submit" variant="primary" size="sm">Pay</x-button>
                </form>
            </x-card>

            <x-card>
                <h2 class="text-sm font-semibold text-gray-900">Upload receipt</h2>
                <form method="POST" action="{{ route('parent.fees.payments.store', $assessment) }}" enctype="multipart/form-data" class="mt-3 space-y-3">
                    @csrf
                    <input type="hidden" name="payment_method" value="receipt_upload">
                    <x-input type="number" name="amount" label="Amount paid" step="0.01" :value="$amountRemaining" required />
                    <div>
                        <label for="receipt" class="mb-1 block text-sm font-medium text-gray-700">Receipt</label>
                        <input id="receipt" name="receipt" type="file" accept=".jpg,.jpeg,.png,.pdf,image/jpeg,image/png,application/pdf" class="block w-full text-sm text-gray-700 file:mr-3 file:rounded-md file:border-0 file:bg-gray-100 file:px-3 file:py-1.5 file:text-sm file:font-medium file:text-gray-700 hover:file:bg-gray-200">
                        @error('receipt')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                    <x-button type="submit" variant="secondary" size="sm">Upload receipt</x-button>
                </form>
            </x-card>
        </div>
    @endif

    <x-card :padded="false">
        <div class="border-b border-gray-200 px-4 py-3 sm:px-6">
            <h2 class="text-sm font-semibold text-gray-900">Payment history</h2>
        </div>
        @if ($assessment->payments->isEmpty())
            <div class="p-4 sm:p-6">
                <x-empty-state title="No payments yet" description="Payments against this fee will appear here." />
            </div>
        @else
            <div class="divide-y divide-gray-100">
                @foreach ($assessment->payments->sortByDesc('created_at') as $payment)
                    <div class="flex items-center justify-between gap-3 px-4 py-3 sm:px-6">
                        <div class="min-w-0">
                            <p class="text-sm font-medium text-gray-900">{{ ucfirst($payment->processor) }}</p>
                            <p class="mt-0.5 text-xs text-gray-500">{{ $payment->created_at?->format('M j, Y g:ia') }}</p>
                            @if ($payment->receipt_upload_path)
                                <a href="{{ Storage::url($payment->receipt_upload_path) }}" class="mt-1 inline-block text-xs font-medium text-gray-600 hover:text-gray-900" target="_blank" rel="noopener">View receipt</a>
                            @endif
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
@endsection
