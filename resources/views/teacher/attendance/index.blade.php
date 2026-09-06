@extends('layouts.app')

@section('title', 'Attendance — SchoolOS')
@section('page-title', 'Attendance')

@section('content')
    @if ($classSections->isEmpty())
        <x-empty-state
            title="No classes assigned yet"
            description="You aren't assigned to any class section. Contact your school administrator if this looks wrong."
        />
    @else
        <div class="grid gap-3 sm:grid-cols-2">
            @foreach ($classSections as $classSection)
                <a
                    href="{{ route('teacher.attendance.show', $classSection) }}"
                    class="flex items-center justify-between rounded-lg border border-gray-200 bg-white p-4 shadow-sm transition hover:border-indigo-300 hover:shadow"
                >
                    <div>
                        <p class="text-sm font-semibold text-gray-900">
                            {{ $classSection->standard?->name }} &middot; {{ $classSection->section?->name }}
                        </p>
                        <p class="mt-0.5 text-xs text-gray-500">
                            {{ $classSection->student_enrollments_count }}
                            {{ \Illuminate\Support\Str::plural('student', $classSection->student_enrollments_count) }} enrolled
                        </p>
                    </div>
                    <svg class="h-4 w-4 shrink-0 text-gray-400" viewBox="0 0 20 20" fill="currentColor"><path d="M7 5l6 5-6 5"/></svg>
                </a>
            @endforeach
        </div>
    @endif
@endsection
