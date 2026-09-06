@extends('layouts.app')

@section('title', 'Promotions — SchoolOS')
@section('page-title', 'Promotions')

@section('content')
    @if (session('status'))
        <x-alert type="success">{{ session('status') }}</x-alert>
    @endif

    @if ($promotions->isEmpty())
        <x-empty-state
            title="No promotion decisions yet"
            description="Run automatic promotion for a class section or record a manual override."
        />
    @else
        <x-card :padded="false">
            <table class="min-w-full divide-y divide-gray-100 text-sm">
                <thead>
                    <tr class="text-left text-xs font-semibold uppercase text-gray-500">
                        <th class="px-4 py-2">Student</th>
                        <th class="px-4 py-2">From</th>
                        <th class="px-4 py-2">To</th>
                        <th class="px-4 py-2">Method</th>
                        <th class="px-4 py-2">Decided</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach ($promotions as $promotion)
                        <tr>
                            <td class="px-4 py-2 font-medium text-gray-900">{{ $promotion->student?->name ?? '—' }}</td>
                            <td class="px-4 py-2 text-gray-700">{{ $promotion->fromClassSection?->standard?->name ?? '—' }}</td>
                            <td class="px-4 py-2 text-gray-700">
                                {{ $promotion->toClassSection?->standard?->name ?? 'Held back' }}
                            </td>
                            <td class="px-4 py-2 text-gray-700">{{ ucfirst($promotion->method) }}</td>
                            <td class="px-4 py-2 text-gray-700">{{ $promotion->decided_at?->format('M j, Y') }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </x-card>
    @endif
@endsection