@extends('layouts.app')

@section('title', 'Let\'s set up your school — SchoolOS')
@section('page-title', 'School setup')

@php
    $totalSteps = count($steps);
    $completedCount = min($school->setup_step - 1, $totalSteps);
    $progressPercent = $totalSteps > 0 ? (int) round(($completedCount / $totalSteps) * 100) : 0;
@endphp

@section('content')
    <p class="text-sm text-gray-500">
        Let's set up your school. Work through these steps in any order — you can jump straight to your
        <a href="{{ route('admin.dashboard') }}" class="text-indigo-600 hover:text-indigo-500">dashboard</a> at any time.
    </p>

    <x-card>
        <div class="mb-1 flex items-center justify-between text-sm">
            <span class="font-medium text-gray-700">{{ $completedCount }} of {{ $totalSteps }} steps done</span>
            <span class="text-gray-500">{{ $progressPercent }}%</span>
        </div>
        <div class="h-2 w-full overflow-hidden rounded-full bg-gray-100">
            <div class="h-2 rounded-full bg-indigo-600 transition-all" style="width: {{ $progressPercent }}%"></div>
        </div>
    </x-card>

    <div class="mt-4 space-y-3">
        @foreach ($steps as $index => $step)
            @php
                $stepNumber = $index + 1;
                $isDone = $stepNumber < $school->setup_step;
            @endphp
            <x-card :padded="false">
                <div class="flex items-center justify-between gap-4 p-4">
                    <div class="flex items-center gap-3">
                        <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full text-sm font-semibold {{ $isDone ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-600' }}">
                            @if ($isDone)
                                &check;
                            @else
                                {{ $stepNumber }}
                            @endif
                        </span>
                        <div>
                            <p class="text-sm font-semibold text-gray-900">{{ $step['title'] }}</p>
                            <p class="text-sm text-gray-500">{{ $step['description'] }}</p>
                        </div>
                    </div>

                    <div class="flex shrink-0 items-center gap-2">
                        <a
                            href="{{ route($step['route']) . ($step['anchor'] ? '#' . $step['anchor'] : '') }}"
                            class="inline-flex items-center rounded-md bg-white px-3 py-2 text-sm font-semibold text-gray-700 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50"
                        >
                            {{ $step['key'] === 'finish' ? 'Go to dashboard' : 'Open' }}
                        </a>

                        @if ($step['key'] === 'finish')
                            <form method="POST" action="{{ route('admin.setup.complete') }}">
                                @csrf
                                <x-button type="submit" size="sm">Finish setup</x-button>
                            </form>
                        @elseif (! $isDone)
                            <form method="POST" action="{{ route('admin.setup.advance') }}">
                                @csrf
                                <input type="hidden" name="step" value="{{ $stepNumber }}">
                                <x-button type="submit" variant="secondary" size="sm">Mark done</x-button>
                            </form>
                        @endif
                    </div>
                </div>
            </x-card>
        @endforeach
    </div>
@endsection
