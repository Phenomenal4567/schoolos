@extends('layouts.app')

@section('title', $exam->name . ' - SchoolOS')
@section('page-title', 'Exam')

@section('content')
    <a href="{{ route('teacher.exams.index') }}" class="inline-flex items-center gap-1 text-sm text-gray-500 hover:text-gray-700">
        <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor"><path d="M13 15l-6-5 6-5"/></svg>
        All exams
    </a>

    <x-card :padded="false">
        <dl class="divide-y divide-gray-100 text-sm">
            <div class="grid grid-cols-3 gap-4 px-4 py-3 sm:px-6">
                <dt class="text-gray-500">Name</dt>
                <dd class="col-span-2 text-gray-900">{{ $exam->name }}</dd>
            </div>
            <div class="grid grid-cols-3 gap-4 px-4 py-3 sm:px-6">
                <dt class="text-gray-500">Subject</dt>
                <dd class="col-span-2 text-gray-900">{{ $exam->subject?->name ?? '-' }}</dd>
            </div>
            <div class="grid grid-cols-3 gap-4 px-4 py-3 sm:px-6">
                <dt class="text-gray-500">Class</dt>
                <dd class="col-span-2 text-gray-900">
                    {{ trim(($exam->classSection?->standard?->name ?? '') . ' ' . ($exam->classSection?->section?->name ?? '')) ?: '-' }}
                </dd>
            </div>
            <div class="grid grid-cols-3 gap-4 px-4 py-3 sm:px-6">
                <dt class="text-gray-500">Exam date</dt>
                <dd class="col-span-2 text-gray-900">{{ $exam->exam_date?->format('M j, Y') }}</dd>
            </div>
        </dl>
    </x-card>

    <div>
        <h2 class="text-sm font-semibold text-gray-900">Marks</h2>

        @if ($components->isEmpty())
            <x-empty-state
                title="No result components"
                description="Ask an administrator to configure exam components before recording marks."
                class="mt-2"
            />
        @elseif ($enrollments->isEmpty())
            <x-empty-state
                title="No students enrolled"
                description="This class section has no active enrollments to record marks for."
                class="mt-2"
            />
        @else
            @foreach ($enrollments as $enrollment)
                <form
                    id="exam-mark-form-{{ $enrollment->student_id }}"
                    method="POST"
                    action="{{ route('teacher.exam-marks.store') }}"
                >
                    @csrf
                    <input type="hidden" name="exam_id" value="{{ $exam->id }}">
                    <input type="hidden" name="student_id" value="{{ $enrollment->student_id }}">
                </form>
            @endforeach

            <x-card :padded="false" class="mt-2">
                <table class="min-w-full divide-y divide-gray-100 text-sm">
                    <thead>
                        <tr class="text-left text-xs font-medium uppercase tracking-wide text-gray-500">
                            <th class="px-4 py-2">Student</th>
                            <th class="px-4 py-2">Component</th>
                            <th class="px-4 py-2">Marks obtained</th>
                            <th class="px-4 py-2">Max marks</th>
                            <th class="px-4 py-2"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach ($enrollments as $enrollment)
                            @php
                                $selectedComponentId = (int) old('exam_component_id', $components->first()?->id);
                                $existingMark = $existingMarks->first(fn ($mark) => $mark->student_id === $enrollment->student_id && $mark->exam_component_id === $selectedComponentId);
                            @endphp
                            <tr>
                                <td class="px-4 py-2 font-medium text-gray-900">{{ $enrollment->student?->name ?? '-' }}</td>
                                <td class="px-4 py-2">
                                    <select
                                        form="exam-mark-form-{{ $enrollment->student_id }}"
                                        name="exam_component_id"
                                        required
                                        class="block w-32 rounded-md border-0 py-1.5 px-2.5 text-sm text-gray-900 ring-1 ring-inset ring-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-600"
                                    >
                                        @foreach ($components as $component)
                                            <option value="{{ $component->id }}" @selected($selectedComponentId === $component->id)>
                                                {{ $component->name }} ({{ number_format((float) $component->weight_percent, 0) }}%)
                                            </option>
                                        @endforeach
                                    </select>
                                </td>
                                <td class="px-4 py-2">
                                    <input
                                        form="exam-mark-form-{{ $enrollment->student_id }}"
                                        type="number"
                                        name="marks_obtained"
                                        value="{{ $existingMark->marks_obtained ?? '' }}"
                                        step="0.01"
                                        min="0"
                                        required
                                        class="block w-28 rounded-md border-0 py-1.5 px-2.5 text-sm text-gray-900 ring-1 ring-inset ring-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-600"
                                    >
                                </td>
                                <td class="px-4 py-2">
                                    <input
                                        form="exam-mark-form-{{ $enrollment->student_id }}"
                                        type="number"
                                        name="max_marks"
                                        value="{{ $existingMark->max_marks ?? '' }}"
                                        step="0.01"
                                        min="0"
                                        required
                                        class="block w-28 rounded-md border-0 py-1.5 px-2.5 text-sm text-gray-900 ring-1 ring-inset ring-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-600"
                                    >
                                </td>
                                <td class="px-4 py-2 text-right">
                                    <x-button form="exam-mark-form-{{ $enrollment->student_id }}" type="submit" variant="secondary" size="sm">
                                        {{ $existingMark ? 'Update' : 'Record' }}
                                    </x-button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </x-card>
        @endif
    </div>

    <div>
        <h2 class="text-sm font-semibold text-gray-900">Remarks</h2>

        @if ($enrollments->isEmpty())
            <x-empty-state
                title="No students enrolled"
                description="This class section has no active enrollments to remark on."
                class="mt-2"
            />
        @else
            <x-card :padded="false" class="mt-2">
                <div class="divide-y divide-gray-100">
                    @foreach ($enrollments as $enrollment)
                        @php $existingRemark = $existingRemarks->get($enrollment->student_id); @endphp
                        <form method="POST" action="{{ route('teacher.exam-remarks.store') }}" class="flex items-start gap-2 px-4 py-3 sm:px-6">
                            @csrf
                            <input type="hidden" name="exam_id" value="{{ $exam->id }}">
                            <input type="hidden" name="student_id" value="{{ $enrollment->student_id }}">
                            <label class="w-32 shrink-0 pt-1.5 text-sm font-medium text-gray-900">{{ $enrollment->student?->name ?? '-' }}</label>
                            <textarea
                                name="remark"
                                rows="1"
                                placeholder="Class teacher remark"
                                class="block w-full rounded-md border-0 py-1.5 px-2.5 text-sm text-gray-900 ring-1 ring-inset ring-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-600"
                            >{{ old('remark', $existingRemark?->class_teacher_remark) }}</textarea>
                            <x-button type="submit" variant="secondary" size="sm">Save</x-button>
                        </form>
                    @endforeach
                </div>
            </x-card>
        @endif
    </div>
@endsection
