@extends('layouts.app')

@section('title', 'Edit Timetable Slot - SchoolOS')
@section('page-title', 'Edit Timetable Slot')

@section('content')
    <x-card>
        <form method="POST" action="{{ route('admin.timetable.update', $slot) }}" class="space-y-4">
            @csrf
            @method('PUT')

            <x-select
                name="class_section_id"
                label="Class section"
                :options="$classSections->mapWithKeys(fn ($classSection) => [
                    $classSection->id => trim(($classSection->standard?->name ?? '') . ' ' . ($classSection->section?->name ?? '')),
                ])"
                :value="old('class_section_id', $slot->class_section_id)"
                required
            />

            <x-select
                name="subject_id"
                label="Subject"
                :options="$subjects->pluck('name', 'id')"
                :value="old('subject_id', $slot->subject_id)"
                required
            />

            <x-select
                name="teacher_id"
                label="Teacher"
                :options="$teachers->pluck('name', 'id')"
                :value="old('teacher_id', $slot->teacher_id)"
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
                :value="old('day_of_week', $slot->day_of_week)"
                required
            />

            <x-input
                name="period_number"
                label="Period number"
                type="number"
                :value="old('period_number', $slot->period_number)"
                required
            />

            <x-input
                name="room"
                label="Room (optional)"
                :value="old('room', $slot->room)"
            />

            <div class="flex items-center gap-3">
                <x-button type="submit" variant="primary">Save changes</x-button>
                <x-button :href="route('admin.timetable.show', $slot)" variant="secondary">Cancel</x-button>
            </div>
        </form>
    </x-card>

    <form method="POST" action="{{ route('admin.timetable.destroy', $slot) }}" class="mt-4" onsubmit="return confirm('Remove this timetable slot?');">
        @csrf
        @method('DELETE')
        <x-button type="submit" variant="danger">Delete slot</x-button>
    </form>
@endsection
