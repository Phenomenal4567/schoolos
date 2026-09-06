@extends('layouts.app')

@section('title', $lessonPlan->title . ' — SchoolOS')
@section('page-title', 'Lesson plan')

@section('content')
    <a href="{{ route('parent.lesson-plans.index') }}" class="inline-flex items-center gap-1 text-sm text-gray-500 hover:text-gray-700">
        <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor"><path d="M13 15l-6-5 6-5"/></svg>
        All lesson plans
    </a>

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

    <x-card>
        <div class="flex items-start justify-between gap-3">
            <h2 class="text-base font-semibold text-gray-900">{{ $lessonPlan->title }}</h2>
            <x-badge :variant="$statusVariant">{{ $statusLabel }}</x-badge>
        </div>
        <p class="mt-4 whitespace-pre-line text-sm text-gray-700">{{ $lessonPlan->content }}</p>
    </x-card>

    <x-card :padded="false">
        <dl class="divide-y divide-gray-100 text-sm">
            <div class="grid grid-cols-3 gap-4 px-4 py-3 sm:px-6">
                <dt class="text-gray-500">Teacher</dt>
                <dd class="col-span-2 text-gray-900">{{ $lessonPlan->teacher?->name ?? '—' }}</dd>
            </div>
            <div class="grid grid-cols-3 gap-4 px-4 py-3 sm:px-6">
                <dt class="text-gray-500">Subject</dt>
                <dd class="col-span-2 text-gray-900">{{ $lessonPlan->subject?->name ?? '—' }}</dd>
            </div>
            <div class="grid grid-cols-3 gap-4 px-4 py-3 sm:px-6">
                <dt class="text-gray-500">Class</dt>
                <dd class="col-span-2 text-gray-900">
                    {{ trim(($lessonPlan->classSection?->standard?->name ?? '') . ' ' . ($lessonPlan->classSection?->section?->name ?? '')) ?: '—' }}
                </dd>
            </div>
        </dl>
    </x-card>
@endsection
