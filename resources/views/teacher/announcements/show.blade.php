@extends('layouts.app')

@section('title', $announcement->title . ' — SchoolOS')
@section('page-title', 'Announcement')

@section('content')
    <a href="{{ route('teacher.announcements.index') }}" class="inline-flex items-center gap-1 text-sm text-gray-500 hover:text-gray-700">
        <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor"><path d="M13 15l-6-5 6-5"/></svg>
        All announcements
    </a>

    <x-card>
        <div class="flex items-start justify-between gap-3">
            <div class="min-w-0">
                <h2 class="text-base font-semibold text-gray-900">{{ $announcement->title }}</h2>
                <p class="mt-1 text-xs text-gray-500">
                    {{ $announcement->author?->name ?? 'Unknown' }}
                    &middot; {{ $announcement->published_at?->format('M j, Y g:ia') }}
                    &middot;
                    {{ $announcement->audience_type === 'school'
                        ? 'Whole school'
                        : trim(($announcement->classSection?->standard?->name ?? '') . ' ' . ($announcement->classSection?->section?->name ?? '')) }}
                </p>
            </div>
            @if ($isRead)
                <x-badge variant="success">Read</x-badge>
            @else
                <x-badge variant="neutral">New</x-badge>
            @endif
        </div>

        <p class="mt-4 whitespace-pre-line text-sm text-gray-700">{{ $announcement->body }}</p>

        @unless ($isRead)
            <form method="POST" action="{{ route('teacher.announcements.read', $announcement) }}" class="mt-4">
                @csrf
                <x-button type="submit" variant="secondary" size="sm">Mark as read</x-button>
            </form>
        @endunless
    </x-card>
@endsection
