@extends('layouts.app')

@section('title', 'Promotion — SchoolOS')
@section('page-title', 'Promotion')

@section('content')
    <a href="{{ route('parent.promotions.index') }}" class="inline-flex items-center gap-1 text-sm text-gray-500 hover:text-gray-700">
        <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor"><path d="M13 15l-6-5 6-5"/></svg>
        All promotions
    </a>

    <x-card :padded="false">
        <dl class="divide-y divide-gray-100 text-sm">
            <div class="grid grid-cols-3 gap-4 px-4 py-3 sm:px-6">
                <dt class="text-gray-500">Student</dt>
                <dd class="col-span-2 text-gray-900">{{ $promotion->student?->name ?? '—' }}</dd>
            </div>
            <div class="grid grid-cols-3 gap-4 px-4 py-3 sm:px-6">
                <dt class="text-gray-500">Decision</dt>
                <dd class="col-span-2 text-gray-900">{{ $promotion->toClassSection ? 'Promoted' : 'Held back' }}</dd>
            </div>
            <div class="grid grid-cols-3 gap-4 px-4 py-3 sm:px-6">
                <dt class="text-gray-500">Method</dt>
                <dd class="col-span-2 text-gray-900">{{ ucfirst($promotion->method) }}</dd>
            </div>
            @if ($promotion->reason)
                <div class="grid grid-cols-3 gap-4 px-4 py-3 sm:px-6">
                    <dt class="text-gray-500">Reason</dt>
                    <dd class="col-span-2 text-gray-900">{{ $promotion->reason }}</dd>
                </div>
            @endif
            <div class="grid grid-cols-3 gap-4 px-4 py-3 sm:px-6">
                <dt class="text-gray-500">Decided</dt>
                <dd class="col-span-2 text-gray-900">{{ $promotion->decided_at?->format('M j, Y') }}</dd>
            </div>
        </dl>
    </x-card>
@endsection
