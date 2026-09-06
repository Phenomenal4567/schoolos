@extends('layouts.app')

@section('title', 'Timetable slot — SchoolOS')
@section('page-title', 'Timetable slot')

@section('content')
    <a href="{{ route('teacher.timetable.index') }}" class="inline-flex items-center gap-1 text-sm text-gray-500 hover:text-gray-700">
        <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor"><path d="M13 15l-6-5 6-5"/></svg>
        All timetable slots
    </a>

    <x-card :padded="false">
        <dl class="divide-y divide-gray-100 text-sm">
            <div class="grid grid-cols-3 gap-4 px-4 py-3 sm:px-6">
                <dt class="text-gray-500">Day</dt>
                <dd class="col-span-2 text-gray-900">{{ ucfirst($slot->day_of_week) }}</dd>
            </div>
            <div class="grid grid-cols-3 gap-4 px-4 py-3 sm:px-6">
                <dt class="text-gray-500">Period</dt>
                <dd class="col-span-2 text-gray-900">{{ $slot->period_number }}</dd>
            </div>
            <div class="grid grid-cols-3 gap-4 px-4 py-3 sm:px-6">
                <dt class="text-gray-500">Subject</dt>
                <dd class="col-span-2 text-gray-900">{{ $slot->subject?->name ?? '—' }}</dd>
            </div>
            <div class="grid grid-cols-3 gap-4 px-4 py-3 sm:px-6">
                <dt class="text-gray-500">Class</dt>
                <dd class="col-span-2 text-gray-900">
                    {{ trim(($slot->classSection?->standard?->name ?? '') . ' ' . ($slot->classSection?->section?->name ?? '')) ?: '—' }}
                </dd>
            </div>
            <div class="grid grid-cols-3 gap-4 px-4 py-3 sm:px-6">
                <dt class="text-gray-500">Teacher</dt>
                <dd class="col-span-2 text-gray-900">{{ $slot->teacher?->name ?? '—' }}</dd>
            </div>
        </dl>
    </x-card>
@endsection
