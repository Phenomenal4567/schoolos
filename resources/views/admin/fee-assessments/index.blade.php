@extends('layouts.app')

@section('title', 'Fee Assessments — SchoolOS')
@section('page-title', 'Fees & Assessments')

@section('content')
    <x-card :padded="false">
        <div class="border-b border-gray-200 px-4 py-3 sm:px-6">
            <h2 class="text-sm font-semibold text-gray-900">Fee assessments</h2>
        </div>
        @if ($assessments->isEmpty())
            <div class="p-4 sm:p-6">
                <x-empty-state
                    title="No fee assessments yet"
                    description="Assessments you create below will appear here."
                />
            </div>
        @else
            <div class="divide-y divide-gray-100">
                @foreach ($assessments as $assessment)
                    <a
                        href="{{ route('admin.fee-assessments.show', $assessment) }}"
                        class="flex items-center justify-between gap-3 px-4 py-3 transition hover:bg-gray-50 sm:px-6"
                    >
                        <div class="min-w-0">
                            <p class="truncate text-sm font-semibold text-gray-900">
                                {{ $assessment->student?->name ?? 'Unknown student' }}
                            </p>
                            <p class="mt-0.5 truncate text-sm text-gray-600">
                                {{ $assessment->feeCategory?->label ?? 'Unknown category' }}
                                &middot; {{ number_format((float) $assessment->amount_due, 2) }} due
                            </p>
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
                    </a>
                @endforeach
            </div>
        @endif
    </x-card>

    <div class="grid gap-4 lg:grid-cols-3">
        <x-card>
            <h2 class="text-sm font-semibold text-gray-900">Create assessment</h2>
            <form method="POST" action="{{ route('admin.fee-assessments.store') }}" class="mt-3 space-y-3">
                @csrf
                <x-select
                    name="student_id"
                    label="Student"
                    :options="$students->pluck('name', 'id')"
                    required
                />
                <x-select
                    name="academic_year_id"
                    label="Academic year"
                    :options="$academicYears->pluck('label', 'id')"
                    required
                />
                <x-select
                    name="fee_category_id"
                    label="Fee category"
                    :options="$feeCategories->pluck('label', 'id')"
                    required
                />
                <x-input type="number" name="base_amount" label="Base amount" step="0.01" required />
                <x-input type="date" name="due_date" label="Due date" />
                <p class="pt-1 text-xs font-medium text-gray-500">Optional discount</p>
                <x-select
                    name="discount_type"
                    label="Discount type"
                    :options="['' => 'No discount', 'individual' => 'Individual', 'percentage' => 'Percentage', 'fixed' => 'Fixed amount']"
                />
                <x-input type="number" name="discount_value" label="Discount value" step="0.01" />
                <x-input name="discount_reason" label="Discount reason" />
                <x-button type="submit" variant="primary" size="sm">Create assessment</x-button>
            </form>
        </x-card>

        <x-card>
            <h2 class="text-sm font-semibold text-gray-900">Grant scholarship</h2>
            {{-- student_id/academic_year_id/fee_category_id repeat the
                 assessment form's own field names above — useOld="false"
                 so old() can't cross-populate the wrong form on a
                 validation error, per x-select's own doc comment. --}}
            <form method="POST" action="{{ route('admin.scholarships.store') }}" class="mt-3 space-y-3">
                @csrf
                <x-select
                    name="student_id"
                    label="Student"
                    :options="$students->pluck('name', 'id')"
                    :useOld="false"
                    required
                />
                <x-select
                    name="academic_year_id"
                    label="Academic year"
                    :options="$academicYears->pluck('label', 'id')"
                    :useOld="false"
                    required
                />
                <x-select
                    name="type"
                    label="Type"
                    :options="['full' => 'Full', 'partial' => 'Partial (%)', 'specific_exemption' => 'Specific category exemption']"
                    required
                />
                <x-input type="number" name="value" label="Value (% for partial)" step="0.01" />
                <x-select
                    name="fee_category_id"
                    label="Fee category (specific exemption only)"
                    :options="collect(['' => 'Not applicable'])->union($feeCategories->pluck('label', 'id'))"
                    :useOld="false"
                />
                <x-input name="reason" label="Reason" />
                <x-button type="submit" variant="primary" size="sm">Grant scholarship</x-button>
            </form>
        </x-card>

        <x-card>
            <h2 class="text-sm font-semibold text-gray-900">Add fee category</h2>
            <form method="POST" action="{{ route('admin.fee-categories.store') }}" class="mt-3 space-y-3">
                @csrf
                <x-input name="key" label="Key" placeholder="e.g. tuition" required />
                <x-input name="label" label="Label" placeholder="e.g. Tuition" required />
                <x-button type="submit" variant="primary" size="sm">Add category</x-button>
            </form>

            @if ($feeCategories->isNotEmpty())
                <div class="mt-4 border-t border-gray-100 pt-3">
                    <p class="text-xs font-medium text-gray-500">Existing categories</p>
                    <ul class="mt-2 space-y-1">
                        @foreach ($feeCategories as $category)
                            <li class="text-sm text-gray-700">{{ $category->label }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </x-card>
    </div>
@endsection
