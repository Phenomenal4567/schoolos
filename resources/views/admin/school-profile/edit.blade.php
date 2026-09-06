@extends('layouts.app')

@section('title', 'School Profile - SchoolOS')
@section('page-title', 'School Profile')

@section('content')
    <x-card>
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <h2 class="text-sm font-semibold text-gray-900">Profile details</h2>
                <p class="mt-1 text-sm text-gray-600">These details appear across school-facing records, ID cards, and admissions.</p>
            </div>
            @if ($school->logo_path)
                <img src="{{ Storage::url($school->logo_path) }}" alt="{{ $school->name }} logo" class="h-16 w-16 rounded-md object-cover ring-1 ring-gray-200">
            @endif
        </div>

        <form method="POST" action="{{ route('admin.school-profile.update') }}" enctype="multipart/form-data" class="mt-4 space-y-4">
            @csrf
            @method('PUT')

            <div class="grid gap-3 sm:grid-cols-2">
                <x-input name="name" label="School name" :value="$school->name" required />
                <x-input name="initials" label="Initials" :value="$school->initials" />
            </div>

            <div class="grid gap-3 sm:grid-cols-2">
                <x-input type="email" name="email" label="Email" :value="$school->email" required />
                <x-input name="phone" label="Phone" :value="$school->phone" required />
            </div>

            <div class="grid gap-3 sm:grid-cols-2">
                <x-input name="location" label="Location" :value="$school->location" />
                <x-select name="school_type" label="School type" :options="$schoolTypes" :value="$school->school_type" />
            </div>

            <x-input type="url" name="google_maps_url" label="Google Maps URL" :value="$school->google_maps_url" />

            <div class="grid gap-3 sm:grid-cols-2">
                <x-input
                    type="number"
                    name="fee_overdue_reminder_days"
                    label="Repeat overdue fee reminders every (days)"
                    :value="$school->fee_overdue_reminder_days"
                    min="1"
                    max="365"
                    required
                />
            </div>

            <div>
                <label for="logo" class="mb-1 block text-sm font-medium text-gray-700">Logo</label>
                <input id="logo" name="logo" type="file" accept="image/*" class="block w-full text-sm text-gray-700 file:mr-3 file:rounded-md file:border-0 file:bg-gray-100 file:px-3 file:py-1.5 file:text-sm file:font-medium file:text-gray-700 hover:file:bg-gray-200">
                @error('logo')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <x-button type="submit" variant="primary">Save profile</x-button>
        </form>
    </x-card>
@endsection
