@extends('layouts.app')

@section('title', 'Audit logs - SchoolOS')
@section('page-title', 'Audit logs')

@section('content')
    <x-card>
        <form method="GET" action="{{ route('super-admin.audit-logs.index') }}" class="flex flex-wrap items-end gap-3">
            <x-select
                name="school_id"
                label="School"
                :options="['' => 'All schools'] + $schools->pluck('name', 'id')->all()"
                :value="$selectedSchoolId"
            />
            <x-button type="submit" variant="secondary" size="sm">Filter</x-button>
            <x-button href="{{ route('super-admin.audit-logs.index') }}" variant="ghost" size="sm">Clear</x-button>
        </form>
    </x-card>

    <x-card :padded="false">
        <div class="border-b border-gray-200 px-4 py-3 sm:px-6">
            <h2 class="text-sm font-semibold text-gray-900">Activity</h2>
        </div>
        @if ($logs->isEmpty())
            <div class="p-4 sm:p-6">
                <x-empty-state title="No audit logs" description="Matching activity will appear here." />
            </div>
        @else
            <div class="divide-y divide-gray-100">
                @foreach ($logs as $log)
                    <div class="grid gap-2 px-4 py-3 sm:grid-cols-[1fr_auto] sm:px-6">
                        <div class="min-w-0">
                            <p class="text-sm font-medium text-gray-900">{{ $log->action }}</p>
                            <p class="mt-0.5 text-xs text-gray-500">
                                {{ $log->school?->name ?? 'Platform' }} · {{ $log->actor?->name ?? 'Unknown actor' }}
                            </p>
                            <p class="mt-1 text-xs text-gray-500">{{ $log->entity_type }} #{{ $log->entity_id }}</p>
                        </div>
                        <span class="text-xs text-gray-500">{{ $log->created_at?->format('M j, Y g:i A') }}</span>
                    </div>
                @endforeach
            </div>
            <div class="border-t border-gray-100 px-4 py-3 sm:px-6">
                {{ $logs->links() }}
            </div>
        @endif
    </x-card>
@endsection
