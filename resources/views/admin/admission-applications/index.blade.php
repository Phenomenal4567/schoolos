@extends('layouts.app')

@section('title', 'Admission Applications — SchoolOS')
@section('page-title', 'Admission Applications')

@section('content')
    @if (session('status'))
        <x-alert variant="success">{{ session('status') }}</x-alert>
    @endif

    @if ($applications->isEmpty())
        <x-empty-state
            title="No admission applications yet"
            description="Applications submitted through the public admission form will appear here."
        />
    @else
        <x-card :padded="false">
            <table class="min-w-full divide-y divide-gray-100 text-sm">
                <thead>
                    <tr class="text-left text-xs font-semibold uppercase text-gray-500">
                        <th class="px-4 py-2">Applicant</th>
                        <th class="px-4 py-2">Status</th>
                        <th class="px-4 py-2">Submitted</th>
                        <th class="px-4 py-2"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach ($applications as $application)
                        <tr>
                            <td class="px-4 py-2 font-medium text-gray-900">
                                {{ $application->applicant_data['name'] ?? '—' }}
                            </td>
                            <td class="px-4 py-2 text-gray-700">{{ ucfirst(str_replace('_', ' ', $application->status)) }}</td>
                            <td class="px-4 py-2 text-gray-700">{{ $application->submitted_at?->format('M j, Y') }}</td>
                            <td class="px-4 py-2">
                                <a href="{{ route('admin.admission-applications.show', $application) }}" class="text-indigo-600 hover:text-indigo-800">
                                    View
                                </a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </x-card>
    @endif
@endsection
