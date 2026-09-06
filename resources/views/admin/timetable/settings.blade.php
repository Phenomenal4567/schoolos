@extends('layouts.app')

@section('title', 'Timetable Settings - SchoolOS')
@section('page-title', 'Timetable Settings')

@section('content')
    @php
        $defaultPeriods = [
            ['period_number' => 1, 'start_time' => '08:00', 'end_time' => '08:45'],
            ['period_number' => 2, 'start_time' => '08:45', 'end_time' => '09:30'],
            ['period_number' => 3, 'start_time' => '09:30', 'end_time' => '10:15'],
            ['period_number' => 4, 'start_time' => '10:30', 'end_time' => '11:15'],
            ['period_number' => 5, 'start_time' => '11:15', 'end_time' => '12:00'],
            ['period_number' => 6, 'start_time' => '12:00', 'end_time' => '12:45'],
        ];

        $configuredPeriods = old('periods', $periods->isNotEmpty()
            ? $periods->map(fn ($period) => [
                'period_number' => $period->period_number,
                'start_time' => \Illuminate\Support\Carbon::parse($period->start_time)->format('H:i'),
                'end_time' => \Illuminate\Support\Carbon::parse($period->end_time)->format('H:i'),
            ])->all()
            : $defaultPeriods);

        $allDays = [
            'monday' => 'Monday',
            'tuesday' => 'Tuesday',
            'wednesday' => 'Wednesday',
            'thursday' => 'Thursday',
            'friday' => 'Friday',
            'saturday' => 'Saturday',
            'sunday' => 'Sunday',
        ];

        $selectedDays = old('working_days', $workingDays);
    @endphp

    <x-card :padded="false">
        <div class="border-b border-gray-200 px-4 py-3 sm:px-6">
            <h2 class="text-sm font-semibold text-gray-900">Periods and working days</h2>
            <p class="mt-0.5 text-sm text-gray-500">
                Set each period's start/end time and pick which days the timetable runs on.
            </p>
        </div>

        <form method="POST" action="{{ route('admin.timetable.settings.update') }}" class="space-y-6 p-4 sm:p-6">
            @csrf
            @method('PUT')

            <div>
                <p class="mb-2 text-sm font-medium text-gray-700">Periods</p>
                <div class="space-y-2">
                    @foreach ($configuredPeriods as $index => $period)
                        <div class="grid grid-cols-3 gap-2">
                            <x-input
                                type="number"
                                name="periods[{{ $index }}][period_number]"
                                label="Period #"
                                value="{{ $period['period_number'] ?? '' }}"
                                required
                            />
                            <x-input
                                type="time"
                                name="periods[{{ $index }}][start_time]"
                                label="Start"
                                value="{{ $period['start_time'] ?? '' }}"
                                required
                            />
                            <x-input
                                type="time"
                                name="periods[{{ $index }}][end_time]"
                                label="End"
                                value="{{ $period['end_time'] ?? '' }}"
                                required
                            />
                        </div>
                    @endforeach
                </div>
                @error('periods')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <p class="mb-2 text-sm font-medium text-gray-700">Working days</p>
                <div class="flex flex-wrap gap-4">
                    @foreach ($allDays as $value => $label)
                        <label class="flex items-center gap-2 text-sm text-gray-700">
                            <input
                                type="checkbox"
                                name="working_days[]"
                                value="{{ $value }}"
                                @checked(in_array($value, $selectedDays ?? [], true))
                                class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-600"
                            >
                            {{ $label }}
                        </label>
                    @endforeach
                </div>
                @error('working_days')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <x-button type="submit" variant="primary">Save settings</x-button>
        </form>
    </x-card>
@endsection
