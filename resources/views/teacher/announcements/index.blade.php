@extends('layouts.app')

@section('title', 'Announcements — SchoolOS')
@section('page-title', 'Announcements')

@section('content')
    <x-card>
        <h2 class="text-sm font-semibold text-gray-900">Post to my class</h2>
        <form method="POST" action="{{ route('teacher.announcements.store') }}" class="mt-3 space-y-3">
            @csrf
            <x-select
                name="class_section_id"
                label="Class section"
                :options="$classSections->mapWithKeys(fn ($cs) => [$cs->id => trim(($cs->standard?->name ?? '') . ' ' . ($cs->section?->name ?? ''))])"
                required
            />
            <x-input name="title" label="Title" required />
            <div>
                <label for="body" class="mb-1 block text-sm font-medium text-gray-700">
                    Body <span class="text-red-500">*</span>
                </label>
                <textarea
                    id="body"
                    name="body"
                    rows="3"
                    required
                    class="block w-full rounded-md border-0 py-1.5 px-2.5 text-sm text-gray-900 ring-1 ring-inset ring-gray-300 placeholder:text-gray-400 focus:outline-none focus:ring-2 focus:ring-indigo-600"
                ></textarea>
            </div>
            <x-button type="submit" variant="primary" size="sm">Post announcement</x-button>
        </form>
    </x-card>

    @if ($announcements->isEmpty())
        <x-empty-state
            title="No announcements yet"
            description="Announcements visible to you will appear here, most recent first."
        />
    @else
        <div class="space-y-2">
            @foreach ($announcements as $announcement)
                <a
                    href="{{ route('teacher.announcements.show', $announcement) }}"
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
