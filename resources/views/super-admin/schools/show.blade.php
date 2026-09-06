@extends('layouts.app')

@section('title', $school->name . ' - SchoolOS')
@section('page-title', $school->name)

@section('content')
    <div class="flex flex-wrap items-center justify-between gap-3">
        <x-button href="{{ route('super-admin.schools.index') }}" variant="ghost" size="sm">Back to schools</x-button>
        <x-badge :variant="$school->status === 'active' ? 'success' : 'danger'">{{ ucfirst($school->status) }}</x-badge>
    </div>

    <div class="grid gap-4 lg:grid-cols-[1fr_0.85fr]">
        <x-card>
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <h2 class="text-sm font-semibold text-gray-900">School profile</h2>
                    <p class="mt-1 text-sm text-gray-600">Core identity and location details for this school.</p>
                </div>
                @if ($school->logo_path)
                    <img src="{{ Storage::url($school->logo_path) }}" alt="{{ $school->name }} logo" class="h-16 w-16 rounded-md object-cover ring-1 ring-gray-200">
                @endif
            </div>
            <form method="POST" action="{{ route('super-admin.schools.update', $school) }}" enctype="multipart/form-data" class="mt-3 space-y-3">
                @csrf
                @method('PUT')
                <div class="grid gap-3 sm:grid-cols-2">
                    <x-input name="name" label="Name" :value="$school->name" required />
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
                <div>
                    <label for="logo" class="mb-1 block text-sm font-medium text-gray-700">Logo</label>
                    <input id="logo" name="logo" type="file" accept="image/*" class="block w-full text-sm text-gray-700 file:mr-3 file:rounded-md file:border-0 file:bg-gray-100 file:px-3 file:py-1.5 file:text-sm file:font-medium file:text-gray-700 hover:file:bg-gray-200">
                    @error('logo')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>
                <x-button type="submit" variant="primary" size="sm">Save changes</x-button>
            </form>
        </x-card>

        <x-card>
            <h2 class="text-sm font-semibold text-gray-900">Status</h2>
            <p class="mt-1 text-sm text-gray-600">Suspending a school blocks logins for all school-scoped users and revokes active sessions where the configured session storage supports it.</p>
            <form method="POST" action="{{ route('super-admin.schools.status', $school) }}" class="mt-4 flex flex-wrap gap-2">
                @csrf
                <input type="hidden" name="status" value="{{ $school->status === 'active' ? 'suspended' : 'active' }}">
                <x-button type="submit" :variant="$school->status === 'active' ? 'danger' : 'primary'" size="sm">
                    {{ $school->status === 'active' ? 'Suspend school' : 'Reactivate school' }}
                </x-button>
            </form>
        </x-card>
    </div>

    <x-card>
        <h2 class="text-sm font-semibold text-gray-900">Create school admin</h2>
        <form method="POST" action="{{ route('super-admin.schools.admins.store', $school) }}" class="mt-3 grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
            @csrf
            <x-input name="name" label="Name" required />
            <x-input type="email" name="email" label="Email" />
            <x-input name="mobile_no" label="Mobile" />
            <x-input type="password" name="password" label="Password (leave blank to invite instead)" />
            <div class="flex items-end">
                <x-button type="submit" variant="primary" class="w-full">Create admin</x-button>
            </div>
            <label class="sm:col-span-2 lg:col-span-5 flex items-center gap-2 text-sm text-gray-700">
                <input type="checkbox" name="invite" value="1" class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-600">
                Send an activation email instead of setting a password
            </label>
        </form>
    </x-card>

    <div class="grid gap-4 lg:grid-cols-2">
        <x-card :padded="false">
            <div class="border-b border-gray-200 px-4 py-3 sm:px-6">
                <h2 class="text-sm font-semibold text-gray-900">School admins</h2>
            </div>
            @if ($schoolAdmins->isEmpty())
                <div class="p-4 sm:p-6">
                    <x-empty-state title="No school admins" description="Create an admin account above so this school can be managed." />
                </div>
            @else
                <div class="divide-y divide-gray-100">
                    @foreach ($schoolAdmins as $admin)
                        <div class="px-4 py-3 sm:px-6">
                            <p class="text-sm font-medium text-gray-900">{{ $admin->name }}</p>
                            <p class="mt-0.5 text-xs text-gray-500">{{ $admin->email ?? $admin->mobile_no }}</p>
                        </div>
                    @endforeach
                </div>
            @endif
        </x-card>

        <x-card :padded="false">
            <div class="border-b border-gray-200 px-4 py-3 sm:px-6">
                <h2 class="text-sm font-semibold text-gray-900">Recent school activity</h2>
            </div>
            @if ($auditLogs->isEmpty())
                <div class="p-4 sm:p-6">
                    <x-empty-state title="No activity yet" description="Changes made for this school will appear here." />
                </div>
            @else
                <div class="divide-y divide-gray-100">
                    @foreach ($auditLogs as $log)
                        <div class="px-4 py-3 sm:px-6">
                            <p class="text-sm font-medium text-gray-900">{{ $log->action }}</p>
                            <p class="mt-0.5 text-xs text-gray-500">{{ $log->actor?->name ?? 'Unknown actor' }} · {{ $log->created_at?->diffForHumans() }}</p>
                        </div>
                    @endforeach
                </div>
            @endif
        </x-card>
    </div>
@endsection
