@extends('layouts.app')

@section('title', 'Timetable — SchoolOS')
@section('page-title', 'Timetable')

@section('content')
    @php
        $dayOrder = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
        $grouped = $slots->groupBy('day_of_week');
    @endphp

    @if ($slots->isEmpty())
        <x-empty-state
            title="No timetable slots yet"
            description="Your child's timetable will appear here once slots are scheduled."
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
                                    <td class="px-4 py-2 font-medium text-gray-900">{{ $slot->subject?->name ?? '—' }}</td>
                                    <td class="px-4 py-2 text-gray-500">{{ $slot->teacher?->name ?? '—' }}</td>
                                    <td class="px-4 py-2 text-right">
                                        <a href="{{ route('parent.timetable.show', $slot) }}" class="text-indigo-600 hover:text-indigo-500">
                                            View
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
