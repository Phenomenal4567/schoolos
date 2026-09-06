@extends('layouts.app')

@section('title', $profile->name . ' — SchoolOS')
@section('page-title', $profile->name)

@section('content')
    <x-card :padded="false">
        <dl class="divide-y divide-gray-100 text-sm">
            <div class="grid grid-cols-3 gap-4 px-4 py-3 sm:px-6">
                <dt class="text-gray-500">Email</dt>
                <dd class="col-span-2 text-gray-900">{{ $profile->email ?? '—' }}</dd>
            </div>
            <div class="grid grid-cols-3 gap-4 px-4 py-3 sm:px-6">
                <dt class="text-gray-500">Mobile number</dt>
                <dd class="col-span-2 text-gray-900">{{ $profile->mobile_no ?? '—' }}</dd>
            </div>
            <div class="grid grid-cols-3 gap-4 px-4 py-3 sm:px-6">
                <dt class="text-gray-500">Registration number</dt>
                <dd class="col-span-2 text-gray-900">{{ $profile->registration_number ?? '—' }}</dd>
            </div>
            <div class="grid grid-cols-3 gap-4 px-4 py-3 sm:px-6">
                <dt class="text-gray-500">Status</dt>
                <dd class="col-span-2 text-gray-900">{{ ucfirst($profile->status) }}</dd>
            </div>
        </dl>
    </x-card>

    <a href="{{ route('student.attendance') }}" class="inline-flex items-center gap-1 text-sm font-medium text-indigo-600 hover:text-indigo-500">
        View attendance
        <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor"><path d="M7 5l6 5-6 5"/></svg>
    </a>

    <p class="text-xs text-gray-400">Announcements will appear here once that module ships.</p>
@endsection
