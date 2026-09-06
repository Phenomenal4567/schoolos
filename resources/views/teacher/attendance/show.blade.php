@extends('layouts.app')

@php
    $classLabel = trim(($classSection->standard?->name ?? '') . ' ' . ($classSection->section?->name ?? ''));
@endphp

@section('title', $classLabel . ' Attendance — SchoolOS')
@section('page-title', $classLabel . ' — Attendance')

@section('content')
    <a href="{{ route('teacher.attendance.index') }}" class="inline-flex items-center gap-1 text-sm text-gray-500 hover:text-gray-700">
        <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor"><path d="M13 15l-6-5 6-5"/></svg>
        All classes
    </a>

    @if ($errors->any() && ! $errors->has('reason') && ! old('student_id'))
        {{-- A validation error not tied to a specific row's correction dialog
             (e.g. an unexpected student_id) — surfaced generically so it's
             never silently lost. --}}
        <x-alert variant="error">{{ $errors->first() }}</x-alert>
    @endif

    <x-card :padded="false">
        <form method="GET" action="{{ route('teacher.attendance.show', $classSection) }}" class="flex flex-wrap items-end gap-3 border-b border-gray-200 p-4">
            <x-input type="date" name="date" label="Date" :value="$date" required />
            <x-select
                name="session"
                label="Session"
                :options="['morning' => 'Morning', 'afternoon' => 'Afternoon']"
                :value="$session"
                required
            />
            <x-button type="submit" variant="secondary" size="sm">View</x-button>
        </form>

        @if ($enrollments->isEmpty())
            <div class="p-4">
                <x-empty-state
                    title="No enrolled students"
                    description="No active students are enrolled in this class section yet."
                />
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-2 text-left font-medium text-gray-500">Roll #</th>
                            <th class="px-4 py-2 text-left font-medium text-gray-500">Student</th>
                            <th class="px-4 py-2 text-left font-medium text-gray-500">Status</th>
                            <th class="px-4 py-2 text-right font-medium text-gray-500">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach ($enrollments as $enrollment)
                            @php
                                $student = $enrollment->student;
                                $existing = $records->get($student->id);
                                $dialogId = 'correct-' . $student->id;
                                $rowHasError = $errors->any() && (int) old('student_id') === $student->id;
                            @endphp
                            <tr class="{{ $rowHasError ? 'bg-red-50/40' : '' }}">
                                <td class="px-4 py-3 text-gray-500">{{ $enrollment->roll_number ?? '—' }}</td>
                                <td class="px-4 py-3 font-medium text-gray-900">{{ $student->name }}</td>
                                <td class="px-4 py-3">
                                    @if ($existing)
                                        <x-badge :status="$existing->status">{{ ucfirst($existing->status) }}</x-badge>
                                        @if ($existing->corrections_count)
                                            <span class="ml-1 text-xs text-gray-400">(corrected)</span>
                                        @endif
                                    @else
                                        <span class="text-xs text-gray-400">Not marked</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-right">
                                    @if ($existing)
                                        <x-button type="button" size="sm" variant="secondary" data-dialog-open="{{ $dialogId }}">
                                            Correct
                                        </x-button>
                                    @else
                                        <div class="inline-flex flex-wrap justify-end gap-1.5">
                                            @foreach (['present' => 'Present', 'absent' => 'Absent', 'late' => 'Late', 'excused' => 'Excused'] as $statusKey => $statusLabel)
                                                <form method="POST" action="{{ route('teacher.attendance.store') }}">
                                                    @csrf
                                                    <input type="hidden" name="class_section_id" value="{{ $classSection->id }}">
                                                    <input type="hidden" name="student_id" value="{{ $student->id }}">
                                                    <input type="hidden" name="date" value="{{ $date }}">
                                                    <input type="hidden" name="session" value="{{ $session }}">
                                                    <input type="hidden" name="status" value="{{ $statusKey }}">
                                                    <x-button type="submit" size="sm" variant="secondary">{{ $statusLabel }}</x-button>
                                                </form>
                                            @endforeach
                                        </div>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-card>

    {{-- One correction dialog per already-marked student. Distinct from a
         plain edit: shows existing → new status side by side and requires
         a reason, matching AttendanceRepository::mark()'s NOT NULL
         attendance_corrections.reason constraint. --}}
    @foreach ($enrollments as $enrollment)
        @php
            $student = $enrollment->student;
            $existing = $records->get($student->id);
        @endphp
        @if ($existing)
            <x-dialog id="correct-{{ $student->id }}" title="Correct attendance — {{ $student->name }}">
                <form method="POST" action="{{ route('teacher.attendance.store') }}" class="space-y-4">
                    @csrf
                    <input type="hidden" name="class_section_id" value="{{ $classSection->id }}">
                    <input type="hidden" name="student_id" value="{{ $student->id }}">
                    <input type="hidden" name="date" value="{{ $date }}">
                    <input type="hidden" name="session" value="{{ $session }}">

                    <div class="flex items-center gap-3 rounded-md bg-gray-50 px-3 py-2 text-sm">
                        <span class="text-gray-500">Existing:</span>
                        <x-badge :status="$existing->status">{{ ucfirst($existing->status) }}</x-badge>
                    </div>

                    @php
                        $isErroringRow = (int) old('student_id') === $student->id;
                        $newStatusValue = $isErroringRow ? old('status', $existing->status) : $existing->status;
                    @endphp
                    <x-select
                        name="status"
                        label="New status"
                        :options="['present' => 'Present', 'absent' => 'Absent', 'late' => 'Late', 'excused' => 'Excused']"
                        :value="$newStatusValue"
                        :use-old="false"
                        required
                    />

                    <div>
                        <label for="reason-{{ $student->id }}" class="mb-1 block text-sm font-medium text-gray-700">
                            Reason <span class="text-red-500">*</span>
                        </label>
                        <textarea
                            id="reason-{{ $student->id }}"
                            name="reason"
                            rows="3"
                            required
                            class="block w-full rounded-md border-0 py-1.5 px-2.5 text-sm text-gray-900 ring-1 ring-inset {{ (int) old('student_id') === $student->id && $errors->has('reason') ? 'ring-red-300 focus:ring-red-500' : 'ring-gray-300 focus:ring-indigo-600' }} focus:outline-none focus:ring-2"
                        >{{ (int) old('student_id') === $student->id ? old('reason') : '' }}</textarea>
                        @if ((int) old('student_id') === $student->id && $errors->has('reason'))
                            <p class="mt-1 text-sm text-red-600">{{ $errors->first('reason') }}</p>
                        @endif
                    </div>

                    <div class="flex justify-end gap-2 border-t border-gray-100 pt-3">
                        <x-button type="button" variant="ghost" data-dialog-close>Cancel</x-button>
                        <x-button type="submit" variant="primary">Save correction</x-button>
                    </div>
                </form>
            </x-dialog>
        @endif
    @endforeach

    @if ($errors->any() && old('student_id'))
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                document.getElementById('correct-{{ (int) old('student_id') }}')?.showModal();
            });
        </script>
    @endif
@endsection
