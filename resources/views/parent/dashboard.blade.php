@extends('layouts.app')

@section('title', 'My children — SchoolOS')
@section('page-title', 'My children')

@section('content')
    @if (session('status'))
        <x-alert variant="success">{{ session('status') }}</x-alert>
    @endif

    @if ($errors->any())
        <x-alert variant="error">{{ $errors->first() }}</x-alert>
    @endif

    @if ($children->isEmpty())
        <x-empty-state
            title="No children linked yet"
            description="No children are linked to your account yet. Request a link below, or contact your school administrator."
        />
    @else
        <div class="space-y-3">
            @foreach ($children as $child)
                <x-card :padded="false">
                    <div class="flex items-center justify-between px-4 py-3.5 sm:px-6">
                        <span class="text-sm font-medium text-gray-900">{{ $child->name }}</span>
                        <a href="{{ route('parent.children.show', $child->id) }}" class="inline-flex items-center gap-1 text-sm font-medium text-indigo-600 hover:text-indigo-500">
                            View enrollment
                            <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor"><path d="M7 5l6 5-6 5"/></svg>
                        </a>
                    </div>
                </x-card>
            @endforeach
        </div>
    @endif

    @if ($pendingRequests->isNotEmpty())
        <x-card class="mt-4">
            <h2 class="text-sm font-semibold text-gray-900">Awaiting school approval</h2>
            <ul class="mt-2 space-y-1 text-sm text-gray-500">
                @foreach ($pendingRequests as $pendingRequest)
                    <li>{{ $pendingRequest->student?->name ?? 'A student' }} — request pending</li>
                @endforeach
            </ul>
        </x-card>
    @endif

    <x-card class="mt-4">
        <h2 class="text-sm font-semibold text-gray-900">Connect a child</h2>
        <p class="mt-1 text-sm text-gray-500">
            Enter your child's student ID (given to you by the school) and their full name to request a link.
            A school admin will review and approve it.
        </p>
        <form method="POST" action="{{ route('parent.child-link-requests.store') }}" class="mt-3 flex flex-wrap items-end gap-3">
            @csrf
            <x-input name="student_id" label="Student ID" placeholder="e.g. GHS-2026-001" required />
            <x-input name="name" label="Child's full name" required />
            <x-button type="submit" size="sm">Send request</x-button>
        </form>
    </x-card>
@endsection
