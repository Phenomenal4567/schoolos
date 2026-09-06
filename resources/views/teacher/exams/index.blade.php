@extends('layouts.app')

@section('title', 'Exams — SchoolOS')
@section('page-title', 'Exams')

@section('content')
    <x-card>
        <h2 class="text-sm font-semibold text-gray-900">New exam</h2>
        <form method="POST" action="{{ route('teacher.exams.store') }}" class="mt-3 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            @csrf
            <x-select
                name="class_section_id"
                label="Class section"
                :options="$classSections->mapWithKeys(fn ($cs) => [$cs->id => trim(($cs->standard?->name ?? '') . ' ' . ($cs->section?->name ?? ''))])"
                required
            />
            <x-select name="subject_id" label="Subject" :options="$subjects->pluck('name', 'id')" required />
            <x-input name="name" label="Exam name" required />
            <x-input type="date" name="exam_date" label="Exam date" required />

            <div class="sm:col-span-2 lg:col-span-4">
                <x-button type="submit" variant="primary" size="sm">Create exam</x-button>
            </div>
        </form>
    </x-card>

    @if ($exams->isEmpty())
        <x-empty-state
            title="No exams yet"
            description="Exams you create above will appear here, most recent first."
        />
    @else
        <div class="space-y-2">
            @foreach ($exams->sortByDesc('exam_date') as $exam)
                <a
                    href="{{ route('teacher.exams.show', $exam) }}"
                    class="flex items-start justify-between gap-3 rounded-lg border border-gray-200 bg-white p-4 shadow-sm transition hover:border-indigo-300 hover:shadow"
                >
                    <div class="min-w-0">
                        <p class="truncate text-sm font-semibold text-gray-900">{{ $exam->name }}</p>
                        <p class="mt-0.5 text-xs text-gray-500">
                            {{ $exam->subject?->name ?? '—' }}
                            &middot;
                            {{ trim(($exam->classSection?->standard?->name ?? '') . ' ' . ($exam->classSection?->section?->name ?? '')) }}
                            &middot; {{ $exam->exam_date?->format('M j, Y') }}
                        </p>
                    </div>
                    <svg class="h-4 w-4 shrink-0 text-gray-400" viewBox="0 0 20 20" fill="currentColor"><path d="M7 5l6 5-6 5"/></svg>
                </a>
            @endforeach
        </div>
    @endif
@endsection
