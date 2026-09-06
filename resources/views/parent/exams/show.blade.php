@extends('layouts.app')

@section('title', $exam->name . ' - SchoolOS')
@section('page-title', 'Exam')

@section('content')
    <a href="{{ route('parent.exams.index') }}" class="inline-flex items-center gap-1 text-sm text-gray-500 hover:text-gray-700">
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
                <dt class="text-gray-500">Exam date</dt>
                <dd class="col-span-2 text-gray-900">{{ $exam->exam_date?->format('M j, Y') }}</dd>
            </div>
        </dl>
    </x-card>

    <div>
        <h2 class="text-sm font-semibold text-gray-900">Published results</h2>

        @if ($marks->isEmpty())
            <x-empty-state
                title="Not yet available"
                description="Published results for this exam are not available yet."
                class="mt-2"
            />
        @else
            <div class="mt-2 space-y-3">
                @foreach ($marks->groupBy('student_id') as $studentId => $studentMarks)
                    @php $remark = $remarksByStudent->get($studentId); @endphp
                    <x-card :padded="false">
                        <div class="flex items-center justify-between gap-3 border-b border-gray-100 px-4 py-3 sm:px-6">
                            <div>
                                <p class="text-sm font-semibold text-gray-900">{{ $studentMarks->first()->student?->name ?? 'Student' }}</p>
                                <p class="mt-0.5 text-xs text-gray-500">Weighted total: {{ number_format((float) $weightedTotals->get($studentId), 2) }}%</p>
                            </div>
                            <x-button href="{{ route('parent.exams.results.download', [$exam, $studentId]) }}" variant="secondary" size="sm">
                                Download PDF
                            </x-button>
                        </div>
                        <table class="min-w-full divide-y divide-gray-100 text-sm">
                            <tbody class="divide-y divide-gray-100">
                                @foreach ($studentMarks as $mark)
                                    <tr>
                                        <td class="px-4 py-2 font-medium text-gray-900">{{ $mark->component?->name ?? 'Component' }}</td>
                                        <td class="px-4 py-2 text-gray-700">{{ $mark->marks_obtained }} / {{ $mark->max_marks }}</td>
                                        <td class="px-4 py-2 text-gray-500">{{ $mark->component?->weight_percent }}%</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                        @if ($remark?->class_teacher_remark || $remark?->proprietor_remark)
                            <div class="border-t border-gray-100 px-4 py-3 text-xs text-gray-600 sm:px-6">
                                @if ($remark->class_teacher_remark)
                                    <p>Class teacher remark: {{ $remark->class_teacher_remark }}</p>
                                @endif
                                @if ($remark->proprietor_remark)
                                    <p class="mt-1">Proprietor remark: {{ $remark->proprietor_remark }}</p>
                                @endif
                            </div>
                        @endif
                    </x-card>
                @endforeach
            </div>
        @endif
    </div>
@endsection
