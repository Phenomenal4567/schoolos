@extends('layouts.app')

@section('title', 'Exams — SchoolOS')
@section('page-title', 'Exams')

@section('content')
    @if ($exams->isEmpty())
        <x-empty-state
            title="No exams yet"
            description="Exams for your child's class will appear here, most recent first."
        />
    @else
        <div class="space-y-2">
            @foreach ($exams->sortByDesc('exam_date') as $exam)
                <a
                    href="{{ route('parent.exams.show', $exam) }}"
                    class="flex items-start justify-between gap-3 rounded-lg border border-gray-200 bg-white p-4 shadow-sm transition hover:border-indigo-300 hover:shadow"
                >
                    <div class="min-w-0">
                        <p class="truncate text-sm font-semibold text-gray-900">{{ $exam->name }}</p>
                        <p class="mt-0.5 text-xs text-gray-500">
                            {{ $exam->subject?->name ?? '—' }}
                            &middot; {{ $exam->exam_date?->format('M j, Y') }}
                        </p>
                    </div>
                    <svg class="h-4 w-4 shrink-0 text-gray-400" viewBox="0 0 20 20" fill="currentColor"><path d="M7 5l6 5-6 5"/></svg>
                </a>
            @endforeach
        </div>
    @endif
@endsection
