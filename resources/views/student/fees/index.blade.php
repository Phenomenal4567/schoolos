@extends('layouts.app')

@section('title', 'Fees — SchoolOS')
@section('page-title', 'Fees')

@section('content')
    @if ($fees->isEmpty())
        <x-empty-state
            title="No fees yet"
            description="Your fee assessments will appear here."
        />
    @else
        <div class="space-y-2">
            @foreach ($fees as $item)
                <a
                    href="{{ route('student.fees.show', $item) }}"
                    class="flex items-center justify-between gap-3 rounded-lg border border-gray-200 bg-white p-4 shadow-sm transition hover:border-indigo-300 hover:shadow"
                >
                    <div class="min-w-0">
                        <p class="truncate text-sm font-semibold text-gray-900">
                            {{ $item->feeCategory?->label ?? 'Fee' }}
                        </p>
                        @if ($item->due_date)
                            <p class="mt-0.5 text-xs text-gray-500">Due {{ $item->due_date->format('M j, Y') }}</p>
                        @endif
                    </div>
                    <div class="flex items-center gap-2">
                        <x-badge
                            variant="{{ match ($item->status) {
                                'paid' => 'success',
                                'partially_paid' => 'warning',
                                'void' => 'neutral',
                                default => 'danger',
                            } }}"
                        >
                            {{ ucwords(str_replace('_', ' ', $item->status)) }}
                        </x-badge>
                        <svg class="h-4 w-4 shrink-0 text-gray-400" viewBox="0 0 20 20" fill="currentColor"><path d="M7 5l6 5-6 5"/></svg>
                    </div>
                </a>
            @endforeach
        </div>
    @endif
@endsection
