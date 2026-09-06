@extends('layouts.app')

@section('title', 'Admission Application — SchoolOS')
@section('page-title', 'Admission Application')

@section('content')
    <a href="{{ route('admin.admission-applications.index') }}" class="inline-flex items-center gap-1 text-sm text-gray-500 hover:text-gray-700">
        <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor"><path d="M13 15l-6-5 6-5"/></svg>
        All applications
    </a>

    @if (session('status'))
        <x-alert variant="success">{{ session('status') }}</x-alert>
    @endif

    @if ($errors->any())
        <x-alert variant="error">{{ $errors->first() }}</x-alert>
    @endif

    <x-card :padded="false">
        <dl class="divide-y divide-gray-100 text-sm">
            <div class="grid grid-cols-3 gap-4 px-4 py-3 sm:px-6">
                <dt class="text-gray-500">Applicant</dt>
                <dd class="col-span-2 text-gray-900">{{ $application->applicant_data['name'] ?? '—' }}</dd>
            </div>
            <div class="grid grid-cols-3 gap-4 px-4 py-3 sm:px-6">
                <dt class="text-gray-500">Date of birth</dt>
                <dd class="col-span-2 text-gray-900">{{ $application->applicant_data['date_of_birth'] ?? '—' }}</dd>
            </div>
            <div class="grid grid-cols-3 gap-4 px-4 py-3 sm:px-6">
                <dt class="text-gray-500">Guardian</dt>
                <dd class="col-span-2 text-gray-900">
                    {{ $application->applicant_data['guardian_name'] ?? '—' }}
                    ({{ $application->applicant_data['guardian_relationship'] ?? '—' }})
                    &middot; {{ $application->applicant_data['guardian_phone'] ?? '—' }}
                </dd>
            </div>
            @if (! empty($application->applicant_data['medical_info']))
                <div class="grid grid-cols-3 gap-4 px-4 py-3 sm:px-6">
                    <dt class="text-gray-500">Medical info</dt>
                    <dd class="col-span-2 text-gray-900">{{ $application->applicant_data['medical_info'] }}</dd>
                </div>
            @endif
            <div class="grid grid-cols-3 gap-4 px-4 py-3 sm:px-6">
                <dt class="text-gray-500">Status</dt>
                <dd class="col-span-2 text-gray-900">{{ ucfirst(str_replace('_', ' ', $application->status)) }}</dd>
            </div>
            <div class="grid grid-cols-3 gap-4 px-4 py-3 sm:px-6">
                <dt class="text-gray-500">Submitted</dt>
                <dd class="col-span-2 text-gray-900">{{ $application->submitted_at?->format('M j, Y') }}</dd>
            </div>
            @if ($application->decided_at)
                <div class="grid grid-cols-3 gap-4 px-4 py-3 sm:px-6">
                    <dt class="text-gray-500">Decided</dt>
                    <dd class="col-span-2 text-gray-900">
                        {{ $application->decided_at->format('M j, Y') }} by {{ $application->decidedBy?->name ?? '—' }}
                    </dd>
                </div>
            @endif
            @if ($application->decision_reason)
                <div class="grid grid-cols-3 gap-4 px-4 py-3 sm:px-6">
                    <dt class="text-gray-500">Reason</dt>
                    <dd class="col-span-2 text-gray-900">{{ $application->decision_reason }}</dd>
                </div>
            @endif
            @if ($application->resultingUser)
                <div class="grid grid-cols-3 gap-4 px-4 py-3 sm:px-6">
                    <dt class="text-gray-500">Enrolled as</dt>
                    <dd class="col-span-2 text-gray-900">{{ $application->resultingUser->name }}</dd>
                </div>
            @endif
        </dl>
    </x-card>

    @if (in_array($application->status, ['submitted', 'under_review'], true))
        <div class="mt-4 space-y-4">
            @if ($application->status === 'submitted')
                <form method="POST" action="{{ route('admin.admission-applications.mark-under-review', $application) }}">
                    @csrf
                    <button type="submit" class="rounded bg-gray-100 px-3 py-1.5 text-sm text-gray-700 hover:bg-gray-200">
                        Mark under review
                    </button>
                </form>
            @endif

            <x-card>
                <h2 class="mb-2 text-sm font-semibold text-gray-900">Accept and enroll</h2>
                <form method="POST" action="{{ route('admin.admission-applications.accept', $application) }}" class="space-y-2">
                    @csrf
                    <label class="block text-xs text-gray-500">Academic year</label>
                    <select name="academic_year_id" required class="w-full rounded border-gray-300 text-sm">
                        @foreach ($academicYears as $year)
                            <option value="{{ $year->id }}">{{ $year->label }}</option>
                        @endforeach
                    </select>

                    <label class="block text-xs text-gray-500">Class section</label>
                    <select name="class_section_id" required class="w-full rounded border-gray-300 text-sm">
                        @foreach ($classSections as $section)
                            <option value="{{ $section->id }}">
                                {{ $section->standard?->name }} {{ $section->section?->name }}
                            </option>
                        @endforeach
                    </select>

                    <label class="block text-xs text-gray-500">Roll number</label>
                    <input type="text" name="roll_number" required class="w-full rounded border-gray-300 text-sm">

                    <button type="submit" class="rounded bg-indigo-600 px-3 py-1.5 text-sm text-white hover:bg-indigo-700">
                        Accept and enroll
                    </button>
                </form>
            </x-card>

            <div class="flex gap-4">
                <x-card>
                    <h2 class="mb-2 text-sm font-semibold text-gray-900">Reject</h2>
                    <form method="POST" action="{{ route('admin.admission-applications.reject', $application) }}" class="space-y-2">
                        @csrf
                        <textarea name="decision_reason" required placeholder="Reason" class="w-full rounded border-gray-300 text-sm"></textarea>
                        <button type="submit" class="rounded bg-red-600 px-3 py-1.5 text-sm text-white hover:bg-red-700">
                            Reject
                        </button>
                    </form>
                </x-card>

                <x-card>
                    <h2 class="mb-2 text-sm font-semibold text-gray-900">Withdraw</h2>
                    <form method="POST" action="{{ route('admin.admission-applications.withdraw', $application) }}" class="space-y-2">
                        @csrf
                        <textarea name="decision_reason" required placeholder="Reason" class="w-full rounded border-gray-300 text-sm"></textarea>
                        <button type="submit" class="rounded bg-gray-600 px-3 py-1.5 text-sm text-white hover:bg-gray-700">
                            Withdraw
                        </button>
                    </form>
                </x-card>
            </div>
        </div>
    @endif
@endsection
