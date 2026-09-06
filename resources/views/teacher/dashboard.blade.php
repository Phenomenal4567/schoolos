@extends('layouts.app')

@section('title', 'Dashboard — SchoolOS')
@section('page-title', 'Dashboard')

@section('content')
    <x-card>
        <h2 class="text-sm font-semibold text-gray-900">Welcome back, {{ auth()->user()->name }}</h2>
        <p class="mt-1 text-sm text-gray-500">
            You're assigned to
            {{ $classSectionCount }} {{ \Illuminate\Support\Str::plural('class section', $classSectionCount) }}.
        </p>

        <div class="mt-4">
            <x-button :href="route('teacher.attendance.index')" variant="primary">
                Go to Attendance
            </x-button>
        </div>
    </x-card>
@endsection
