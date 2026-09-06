@extends('layouts.app')

@section('title', 'Exams - SchoolOS')
@section('page-title', 'Exams')

@section('content')
    <x-card :padded="false">
        <div class="border-b border-gray-200 px-4 py-3 sm:px-6">
            <h2 class="text-sm font-semibold text-gray-900">Exam components</h2>
        </div>
        <form method="POST" action="{{ route('admin.exam-components.store') }}" class="grid gap-3 p-4 sm:grid-cols-2 sm:p-6">
            @csrf
            @php
                $configured = old('components', $components->isNotEmpty()
                    ? $components->map(fn ($component) => ['name' => $component->name, 'weight_percent' => $component->weight_percent])->all()
                    : [['name' => 'CA', 'weight_percent' => 40], ['name' => 'Exam', 'weight_percent' => 60]]);
            @endphp
            @foreach ($configured as $index => $row)
                <div class="grid grid-cols-2 gap-2">
                    <x-input name="components[{{ $index }}][name]" label="Name" value="{{ $row['name'] ?? '' }}" required />
                    <x-input type="number" name="components[{{ $index }}][weight_percent]" label="Weight %" value="{{ $row['weight_percent'] ?? '' }}" step="0.01" required />
                </div>
            @endforeach
            <div class="sm:col-span-2">
                <x-button type="submit" variant="primary" size="sm">Save components</x-button>
            </div>
        </form>
    </x-card>

    <x-card :padded="false">
        <div class="border-b border-gray-200 px-4 py-3 sm:px-6">
            <h2 class="text-sm font-semibold text-gray-900">Result review queue</h2>
        </div>
        @if ($exams->isEmpty())
            <div class="p-4 sm:p-6">
                <x-empty-state title="No exams yet" description="Exams created by teachers will appear here." />
            </div>
        @else
            <div class="divide-y divide-gray-100">
                @foreach ($exams as $exam)
                    <a href="{{ route('admin.exams.show', $exam) }}" class="flex items-center justify-between gap-3 px-4 py-3 transition hover:bg-gray-50 sm:px-6">
                        <div class="min-w-0">
                            <p class="truncate text-sm font-semibold text-gray-900">{{ $exam->name }}</p>
                            <p class="mt-0.5 truncate text-sm text-gray-600">
                                {{ $exam->subject?->name ?? '-' }}
                                &middot; {{ trim(($exam->classSection?->standard?->name ?? '') . ' ' . ($exam->classSection?->section?->name ?? '')) ?: '-' }}
                            </p>
                        </div>
                        <span class="text-xs font-medium text-gray-500">{{ $exam->marks->count() }} mark(s)</span>
                    </a>
                @endforeach
            </div>
        @endif
    </x-card>
@endsection
