@extends('layouts.app')

@section('title', 'Lesson plans — SchoolOS')
@section('page-title', 'Lesson plans')

@section('content')
    <x-card>
        <h2 class="text-sm font-semibold text-gray-900">New lesson plan</h2>
        <form method="POST" action="{{ route('teacher.lesson-plans.store') }}" class="mt-3 space-y-3">
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
                <label for="content" class="mb-1 block text-sm font-medium text-gray-700">
                    Content <span class="text-red-500">*</span>
                </label>
                <textarea
                    id="content"
                    name="content"
                    rows="4"
                    required
                    class="block w-full rounded-md border-0 py-1.5 px-2.5 text-sm text-gray-900 ring-1 ring-inset ring-gray-300 placeholder:text-gray-400 focus:outline-none focus:ring-2 focus:ring-indigo-600"
                >{{ old('content') }}</textarea>
            </div>
            <x-button type="submit" variant="primary" size="sm">Create lesson plan</x-button>
        </form>
    </x-card>

    @if ($lessonPlans->isEmpty())
        <x-empty-state
            title="No lesson plans yet"
            description="Lesson plans you create above will appear here, most recent first."
        />
    @else
        <div class="space-y-2">
            @foreach ($lessonPlans->sortByDesc('created_at') as $lessonPlan)
                @php
                    $statusVariant = match ($lessonPlan->status) {
                        'approved' => 'success',
                        'rejected' => 'danger',
                        'submitted' => 'warning',
                        default => 'neutral',
                    };
                    $statusLabel = match ($lessonPlan->status) {
                        'approved' => 'Approved',
                        'rejected' => 'Rejected',
                        'submitted' => 'Pending review',
                        default => 'Draft',
                    };
                @endphp
                <a
                    href="{{ route('teacher.lesson-plans.show', $lessonPlan) }}"
                    class="flex items-start justify-between gap-3 rounded-lg border border-gray-200 bg-white p-4 shadow-sm transition hover:border-indigo-300 hover:shadow"
                >
                    <div class="min-w-0">
                        <div class="flex items-center gap-2">
                            <p class="truncate text-sm font-semibold text-gray-900">{{ $lessonPlan->title }}</p>
                            <x-badge :variant="$statusVariant">{{ $statusLabel }}</x-badge>
                        </div>
                        <p class="mt-0.5 text-xs text-gray-500">
                            {{ $lessonPlan->subject?->name ?? '—' }}
                            &middot;
                            {{ trim(($lessonPlan->classSection?->standard?->name ?? '') . ' ' . ($lessonPlan->classSection?->section?->name ?? '')) }}
                            &middot; {{ $lessonPlan->created_at?->format('M j, Y') }}
                        </p>
                    </div>
                    <svg class="h-4 w-4 shrink-0 text-gray-400" viewBox="0 0 20 20" fill="currentColor"><path d="M7 5l6 5-6 5"/></svg>
                </a>
            @endforeach
        </div>
    @endif
@endsection
