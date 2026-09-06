@extends('layouts.app')

@section('title', 'Assignments — SchoolOS')
@section('page-title', 'Assignments')

@section('content')
    @if ($assignments->isEmpty())
        <x-empty-state
            title="No assignments yet"
            description="Assignments for your child's class will appear here, most recent first."
        />
    @else
        <div class="space-y-2">
            @foreach ($assignments->sortByDesc('created_at') as $assignment)
                <a
                    href="{{ route('student.assignments.show', $assignment) }}"
                    class="flex items-start justify-between gap-3 rounded-lg border border-gray-200 bg-white p-4 shadow-sm transition hover:border-indigo-300 hover:shadow"
                >
                    <div class="min-w-0">
                        <p class="truncate text-sm font-semibold text-gray-900">{{ $assignment->title }}</p>
                        <p class="mt-0.5 text-xs text-gray-500">
                            {{ $assignment->teacher?->name ?? 'Unknown' }}
                            &middot; {{ $assignment->subject?->name ?? '—' }}
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
