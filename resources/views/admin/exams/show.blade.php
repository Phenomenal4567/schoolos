@extends('layouts.app')

@section('title', $exam->name . ' - SchoolOS')
@section('page-title', 'Exam Results')

@section('content')
    <a href="{{ route('admin.exams.index') }}" class="inline-flex items-center gap-1 text-sm text-gray-500 hover:text-gray-700">
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
        </dl>
    </x-card>

    @if ($marksByStudent->isEmpty())
        <x-empty-state title="No marks recorded" description="Teacher-entered marks will appear here for review." />
    @else
        <div class="space-y-3">
            @foreach ($marksByStudent as $result)
                @php $remark = $remarksByStudent->get($result['student']?->id); @endphp
                <x-card :padded="false">
                    <div class="border-b border-gray-100 px-4 py-3 sm:px-6">
                        <p class="text-sm font-semibold text-gray-900">{{ $result['student']?->name ?? 'Student' }}</p>
                        <p class="mt-0.5 text-xs text-gray-500">Weighted total: {{ number_format((float) $result['weighted_total'], 2) }}%</p>
                    </div>
                    <table class="min-w-full divide-y divide-gray-100 text-sm">
                        <thead>
                            <tr class="text-left text-xs font-medium uppercase tracking-wide text-gray-500">
                                <th class="px-4 py-2">Component</th>
                                <th class="px-4 py-2">Marks</th>
                                <th class="px-4 py-2">Status</th>
                                <th class="px-4 py-2"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach ($result['marks'] as $mark)
                                <tr>
                                    <td class="px-4 py-2 font-medium text-gray-900">{{ $mark->component?->name ?? 'Component' }}</td>
                                    <td class="px-4 py-2">
                                        <form id="mark-update-{{ $mark->id }}" method="POST" action="{{ route('admin.exams.marks.update', [$exam, $mark]) }}" class="flex gap-2">
                                            @csrf
                                            <input type="number" name="marks_obtained" value="{{ $mark->marks_obtained }}" step="0.01" min="0" required class="block w-24 rounded-md border-0 py-1.5 px-2.5 text-sm text-gray-900 ring-1 ring-inset ring-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-600">
                                            <input type="number" name="max_marks" value="{{ $mark->max_marks }}" step="0.01" min="0.01" required class="block w-24 rounded-md border-0 py-1.5 px-2.5 text-sm text-gray-900 ring-1 ring-inset ring-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-600">
                                        </form>
                                    </td>
                                    <td class="px-4 py-2">
                                        <x-badge variant="{{ $mark->status === 'published' ? 'success' : ($mark->status === 'reviewed' ? 'warning' : 'neutral') }}">
                                            {{ ucfirst($mark->status) }}
                                        </x-badge>
                                    </td>
                                    <td class="px-4 py-2">
                                        <div class="flex justify-end gap-2">
                                            <x-button form="mark-update-{{ $mark->id }}" type="submit" variant="secondary" size="sm">Save</x-button>
                                            @if ($mark->status === 'draft')
                                                <form method="POST" action="{{ route('admin.exams.marks.review', [$exam, $mark]) }}">
                                                    @csrf
                                                    <x-button type="submit" variant="secondary" size="sm">Review</x-button>
                                                </form>
                                            @endif
                                            @if ($mark->status === 'reviewed')
                                                <form method="POST" action="{{ route('admin.exams.marks.publish', [$exam, $mark]) }}">
                                                    @csrf
                                                    <x-button type="submit" variant="primary" size="sm">Publish</x-button>
                                                </form>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>

                    <div class="border-t border-gray-100 px-4 py-3 sm:px-6">
                        @if ($remark?->class_teacher_remark)
                            <p class="mb-2 text-xs text-gray-500">Class teacher remark: {{ $remark->class_teacher_remark }}</p>
                        @endif
                        <form method="POST" action="{{ route('admin.exams.remarks.update', [$exam, $result['student']->id]) }}" class="space-y-2">
                            @csrf
                            <label class="block text-xs font-medium text-gray-700">Proprietor remark</label>
                            <textarea
                                name="remark"
                                rows="2"
                                class="block w-full rounded-md border-0 py-1.5 px-2.5 text-sm text-gray-900 ring-1 ring-inset ring-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-600"
                            >{{ old('remark', $remark?->proprietor_remark) }}</textarea>
                            <x-button type="submit" variant="secondary" size="sm">Save remark</x-button>
                        </form>
                    </div>
                </x-card>
            @endforeach
        </div>
    @endif
@endsection
