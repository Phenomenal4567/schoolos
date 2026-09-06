@extends('layouts.app')

@section('title', 'Dashboard — SchoolOS')
@section('page-title', 'Dashboard')

@section('content')
    @if (session('status'))
        <x-alert variant="success">{{ session('status') }}</x-alert>
    @endif

    @if ($errors->any())
        <x-alert variant="error">{{ $errors->first() }}</x-alert>
    @endif

    <div class="grid gap-4 sm:grid-cols-2">
        <x-card>
            <h2 class="text-sm font-semibold text-gray-900">Attendance</h2>

            @if ($todayCheckIn)
                <p class="mt-2 text-sm text-gray-500">
                    You checked in today at {{ $todayCheckIn->check_in?->format('g:i A') ?? $todayCheckIn->created_at->format('g:i A') }}.
                </p>
                <span class="mt-2 inline-block"><x-badge :status="$todayCheckIn->status">{{ ucfirst($todayCheckIn->status) }}</x-badge></span>
            @else
                <p class="mt-2 text-sm text-gray-500">You haven't checked in today.</p>
                <form method="POST" action="{{ route('staff-attendance.check-in') }}" class="mt-3">
                    @csrf
                    <x-button type="submit" size="sm">Check in</x-button>
                </form>
            @endif
        </x-card>

        <x-card>
            <h2 class="text-sm font-semibold text-gray-900">Your profile</h2>

            @if ($staffProfile)
                <ul class="mt-3 space-y-2 text-sm">
                    <li class="flex items-center justify-between">
                        <span class="text-gray-500">Qualifications</span>
                        <x-badge :variant="filled($staffProfile->qualifications) ? 'success' : 'neutral'">
                            {{ filled($staffProfile->qualifications) ? 'Set' : 'Not set' }}
                        </x-badge>
                    </li>
                    <li class="flex items-center justify-between">
                        <span class="text-gray-500">CV uploaded</span>
                        <x-badge :variant="$staffProfile->cv_path ? 'success' : 'neutral'">
                            {{ $staffProfile->cv_path ? 'Yes' : 'No' }}
                        </x-badge>
                    </li>
                    <li class="flex items-center justify-between">
                        <span class="text-gray-500">School rules acknowledged</span>
                        <x-badge :variant="$staffProfile->rules_acknowledged_at ? 'success' : 'neutral'">
                            {{ $staffProfile->rules_acknowledged_at ? 'Yes' : 'No' }}
                        </x-badge>
                    </li>
                </ul>
                <p class="mt-3 text-xs text-gray-400">Contact your school administrator to update these details.</p>
            @else
                <x-empty-state
                    title="No profile on file yet"
                    description="Your school administrator hasn't set up your staff profile yet."
                />
            @endif
        </x-card>
    </div>
@endsection
