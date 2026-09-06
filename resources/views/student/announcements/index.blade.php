@extends('layouts.app')

@section('title', 'Announcements — SchoolOS')
@section('page-title', 'Announcements')

@section('content')
    @if ($announcements->isEmpty())
        <x-empty-state
            title="No announcements yet"
            description="Announcements visible to you will appear here, most recent first."
        />
    @else
        <div class="space-y-2">
            @foreach ($announcements as $announcement)
                <a
                    href="{{ route('student.announcements.show', $announcement) }}"
                    class="flex items-start justify-between gap-3 rounded-lg border border-gray-200 bg-white p-4 shadow-sm transition hover:border-indigo-300 hover:shadow"
                >
                    <div class="min-w-0">
                        <div class="flex items-center gap-2">
                            <p class="truncate text-sm font-semibold text-gray-900">{{ $announcement->title }}</p>
                            @unless (in_array($announcement->id, $readIds, true))
                                <x-badge variant="neutral">New</x-badge>
                            @endunless
                        </div>
                        <p class="mt-0.5 text-xs text-gray-500">
                            {{ $announcement->author?->name ?? 'Unknown' }}
                            &middot; {{ $announcement->published_at?->format('M j, Y') }}
                            &middot;
                            {{ $announcement->audience_type === 'school'
                                ? 'Whole school'
                                : trim(($announcement->classSection?->standard?->name ?? '') . ' ' . ($announcement->classSection?->section?->name ?? '')) }}
                        </p>
                    </div>
                    <svg class="h-4 w-4 shrink-0 text-gray-400" viewBox="0 0 20 20" fill="currentColor"><path d="M7 5l6 5-6 5"/></svg>
                </a>
            @endforeach
        </div>

        <div class="mt-4">
            {{ $announcements->links() }}
        </div>
    @endif
@endsection
