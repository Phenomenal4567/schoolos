@extends('layouts.app')

@section('title', $staff->name . ' - SchoolOS')
@section('page-title', 'Staff Profile')

@section('content')
    <x-card :padded="false">
        <dl class="divide-y divide-gray-100 text-sm">
            <div class="grid grid-cols-3 gap-4 px-4 py-3 sm:px-6">
                <dt class="text-gray-500">Name</dt>
                <dd class="col-span-2 text-gray-900">{{ $staff->name }}</dd>
            </div>
            <div class="grid grid-cols-3 gap-4 px-4 py-3 sm:px-6">
                <dt class="text-gray-500">Role</dt>
                <dd class="col-span-2 text-gray-900">{{ $staff->role?->label ?? '-' }}</dd>
            </div>
            <div class="grid grid-cols-3 gap-4 px-4 py-3 sm:px-6">
                <dt class="text-gray-500">Staff ID</dt>
                <dd class="col-span-2 text-gray-900">{{ $staff->staff_id ?? '-' }}</dd>
            </div>
            <div class="grid grid-cols-3 gap-4 px-4 py-3 sm:px-6">
                <dt class="text-gray-500">Responsibilities</dt>
                <dd class="col-span-2 text-gray-900">{{ $staffProfile?->responsibilities ?? '-' }}</dd>
            </div>
            <div class="grid grid-cols-3 gap-4 px-4 py-3 sm:px-6">
                <dt class="text-gray-500">Qualifications</dt>
                <dd class="col-span-2 text-gray-900">
                    @forelse ($staffProfile?->qualifications ?? [] as $qualification)
                        <p>{{ $qualification }}</p>
                    @empty
                        -
                    @endforelse
                </dd>
            </div>
            <div class="grid grid-cols-3 gap-4 px-4 py-3 sm:px-6">
                <dt class="text-gray-500">School rules</dt>
                <dd class="col-span-2 text-gray-900">
                    @if ($staffProfile?->rules_acknowledged_at)
                        Acknowledged {{ $staffProfile->rules_acknowledged_at->format('M j, Y') }}
                    @else
                        Not yet acknowledged
                    @endif
                </dd>
            </div>
        </dl>
    </x-card>

    @if ($staff->role?->key !== 'school_admin')
        <x-card>
            <h2 class="text-sm font-semibold text-gray-900">Permissions</h2>
            <p class="mt-1 text-sm text-gray-500">
                Delegate timetable management to this staff member. They'll be able to build,
                edit, and publish the school's timetable the same as a school admin.
            </p>

            <form method="POST" action="{{ route('admin.staff.profile.permissions.update', $staff) }}" class="mt-3">
                @csrf
                @method('PUT')

                <label class="flex items-center gap-2 text-sm text-gray-700">
                    <input
                        type="checkbox"
                        name="can_manage_timetable"
                        value="1"
                        @checked($staffProfile?->can_manage_timetable)
                        class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-600"
                    >
                    Can manage timetable
                </label>

                <x-button type="submit" variant="primary" size="sm" class="mt-3">Save</x-button>
            </form>
        </x-card>
    @endif

    <x-card>
        <h2 class="text-sm font-semibold text-gray-900">Documents</h2>

        @if ($documents->isEmpty())
            <x-empty-state title="No documents on file" description="Nothing has been uploaded by this staff member yet." class="mt-2" />
        @else
            <ul class="mt-3 divide-y divide-gray-100 text-sm">
                @foreach ($documents as $document)
                    <li class="py-2">
                        <a href="{{ route('admin.staff.profile.documents.download', [$staff, $document]) }}" class="text-indigo-600 hover:underline">{{ $document->label }}</a>
                    </li>
                @endforeach
            </ul>
        @endif
    </x-card>
@endsection
