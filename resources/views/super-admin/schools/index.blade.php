@extends('layouts.app')

@section('title', 'Schools - SchoolOS')
@section('page-title', 'Schools')

@section('content')
    <x-card>
        <h2 class="text-sm font-semibold text-gray-900">Create school</h2>
        <form method="POST" action="{{ route('super-admin.schools.store') }}" enctype="multipart/form-data" class="mt-3 space-y-3">
            @csrf
            <div class="grid gap-3 sm:grid-cols-2">
                <x-input name="name" label="Name" required />
                <x-input name="initials" label="Initials" />
            </div>
            <div class="grid gap-3 sm:grid-cols-2">
                <x-input type="email" name="email" label="Email" required />
                <x-input name="phone" label="Phone" required />
            </div>
            <div class="grid gap-3 sm:grid-cols-2">
                <x-input name="location" label="Location" />
                <x-select name="school_type" label="School type" :options="$schoolTypes" />
            </div>
            <x-input type="url" name="google_maps_url" label="Google Maps URL" />
            <div>
                <label for="logo" class="mb-1 block text-sm font-medium text-gray-700">Logo</label>
                <input id="logo" name="logo" type="file" accept="image/*" class="block w-full text-sm text-gray-700 file:mr-3 file:rounded-md file:border-0 file:bg-gray-100 file:px-3 file:py-1.5 file:text-sm file:font-medium file:text-gray-700 hover:file:bg-gray-200">
                @error('logo')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>
            <x-button type="submit" variant="primary">Create school</x-button>
        </form>
    </x-card>

    <x-card :padded="false">
        <div class="border-b border-gray-200 px-4 py-3 sm:px-6">
            <h2 class="text-sm font-semibold text-gray-900">All schools</h2>
        </div>
        @if ($schools->isEmpty())
            <div class="p-4 sm:p-6">
                <x-empty-state title="No schools yet" description="Create a school above to begin onboarding." />
            </div>
        @else
            <div class="divide-y divide-gray-100">
                @foreach ($schools as $school)
                    <a href="{{ route('super-admin.schools.show', $school) }}" class="grid gap-3 px-4 py-3 hover:bg-gray-50 sm:grid-cols-[1fr_auto_auto] sm:items-center sm:px-6">
                        <div class="min-w-0">
                            <p class="truncate text-sm font-medium text-gray-900">{{ $school->name }}</p>
                            <p class="mt-0.5 truncate text-xs text-gray-500">{{ $school->email }} · {{ $school->phone }}</p>
                            @if ($school->location)
                                <p class="mt-0.5 truncate text-xs text-gray-500">{{ $school->location }}</p>
                            @endif
                        </div>
                        <span class="text-sm text-gray-600">{{ $school->users_count }} users</span>
                        <x-badge :variant="$school->status === 'active' ? 'success' : 'danger'">{{ ucfirst($school->status) }}</x-badge>
                    </a>
                @endforeach
            </div>
        @endif
    </x-card>
@endsection
