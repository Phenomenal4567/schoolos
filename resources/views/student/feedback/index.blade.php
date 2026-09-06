@extends('layouts.app')

@section('title', 'Feedback — SchoolOS')
@section('page-title', 'Feedback')

@section('content')
    <x-card>
        <h2 class="text-sm font-semibold text-gray-900">Send feedback</h2>
        {{-- No student_id input for the person to fill in or choose — a
             student's feedback is always about themself (this task's own
             scoping note), enforced server-side by
             FeedbackRepository::create()'s assertOwnsStudent(). The
             hidden field below only exists to satisfy store()'s existing
             'required' validation rule. --}}
        <form method="POST" action="{{ route('student.feedback.store') }}" class="mt-3 space-y-3">
            @csrf
            <input type="hidden" name="student_id" value="{{ auth()->id() }}">
            <div class="grid gap-3 sm:grid-cols-2">
                <x-select
                    name="recipient_type"
                    label="Send to"
                    :options="['school' => 'The school', 'teacher' => 'A specific teacher']"
                    required
                />
                <x-select
                    name="recipient_teacher_id"
                    label="Teacher (only if sending to a specific teacher)"
                    :options="collect(['' => 'Not applicable'])->union($teachers->pluck('name', 'id'))"
                />
            </div>
            <x-input name="category" label="Category" />
            <div>
                <label for="message" class="mb-1 block text-sm font-medium text-gray-700">
                    Message <span class="text-red-500">*</span>
                </label>
                <textarea
                    id="message"
                    name="message"
                    rows="4"
                    required
                    class="block w-full rounded-md border-0 py-1.5 px-2.5 text-sm text-gray-900 ring-1 ring-inset ring-gray-300 placeholder:text-gray-400 focus:outline-none focus:ring-2 focus:ring-indigo-600"
                >{{ old('message') }}</textarea>
            </div>
            <x-button type="submit" variant="primary" size="sm">Send feedback</x-button>
        </form>
    </x-card>

    @if ($feedback->isEmpty())
        <x-empty-state
            title="No feedback yet"
            description="Feedback you send will appear here, most recent first."
        />
    @else
        <div class="space-y-2">
            @foreach ($feedback->sortByDesc('created_at') as $item)
                <a
                    href="{{ route('student.feedback.show', $item) }}"
                    class="flex items-start justify-between gap-3 rounded-lg border border-gray-200 bg-white p-4 shadow-sm transition hover:border-indigo-300 hover:shadow"
                >
                    <div class="min-w-0">
                        <p class="truncate text-sm font-semibold text-gray-900">
                            {{ $item->category ?: 'Feedback' }}
                        </p>
                        <p class="mt-0.5 truncate text-sm text-gray-600">{{ $item->message }}</p>
                        <p class="mt-0.5 text-xs text-gray-500">
                            {{ $item->recipient_type === 'teacher'
                                ? 'To ' . ($item->recipientTeacher?->name ?? 'a teacher')
                                : 'To the school' }}
                            &middot; {{ $item->created_at?->format('M j, Y') }}
                        </p>
                    </div>
                    <svg class="h-4 w-4 shrink-0 text-gray-400" viewBox="0 0 20 20" fill="currentColor"><path d="M7 5l6 5-6 5"/></svg>
                </a>
            @endforeach
        </div>
    @endif
@endsection
