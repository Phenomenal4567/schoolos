@extends('layouts.app')

@section('title', 'Feedback — SchoolOS')
@section('page-title', 'Feedback')

@section('content')
    <a href="{{ route('parent.feedback.index') }}" class="inline-flex items-center gap-1 text-sm text-gray-500 hover:text-gray-700">
        <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor"><path d="M13 15l-6-5 6-5"/></svg>
        All feedback
    </a>

    <x-card>
        <div class="flex items-start justify-between gap-3">
            <h2 class="text-base font-semibold text-gray-900">{{ $feedback->category ?: 'Feedback' }}</h2>
            <x-badge variant="neutral">
                {{ $feedback->recipient_type === 'teacher'
                    ? 'To ' . ($feedback->recipientTeacher?->name ?? 'a teacher')
                    : 'To the school' }}
            </x-badge>
        </div>
        <p class="mt-1 text-xs text-gray-500">
            About {{ $feedback->student?->name ?? 'Unknown' }}
            &middot; {{ $feedback->created_at?->format('M j, Y g:ia') }}
        </p>
        <p class="mt-4 whitespace-pre-line text-sm text-gray-700">{{ $feedback->message }}</p>
    </x-card>
@endsection
