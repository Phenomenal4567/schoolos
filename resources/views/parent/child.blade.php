@extends('layouts.app')

@section('title', $child->name . ' — SchoolOS')
@section('page-title', $child->name)

@section('content')
    <a href="{{ route('parent.dashboard') }}" class="inline-flex items-center gap-1 text-sm text-gray-500 hover:text-gray-700">
        <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor"><path d="M13 15l-6-5 6-5"/></svg>
        My children
    </a>

    <x-card :padded="false">
        <dl class="divide-y divide-gray-100 text-sm">
            <div class="grid grid-cols-3 gap-4 px-4 py-3 sm:px-6">
                <dt class="text-gray-500">Email</dt>
                <dd class="col-span-2 text-gray-900">{{ $child->email ?? '—' }}</dd>
            </div>
            <div class="grid grid-cols-3 gap-4 px-4 py-3 sm:px-6">
                <dt class="text-gray-500">Mobile number</dt>
                <dd class="col-span-2 text-gray-900">{{ $child->mobile_no ?? '—' }}</dd>
            </div>
            <div class="grid grid-cols-3 gap-4 px-4 py-3 sm:px-6">
                <dt class="text-gray-500">Registration number</dt>
                <dd class="col-span-2 text-gray-900">{{ $child->registration_number ?? '—' }}</dd>
            </div>
            <div class="grid grid-cols-3 gap-4 px-4 py-3 sm:px-6">
                <dt class="text-gray-500">Status</dt>
                <dd class="col-span-2 text-gray-900">{{ ucfirst($child->status) }}</dd>
            </div>
        </dl>
    </x-card>

    <a href="{{ route('parent.children.attendance', $child->id) }}" class="inline-flex items-center gap-1 text-sm font-medium text-indigo-600 hover:text-indigo-500">
        View attendance
        <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor"><path d="M7 5l6 5-6 5"/></svg>
    </a>

    <a href="{{ route('parent.announcements.index') }}" class="inline-flex items-center gap-1 text-sm font-medium text-indigo-600 hover:text-indigo-500">
        View announcements
        <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor"><path d="M7 5l6 5-6 5"/></svg>
    </a>
@endsection
