@extends('layouts.app')

@section('title', 'Add Timetable Slot - SchoolOS')
@section('page-title', 'Add Timetable Slot')

@section('content')
    <x-card>
        <form method="POST" action="{{ route('admin.timetable.store') }}" class="space-y-4">
            @csrf

            <x-select
                name="class_section_id"
                label="Class section"
                :options="$classSections->mapWithKeys(fn ($classSection) => [
                    $classSection->id => trim(($classSection->standard?->name ?? '') . ' ' . ($classSection->section?->name ?? '')),
                ])"
                :value="old('class_section_id')"
                required
            />

            <x-select
                name="subject_id"
                label="Subject"
                :options="$subjects->pluck('name', 'id')"
                :value="old('subject_id')"
                required
            />

            <x-select
                name="teacher_id"
                label="Teacher"
                :options="$teachers->pluck('name', 'id')"
                :value="old('teacher_id')"
                required
            />

            <x-select
                name="day_of_week"
                label="Day"
                :options="[
                    'monday' => 'Monday',
                    'tuesday' => 'Tuesday',
                    'wednesday' => 'Wednesday',
                    'thursday' => 'Thursday',
                    'friday' => 'Friday',
                    'saturday' => 'Saturday',
                    'sunday' => 'Sunday',
                ]"
                :value="old('day_of_week')"
                required
            />

            <x-input
                name="period_number"
                label="Period number"
                type="number"
                :value="old('period_number')"
                required
            />

            <x-input
                name="room"
                label="Room (optional)"
                :value="old('room')"
            />

            <div class="flex items-center gap-3">
                <x-button type="submit" variant="primary">Add slot</x-button>
                <x-button :href="route('admin.timetable.index')" variant="secondary">Cancel</x-button>
            </div>
        </form>
    </x-card>
@endsection
