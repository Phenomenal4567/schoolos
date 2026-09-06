@extends('layouts.app')

@section('title', 'Platform - SchoolOS')
@section('page-title', 'Platform')

@section('content')
    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <x-card>
            <p class="text-xs font-medium uppercase text-gray-500">Schools</p>
            <p class="mt-2 text-2xl font-semibold text-gray-900">{{ $schools->count() }}</p>
        </x-card>
        <x-card>
            <p class="text-xs font-medium uppercase text-gray-500">Active</p>
            <p class="mt-2 text-2xl font-semibold text-emerald-700">{{ $activeSchoolsCount }}</p>
        </x-card>
        <x-card>
            <p class="text-xs font-medium uppercase text-gray-500">Suspended</p>
            <p class="mt-2 text-2xl font-semibold text-red-700">{{ $suspendedSchoolsCount }}</p>
        </x-card>
        <x-card>
            <p class="text-xs font-medium uppercase text-gray-500">Users</p>
            <p class="mt-2 text-2xl font-semibold text-gray-900">{{ $roleCounts->sum() }}</p>
        </x-card>
    </div>

    {{-- Demo/trial length (super-admin.platform-settings.update) —
         SchoolOS Onboarding & Authentication UI, demo/trial billing
         addition. --}}
    <x-card>
        <h2 class="text-sm font-semibold text-gray-900">New school trial length</h2>
        <p class="mt-1 text-sm text-gray-500">
            How many days a school's free trial lasts when they choose "demo" instead of "pay now" at signup.
        </p>
        <form method="POST" action="{{ route('super-admin.platform-settings.update') }}" class="mt-3 flex flex-wrap items-end gap-3">
            @csrf
            @method('PUT')
            <x-input type="number" name="trial_days" label="Trial days" :value="$platformSetting->trial_days" min="1" max="365" required />
            <x-button type="submit" size="sm">Save</x-button>
        </form>
    </x-card>

    <div class="grid gap-4 lg:grid-cols-[1.4fr_1fr]">
        <x-card :padded="false">
            <div class="flex items-center justify-between gap-3 border-b border-gray-200 px-4 py-3 sm:px-6">
                <h2 class="text-sm font-semibold text-gray-900">Schools</h2>
                <x-button href="{{ route('super-admin.schools.index') }}" variant="secondary" size="sm">Manage</x-button>
            </div>
            @if ($schools->isEmpty())
                <div class="p-4 sm:p-6">
                    <x-empty-state title="No schools yet" description="Create the first school to start onboarding school administrators." />
                </div>
            @else
                <div class="divide-y divide-gray-100">
                    @foreach ($schools->take(8) as $school)
                        <a href="{{ route('super-admin.schools.show', $school) }}" class="flex items-center justify-between gap-3 px-4 py-3 hover:bg-gray-50 sm:px-6">
                            <div class="min-w-0">
                                <p class="truncate text-sm font-medium text-gray-900">{{ $school->name }}</p>
                                <p class="mt-0.5 truncate text-xs text-gray-500">{{ $school->email }} · {{ $school->users_count }} users</p>
                            </div>
                            <x-badge :variant="$school->status === 'active' ? 'success' : 'danger'">{{ ucfirst($school->status) }}</x-badge>
                        </a>
                    @endforeach
                </div>
            @endif
        </x-card>

        <x-card :padded="false">
            <div class="border-b border-gray-200 px-4 py-3 sm:px-6">
                <h2 class="text-sm font-semibold text-gray-900">Users by role</h2>
            </div>
            @if ($roleCounts->isEmpty())
                <div class="p-4 sm:p-6">
                    <x-empty-state title="No users yet" description="Users will appear here after accounts are created." />
                </div>
            @else
                <div class="divide-y divide-gray-100">
                    @foreach ($roleCounts as $role => $total)
                        <div class="flex items-center justify-between gap-3 px-4 py-3 sm:px-6">
                            <span class="text-sm text-gray-700">{{ ucwords(str_replace('_', ' ', $role)) }}</span>
                            <span class="text-sm font-semibold text-gray-900">{{ $total }}</span>
                        </div>
                    @endforeach
                </div>
            @endif
        </x-card>
    </div>

    <x-card :padded="false">
        <div class="flex items-center justify-between gap-3 border-b border-gray-200 px-4 py-3 sm:px-6">
            <h2 class="text-sm font-semibold text-gray-900">Recent platform activity</h2>
            <x-button href="{{ route('super-admin.audit-logs.index') }}" variant="ghost" size="sm">View all</x-button>
        </div>
        @if ($recentAuditLogs->isEmpty())
            <div class="p-4 sm:p-6">
                <x-empty-state title="No audit logs yet" description="Platform and school changes will be recorded here." />
            </div>
        @else
            <div class="divide-y divide-gray-100">
                @foreach ($recentAuditLogs as $log)
                    <div class="flex flex-wrap items-center justify-between gap-3 px-4 py-3 sm:px-6">
                        <div class="min-w-0">
                            <p class="text-sm font-medium text-gray-900">{{ $log->action }}</p>
                            <p class="mt-0.5 text-xs text-gray-500">
                                {{ $log->school?->name ?? 'Platform' }} · {{ $log->actor?->name ?? 'Unknown actor' }}
                            </p>
                        </div>
                        <span class="text-xs text-gray-500">{{ $log->created_at?->diffForHumans() }}</span>
                    </div>
                @endforeach
            </div>
        @endif
    </x-card>
@endsection
