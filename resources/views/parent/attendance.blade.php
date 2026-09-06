@extends('layouts.app')

@section('title', $child->name . ' — Attendance — SchoolOS')
@section('page-title', 'Attendance')

@section('content')
    <a href="{{ route('parent.children.show', $child->id) }}" class="inline-flex items-center gap-1 text-sm text-gray-500 hover:text-gray-700">
        <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor"><path d="M13 15l-6-5 6-5"/></svg>
        {{ $child->name }}
    </a>

    @if ($records->isEmpty())
        <x-empty-state
            title="No attendance records yet"
            description="No attendance records yet for {{ $child->name }}."
        />
    @else
        <x-card :padded="false">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-2 text-left font-medium text-gray-500 sm:px-6">Date</th>
                            <th class="px-4 py-2 text-left font-medium text-gray-500 sm:px-6">Status</th>
                            <th class="px-4 py-2 text-left font-medium text-gray-500 sm:px-6">Note</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach ($records as $record)
                            <tr>
                                <td class="px-4 py-3 text-gray-900 sm:px-6">{{ $record->date->format('M j, Y') }}</td>
                                <td class="px-4 py-3 sm:px-6">
                                    <x-badge :status="$record->status">{{ ucfirst($record->status) }}</x-badge>
                                </td>
                                <td class="px-4 py-3 text-gray-400 sm:px-6">{{ $record->note ?? '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-card>
    @endif
@endsection
