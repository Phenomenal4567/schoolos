@extends('layouts.app')

@section('title', 'School Calendar - SchoolOS')
@section('page-title', 'School Calendar')

@section('content')
    @if ($events->isEmpty())
        <x-empty-state
            title="No calendar events yet"
            description="Term dates, holidays, and school activities will appear here once published."
        />
    @else
        <div class="space-y-2">
            @foreach ($events as $event)
                <a
                    href="{{ route('teacher.calendar-events.show', $event) }}"
                    class="flex items-start justify-between gap-3 rounded-lg border border-gray-200 bg-white p-4 shadow-sm transition hover:border-indigo-300 hover:shadow"
                >
                    <div class="min-w-0">
                        <div class="flex items-center gap-2">
                            <p class="truncate text-sm font-semibold text-gray-900">{{ $event->title }}</p>
                            <x-badge variant="neutral">{{ str($event->event_type)->headline() }}</x-badge>
                        </div>
                        <p class="mt-0.5 text-xs text-gray-500">
                            {{ $event->start_date?->format('M j, Y') }}
                            @if ($event->end_date)
                                &ndash; {{ $event->end_date->format('M j, Y') }}
                            @endif
                        </p>
                    </div>
                    <svg class="h-4 w-4 shrink-0 text-gray-400" viewBox="0 0 20 20" fill="currentColor"><path d="M7 5l6 5-6 5"/></svg>
                </a>
            @endforeach
        </div>
    @endif
@endsection
