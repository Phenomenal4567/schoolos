@extends('layouts.app')

@section('title', 'Timetable - SchoolOS')
@section('page-title', 'Timetable')

@section('content')
    @php
        $dayOrder = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
        $grouped = $slots->groupBy('day_of_week');
    @endphp

    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <div class="flex items-center gap-2">
            @if ($isPublished)
                <x-badge variant="success">Published</x-badge>
            @else
                <x-badge variant="neutral">Draft</x-badge>
            @endif
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <x-button :href="route('admin.timetable.settings')" variant="secondary" size="sm">Settings</x-button>
            <x-button :href="route('admin.timetable.create')" variant="secondary" size="sm">Add slot</x-button>
            @if ($isPublished)
                <form method="POST" action="{{ route('admin.timetable.unpublish') }}">
                    @csrf
                    <x-button type="submit" variant="secondary" size="sm">Unpublish</x-button>
                </form>
            @else
                <form method="POST" action="{{ route('admin.timetable.publish') }}">
                    @csrf
                    <x-button type="submit" variant="primary" size="sm">Publish</x-button>
                </form>
            @endif
        </div>
    </div>

    @if ($slots->isEmpty())
        <x-empty-state
            title="No timetable slots yet"
            description="Scheduled class timetable slots will appear here."
        />
    @else
        <div class="grid gap-3 sm:grid-cols-2">
            @foreach ($dayOrder as $day)
                @continue($grouped->get($day, collect())->isEmpty())
                <x-card :padded="false">
                    <div class="border-b border-gray-200 px-4 py-2">
                        <p class="text-sm font-semibold text-gray-900">{{ ucfirst($day) }}</p>
                    </div>
                    <table class="min-w-full divide-y divide-gray-100 text-sm">
                        <tbody class="divide-y divide-gray-100">
                            @foreach ($grouped->get($day)->sortBy('period_number') as $slot)
                                <tr>
                                    <td class="px-4 py-2 text-gray-500">P{{ $slot->period_number }}</td>
                                    <td class="px-4 py-2 font-medium text-gray-900">{{ $slot->subject?->name ?? '-' }}</td>
                                    <td class="px-4 py-2 text-gray-500">
                                        {{ trim(($slot->classSection?->standard?->name ?? '') . ' ' . ($slot->classSection?->section?->name ?? '')) ?: '-' }}
                                    </td>
                                    <td class="px-4 py-2 text-gray-500">{{ $slot->teacher?->name ?? '-' }}</td>
                                    <td class="px-4 py-2 text-right whitespace-nowrap">
                                        <a href="{{ route('admin.timetable.show', $slot) }}" class="text-indigo-600 hover:text-indigo-500">
                                            View
                                        </a>
                                        <a href="{{ route('admin.timetable.edit', $slot) }}" class="ml-3 text-indigo-600 hover:text-indigo-500">
                                            Edit
                                        </a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </x-card>
            @endforeach
        </div>
    @endif
@endsection
