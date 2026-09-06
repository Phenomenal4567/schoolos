@extends('layouts.app')

@section('title', 'Assignments — SchoolOS')
@section('page-title', 'Assignments')

@section('content')
    <x-card>
        <h2 class="text-sm font-semibold text-gray-900">New assignment</h2>
        <form method="POST" action="{{ route('teacher.assignments.store') }}" class="mt-3 space-y-3">
            @csrf
            <div class="grid gap-3 sm:grid-cols-2">
                <x-select
                    name="class_section_id"
                    label="Class section"
                    :options="$classSections->mapWithKeys(fn ($cs) => [$cs->id => trim(($cs->standard?->name ?? '') . ' ' . ($cs->section?->name ?? ''))])"
                    required
                />
                <x-select name="subject_id" label="Subject" :options="$subjects->pluck('name', 'id')" required />
            </div>
            <x-input name="title" label="Title" required />
            <div>
                <label for="description" class="mb-1 block text-sm font-medium text-gray-700">Description</label>
                <textarea
                    id="description"
                    name="description"
                    rows="3"
                    class="block w-full rounded-md border-0 py-1.5 px-2.5 text-sm text-gray-900 ring-1 ring-inset ring-gray-300 placeholder:text-gray-400 focus:outline-none focus:ring-2 focus:ring-indigo-600"
                >{{ old('description') }}</textarea>
            </div>
            <x-input type="date" name="due_date" label="Due date" />
            <x-button type="submit" variant="primary" size="sm">Create assignment</x-button>
        </form>
    </x-card>

    @if ($assignments->isEmpty())
        <x-empty-state
            title="No assignments yet"
            description="Assignments you create above will appear here, most recent first."
        />
    @else
        <div class="space-y-2">
            @foreach ($assignments->sortByDesc('created_at') as $assignment)
                <a
                    href="{{ route('teacher.assignments.show', $assignment) }}"
                    class="flex items-start justify-between gap-3 rounded-lg border border-gray-200 bg-white p-4 shadow-sm transition hover:border-indigo-300 hover:shadow"
                >
                    <div class="min-w-0">
                        <p class="truncate text-sm font-semibold text-gray-900">{{ $assignment->title }}</p>
                        <p class="mt-0.5 text-xs text-gray-500">
                            {{ $assignment->subject?->name ?? '—' }}
                            &middot;
                            {{ trim(($assignment->classSection?->standard?->name ?? '') . ' ' . ($assignment->classSection?->section?->name ?? '')) }}
                            @if ($assignment->due_date)
                                &middot; Due {{ $assignment->due_date->format('M j, Y') }}
                            @endif
                        </p>
                    </div>
                    <svg class="h-4 w-4 shrink-0 text-gray-400" viewBox="0 0 20 20" fill="currentColor"><path d="M7 5l6 5-6 5"/></svg>
                </a>
            @endforeach
        </div>
    @endif
@endsection
