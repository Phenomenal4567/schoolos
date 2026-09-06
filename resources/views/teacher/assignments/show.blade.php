@extends('layouts.app')

@section('title', $assignment->title . ' — SchoolOS')
@section('page-title', 'Assignment')

@section('content')
    <a href="{{ route('teacher.assignments.index') }}" class="inline-flex items-center gap-1 text-sm text-gray-500 hover:text-gray-700">
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
                <dt class="text-gray-500">Subject</dt>
                <dd class="col-span-2 text-gray-900">{{ $assignment->subject?->name ?? '—' }}</dd>
            </div>
            <div class="grid grid-cols-3 gap-4 px-4 py-3 sm:px-6">
                <dt class="text-gray-500">Class</dt>
                <dd class="col-span-2 text-gray-900">
                    {{ trim(($assignment->classSection?->standard?->name ?? '') . ' ' . ($assignment->classSection?->section?->name ?? '')) ?: '—' }}
                </dd>
            </div>
            <div class="grid grid-cols-3 gap-4 px-4 py-3 sm:px-6">
                <dt class="text-gray-500">Due date</dt>
                <dd class="col-span-2 text-gray-900">{{ $assignment->due_date?->format('M j, Y') ?? '—' }}</dd>
            </div>
        </dl>
    </x-card>

    <div>
        <h2 class="text-sm font-semibold text-gray-900">Submissions</h2>

        @if ($submissions->isEmpty())
            <x-empty-state
                title="No submissions yet"
                description="Student submissions for this assignment will appear here."
                class="mt-2"
            />
        @else
            <div class="mt-2 space-y-3">
                @foreach ($submissions as $submission)
                    <x-card>
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p class="text-sm font-medium text-gray-900">{{ $submission->student?->name ?? 'Unknown student' }}</p>
                                <p class="mt-0.5 text-xs text-gray-500">
                                    Submitted {{ $submission->submitted_at?->format('M j, Y g:ia') ?? '—' }}
                                </p>
                                {{-- AssignmentSubmission has one shared `comments`
                                     column, not separate student-note/feedback
                                     fields — grade() below overwrites this same
                                     column with the teacher's own feedback text
                                     (see that repository method's field list), so
                                     it only still holds the student's own note
                                     while ungraded. --}}
                                @if (! $submission->graded_at && $submission->comments)
                                    <p class="mt-2 whitespace-pre-line text-sm text-gray-700">{{ $submission->comments }}</p>
                                @elseif ($submission->graded_at && $submission->comments)
                                    <p class="mt-2 whitespace-pre-line text-sm text-gray-700">Feedback: {{ $submission->comments }}</p>
                                @endif
                            </div>
                            @if ($submission->graded_at)
                                <x-badge variant="success">Graded — {{ $submission->obtained_marks }}</x-badge>
                            @else
                                <x-badge variant="neutral">Ungraded</x-badge>
                            @endif
                        </div>

                        {{-- Plain inputs, not <x-input>, since that component always reads
                             old($name) with no way to disable it (unlike <x-select>'s
                             useOld flag) — with the same field names repeating once per
                             submission on this page, old() would bleed one row's
                             validation-failure input into every other row's form. --}}
                        <form method="POST" action="{{ route('teacher.submissions.grade', $submission) }}" class="mt-3 grid gap-3 sm:grid-cols-[8rem_1fr_auto] sm:items-end">
                            @csrf
                            <div>
                                <label for="obtained_marks-{{ $submission->id }}" class="mb-1 block text-sm font-medium text-gray-700">
                                    Marks <span class="text-red-500">*</span>
                                </label>
                                <input
                                    id="obtained_marks-{{ $submission->id }}"
                                    type="number"
                                    name="obtained_marks"
                                    value="{{ $submission->obtained_marks }}"
                                    step="0.01"
                                    min="0"
                                    required
                                    class="block w-full rounded-md border-0 py-1.5 px-2.5 text-sm text-gray-900 ring-1 ring-inset ring-gray-300 placeholder:text-gray-400 focus:outline-none focus:ring-2 focus:ring-indigo-600"
                                >
                            </div>
                            <div>
                                <label for="comments-{{ $submission->id }}" class="mb-1 block text-sm font-medium text-gray-700">Feedback</label>
                                <input
                                    id="comments-{{ $submission->id }}"
                                    name="comments"
                                    value="{{ $submission->comments }}"
                                    class="block w-full rounded-md border-0 py-1.5 px-2.5 text-sm text-gray-900 ring-1 ring-inset ring-gray-300 placeholder:text-gray-400 focus:outline-none focus:ring-2 focus:ring-indigo-600"
                                >
                            </div>
                            <x-button type="submit" variant="secondary" size="sm">
                                {{ $submission->graded_at ? 'Update grade' : 'Grade' }}
                            </x-button>
                        </form>
                    </x-card>
                @endforeach
            </div>
        @endif
    </div>
@endsection
