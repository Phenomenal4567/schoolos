@extends('layouts.app')

@section('title', $event->title . ' - SchoolOS')
@section('page-title', 'Calendar Event')

@section('content')
    <a href="{{ route('teacher.calendar-events.index') }}" class="inline-flex items-center gap-1 text-sm text-gray-500 hover:text-gray-700">
        <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor"><path d="M13 15l-6-5 6-5"/></svg>
        School calendar
    </a>

    <x-card>
        <div class="flex items-start justify-between gap-3">
            <div class="min-w-0">
                <h2 class="text-base font-semibold text-gray-900">{{ $event->title }}</h2>
                <p class="mt-1 text-xs text-gray-500">
                    {{ $event->start_date?->format('M j, Y') }}
                    @if ($event->end_date)
                        &ndash; {{ $event->end_date->format('M j, Y') }}
                    @endif
                </p>
            </div>
            <x-badge variant="neutral">{{ str($event->event_type)->headline() }}</x-badge>
        </div>
    </x-card>
@endsection
