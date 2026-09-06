@extends('layouts.app')

@section('title', 'Upload Lesson Document - SchoolOS')
@section('page-title', 'Upload Lesson Document')

@section('content')
    <x-card>
        <form method="POST" action="{{ route('admin.lesson-plans.store') }}" enctype="multipart/form-data" class="space-y-4">
            @csrf

            <x-input
                name="title"
                label="Title"
                :value="old('title')"
                required
            />

            <x-select
                name="class_section_id"
                label="Class section"
                :options="$classSections->mapWithKeys(fn ($classSection) => [
                    $classSection->id => trim(($classSection->standard?->name ?? '') . ' ' . ($classSection->section?->name ?? '')),
                ])"
                :value="old('class_section_id')"
                required
            />

            <x-select
                name="subject_id"
                label="Subject"
                :options="$subjects->pluck('name', 'id')"
                :value="old('subject_id')"
                required
            />

            <div>
                <label for="content" class="mb-1 block text-sm font-medium text-gray-700">Notes</label>
                <textarea id="content" name="content" rows="4" class="block w-full rounded-md border-0 py-1.5 px-2.5 text-sm text-gray-900 ring-1 ring-inset ring-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-600">{{ old('content') }}</textarea>
                @error('content')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="document" class="mb-1 block text-sm font-medium text-gray-700">Document</label>
                <input id="document" name="document" type="file" required class="block w-full text-sm text-gray-700 file:mr-3 file:rounded-md file:border-0 file:bg-indigo-50 file:px-3 file:py-2 file:text-sm file:font-medium file:text-indigo-700 hover:file:bg-indigo-100">
                @error('document')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
                @error('lesson_plan')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <x-button type="submit" variant="primary">Upload document</x-button>
        </form>
    </x-card>
@endsection
