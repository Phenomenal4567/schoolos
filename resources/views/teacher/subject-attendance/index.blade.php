@extends('layouts.app')

@section('title', 'Subject Attendance — SchoolOS')
@section('page-title', 'Subject Attendance')

@section('content')
    <p class="text-sm text-gray-500">
        Take attendance for a specific subject period you teach, and record the topic covered.
    </p>

    @if ($slots->isEmpty())
        <x-empty-state
            title="No subject periods assigned yet"
            description="You aren't listed as the teacher for any timetable slot. Contact your school administrator if this looks wrong."
        />
    @else
        <div class="grid gap-3 sm:grid-cols-2">
            @foreach ($slots as $slot)
                <a
                    href="{{ route('teacher.subject-attendance.show', $slot) }}"
                    class="flex items-center justify-between rounded-lg border border-gray-200 bg-white p-4 shadow-sm transition hover:border-indigo-300 hover:shadow"
                >
                    <div>
                        <p class="text-sm font-semibold text-gray-900">{{ $slot->subject?->name }}</p>
                        <p class="mt-0.5 text-xs text-gray-500">
                            {{ $slot->classSection?->standard?->name }} &middot; {{ $slot->classSection?->section?->name }}
                        </p>
                        <p class="mt-0.5 text-xs text-gray-400">
                            {{ ucfirst($slot->day_of_week) }} &middot; Period {{ $slot->period_number }}
                        </p>
                    </div>
                    <svg class="h-4 w-4 shrink-0 text-gray-400" viewBox="0 0 20 20" fill="currentColor"><path d="M7 5l6 5-6 5"/></svg>
                </a>
            @endforeach
        </div>
    @endif
@endsection
