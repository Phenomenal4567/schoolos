@extends('layouts.app')

@section('title', $assignment->title . ' — SchoolOS')
@section('page-title', 'Assignment')

@section('content')
    <a href="{{ route('student.assignments.index') }}" class="inline-flex items-center gap-1 text-sm text-gray-500 hover:text-gray-700">
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

    @if ($submission === null)
        <x-card>
            <h2 class="text-sm font-semibold text-gray-900">Submit your work</h2>
            <form method="POST" action="{{ route('student.assignments.submissions.store', $assignment) }}" class="mt-3 space-y-3">
                @csrf
                <div>
                    <label for="comments" class="mb-1 block text-sm font-medium text-gray-700">Comments</label>
                    <textarea
                        id="comments"
                        name="comments"
                        rows="4"
                        class="block w-full rounded-md border-0 py-1.5 px-2.5 text-sm text-gray-900 ring-1 ring-inset ring-gray-300 placeholder:text-gray-400 focus:outline-none focus:ring-2 focus:ring-indigo-600"
                    >{{ old('comments') }}</textarea>
                </div>
                <x-button type="submit" variant="primary" size="sm">Submit assignment</x-button>
            </form>
        </x-card>
    @else
        <x-card>
            <div class="flex items-start justify-between gap-3">
                <div class="min-w-0">
                    <h2 class="text-sm font-semibold text-gray-900">Your submission</h2>
                    <p class="mt-0.5 text-xs text-gray-500">
                        Submitted {{ $submission->submitted_at?->format('M j, Y g:ia') ?? '—' }}
                    </p>
                </div>
                @if ($submission->graded_at)
                    <x-badge variant="success">Graded</x-badge>
                @else
                    <x-badge variant="neutral">Awaiting grade</x-badge>
                @endif
            </div>

            {{-- AssignmentSubmission has one shared `comments` column, not a
                 separate student-note vs. teacher-feedback pair — grade()
                 overwrites this same field with the teacher's feedback text
                 (see that repository method's own field list), so which
                 label fits depends on whether grading has happened yet, not
                 on displaying both at once. --}}
            @if (! $submission->graded_at && $submission->comments)
                <p class="mt-3 whitespace-pre-line text-sm text-gray-700">{{ $submission->comments }}</p>
            @endif

            @if ($submission->graded_at)
                <div class="mt-4 rounded-md bg-gray-50 p-3">
                    <p class="text-sm font-medium text-gray-900">Marks: {{ $submission->obtained_marks }}</p>
                    @if ($submission->comments)
                        <p class="mt-1 text-sm text-gray-700">Feedback: {{ $submission->comments }}</p>
                    @endif
                    <p class="mt-1 text-xs text-gray-500">
                        Graded {{ $submission->graded_at->format('M j, Y g:ia') }}
                        @if ($submission->gradedBy?->name)
                            by {{ $submission->gradedBy->name }}
                        @endif
                    </p>
                </div>
            @endif
        </x-card>
    @endif
@endsection
