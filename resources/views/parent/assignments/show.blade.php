@extends('layouts.app')

@section('title', $assignment->title . ' — SchoolOS')
@section('page-title', 'Assignment')

@section('content')
    <a href="{{ route('parent.assignments.index') }}" class="inline-flex items-center gap-1 text-sm text-gray-500 hover:text-gray-700">
        <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor"><path d="M13 15l-6-5 6-5"/></svg>
        All assignments
    </a>

    <x-card :padded="false">
        <dl class="divide-y divide-gray-100 text-sm">
            <div class="grid grid-cols-3 gap-4 px-4 py-3 sm:px-6">
                <dt class="text-gray-500">Title</dt>
                <dd class="col-span-2 text-gray-900">{{ $assignment->title }}</dd>
            </div>
            @if ($assignment->description)
                <div class="grid grid-cols-3 gap-4 px-4 py-3 sm:px-6">
                    <dt class="text-gray-500">Description</dt>
                    <dd class="col-span-2 whitespace-pre-line text-gray-900">{{ $assignment->description }}</dd>
                </div>
            @endif
            <div class="grid grid-cols-3 gap-4 px-4 py-3 sm:px-6">
                <dt class="text-gray-500">Teacher</dt>
                <dd class="col-span-2 text-gray-900">{{ $assignment->teacher?->name ?? '—' }}</dd>
            </div>
            <div class="grid grid-cols-3 gap-4 px-4 py-3 sm:px-6">
                <dt class="text-gray-500">Subject</dt>
                <dd class="col-span-2 text-gray-900">{{ $assignment->subject?->name ?? '—' }}</dd>
            </div>
            <div class="grid grid-cols-3 gap-4 px-4 py-3 sm:px-6">
                <dt class="text-gray-500">Due date</dt>
                <dd class="col-span-2 text-gray-900">{{ $assignment->due_date?->format('M j, Y') ?? '—' }}</dd>
            </div>
        </dl>
    </x-card>
@endsection
