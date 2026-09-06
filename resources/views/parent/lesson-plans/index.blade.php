@extends('layouts.app')

@section('title', 'Lesson plans — SchoolOS')
@section('page-title', 'Lesson plans')

@section('content')
    @if ($lessonPlans->isEmpty())
        <x-empty-state
            title="No lesson plans yet"
            description="Lesson plans for your child's class will appear here, most recent first."
        />
    @else
        <div class="space-y-2">
            @foreach ($lessonPlans->sortByDesc('created_at') as $lessonPlan)
                @php
                    $statusVariant = match ($lessonPlan->status) {
                        'approved' => 'success',
                        'rejected' => 'danger',
                        'submitted' => 'warning',
                        default => 'neutral',
                    };
                    $statusLabel = match ($lessonPlan->status) {
                        'approved' => 'Approved',
                        'rejected' => 'Rejected',
                        'submitted' => 'Pending review',
                        default => 'Draft',
                    };
                @endphp
                <a
                    href="{{ route('parent.lesson-plans.show', $lessonPlan) }}"
                    class="flex items-start justify-between gap-3 rounded-lg border border-gray-200 bg-white p-4 shadow-sm transition hover:border-indigo-300 hover:shadow"
                >
                    <div class="min-w-0">
                        <div class="flex items-center gap-2">
                            <p class="truncate text-sm font-semibold text-gray-900">{{ $lessonPlan->title }}</p>
                            <x-badge :variant="$statusVariant">{{ $statusLabel }}</x-badge>
                        </div>
                        <p class="mt-0.5 text-xs text-gray-500">
                            {{ $lessonPlan->teacher?->name ?? 'Unknown' }}
                            &middot; {{ $lessonPlan->subject?->name ?? '—' }}
                            &middot; {{ $lessonPlan->created_at?->format('M j, Y') }}
                        </p>
                    </div>
                    <svg class="h-4 w-4 shrink-0 text-gray-400" viewBox="0 0 20 20" fill="currentColor"><path d="M7 5l6 5-6 5"/></svg>
                </a>
            @endforeach
        </div>
    @endif
@endsection
