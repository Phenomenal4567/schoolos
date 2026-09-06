@extends('layouts.app')

@section('title', 'Fee - SchoolOS')
@section('page-title', 'Fee')

@section('content')
    <a href="{{ route('student.fees.index') }}" class="inline-flex items-center gap-1 text-sm text-gray-500 hover:text-gray-700">
        <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor"><path d="M13 15l-6-5 6-5"/></svg>
        All fees
    </a>

    <x-card>
        <div class="flex items-start justify-between gap-3">
            <h2 class="text-base font-semibold text-gray-900">{{ $assessment->feeCategory?->label ?? 'Fee' }}</h2>
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

        <p class="mt-4 text-sm text-gray-600">Balance and payment details are available in the parent fee portal.</p>
    </x-card>
@endsection
