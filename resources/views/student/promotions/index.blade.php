@extends('layouts.app')

@section('title', 'Promotions — SchoolOS')
@section('page-title', 'Promotion')

@section('content')
    @if ($promotions->isEmpty())
        <x-empty-state
            title="No promotion decisions yet"
            description="Your end-of-year promotion decision will appear here once recorded."
        />
    @else
        <div class="space-y-2">
            @foreach ($promotions as $promotion)
                <a
                    href="{{ route('student.promotions.show', $promotion) }}"
                    class="flex items-start justify-between gap-3 rounded-lg border border-gray-200 bg-white p-4 shadow-sm transition hover:border-indigo-300 hover:shadow"
                >
                    <div class="min-w-0">
                        <p class="truncate text-sm font-semibold text-gray-900">
                            {{ $promotion->toClassSection ? 'Promoted' : 'Held back' }}
                        </p>
                        <p class="mt-0.5 text-xs text-gray-500">
                            {{ ucfirst($promotion->method) }}
                            &middot; {{ $promotion->decided_at?->format('M j, Y') }}
                        </p>
                    </div>
                    <svg class="h-4 w-4 shrink-0 text-gray-400" viewBox="0 0 20 20" fill="currentColor"><path d="M7 5l6 5-6 5"/></svg>
                </a>
            @endforeach
        </div>
    @endif
@endsection
