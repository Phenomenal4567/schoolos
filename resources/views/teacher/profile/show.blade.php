@extends('layouts.app')

@section('title', 'My Profile - SchoolOS')
@section('page-title', 'My Profile')

@section('content')
    <div class="grid gap-4 lg:grid-cols-3">
        <x-card :padded="false" class="lg:col-span-1">
            <dl class="divide-y divide-gray-100 text-sm">
                <div class="grid grid-cols-3 gap-4 px-4 py-3 sm:px-6">
                    <dt class="text-gray-500">Name</dt>
                    <dd class="col-span-2 text-gray-900">{{ $teacher->name }}</dd>
                </div>
                <div class="grid grid-cols-3 gap-4 px-4 py-3 sm:px-6">
                    <dt class="text-gray-500">Email</dt>
                    <dd class="col-span-2 text-gray-900">{{ $teacher->email ?? '-' }}</dd>
                </div>
                <div class="grid grid-cols-3 gap-4 px-4 py-3 sm:px-6">
                    <dt class="text-gray-500">Mobile</dt>
                    <dd class="col-span-2 text-gray-900">{{ $teacher->mobile_no ?? '-' }}</dd>
                </div>
                <div class="grid grid-cols-3 gap-4 px-4 py-3 sm:px-6">
                    <dt class="text-gray-500">Staff ID</dt>
                    <dd class="col-span-2 text-gray-900">{{ $teacher->staff_id ?? '-' }}</dd>
                </div>
                <div class="grid grid-cols-3 gap-4 px-4 py-3 sm:px-6">
                    <dt class="text-gray-500">School</dt>
                    <dd class="col-span-2 text-gray-900">{{ $teacher->school?->name ?? '-' }}</dd>
                </div>
            </dl>
        </x-card>

        <x-card class="lg:col-span-2">
            <h2 class="text-sm font-semibold text-gray-900">Update profile</h2>
            <form method="POST" action="{{ route('teacher.profile.update') }}" class="mt-3 grid gap-3 sm:grid-cols-2">
                @csrf
                @method('PUT')
                <x-input name="name" label="Name" :value="$teacher->name" required />
                <x-input type="email" name="email" label="Email" :value="$teacher->email" />
                <x-input name="mobile_no" label="Mobile" :value="$teacher->mobile_no" />
                <x-input name="photo_path" label="Photo path" :value="$teacher->photo_path" />
                <div class="sm:col-span-2">
                    <x-button type="submit" variant="primary" size="sm">Save profile</x-button>
                </div>
            </form>
        </x-card>
    </div>

    <div class="grid gap-4 lg:grid-cols-2">
        <x-card>
            <h2 class="text-sm font-semibold text-gray-900">Qualifications &amp; responsibilities</h2>
            <form method="POST" action="{{ route('teacher.profile.update-details') }}" class="mt-3 space-y-3">
                @csrf
                @method('PUT')
                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-700">Qualifications (one per line)</label>
                    <textarea
                        name="qualifications"
                        rows="4"
                        class="block w-full rounded-md border-0 py-1.5 px-2.5 text-sm text-gray-900 ring-1 ring-inset ring-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-600"
                    >{{ old('qualifications', implode("\n", $staffProfile?->qualifications ?? [])) }}</textarea>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-700">Responsibilities</label>
                    <textarea
                        name="responsibilities"
                        rows="3"
                        class="block w-full rounded-md border-0 py-1.5 px-2.5 text-sm text-gray-900 ring-1 ring-inset ring-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-600"
                    >{{ old('responsibilities', $staffProfile?->responsibilities) }}</textarea>
                </div>
                <x-button type="submit" variant="primary" size="sm">Save details</x-button>
            </form>
        </x-card>

        <x-card>
            <h2 class="text-sm font-semibold text-gray-900">CV &amp; school rules</h2>

            <div class="mt-3">
                <p class="text-sm text-gray-700">
                    Current CV:
                    @if ($staffProfile?->cv_path)
                        <span class="font-medium text-gray-900">Uploaded</span>
                    @else
                        <span class="text-gray-400">None uploaded</span>
                    @endif
                </p>
                <form method="POST" action="{{ route('teacher.profile.cv.store') }}" enctype="multipart/form-data" class="mt-2 flex items-center gap-2">
                    @csrf
                    <input type="file" name="cv" accept=".pdf,.doc,.docx" required class="block w-full text-sm text-gray-700 file:mr-3 file:rounded-md file:border-0 file:bg-gray-100 file:px-3 file:py-1.5 file:text-sm file:font-medium file:text-gray-700 hover:file:bg-gray-200">
                    <x-button type="submit" variant="secondary" size="sm">Upload</x-button>
                </form>
                @error('cv')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div class="mt-4 border-t border-gray-100 pt-3">
                <p class="text-sm text-gray-700">
                    School rules &amp; regulations:
                    @if ($staffProfile?->rules_acknowledged_at)
                        <span class="font-medium text-gray-900">Acknowledged {{ $staffProfile->rules_acknowledged_at->format('M j, Y') }}</span>
                    @else
                        <span class="text-gray-400">Not yet acknowledged</span>
                    @endif
                </p>
                @unless ($staffProfile?->rules_acknowledged_at)
                    <form method="POST" action="{{ route('teacher.profile.acknowledge-rules') }}" class="mt-2">
                        @csrf
                        <x-button type="submit" variant="secondary" size="sm">I acknowledge the school rules</x-button>
                    </form>
                @endunless
            </div>
        </x-card>
    </div>

    <x-card>
        <h2 class="text-sm font-semibold text-gray-900">Supporting documents</h2>

        @if ($documents->isEmpty())
            <x-empty-state title="No documents yet" description="Certificates, references, or other supporting documents you upload will appear here." class="mt-2" />
        @else
            <ul class="mt-3 divide-y divide-gray-100 text-sm">
                @foreach ($documents as $document)
                    <li class="flex items-center justify-between gap-3 py-2">
                        <a href="{{ route('teacher.profile.documents.download', $document) }}" class="text-indigo-600 hover:underline">{{ $document->label }}</a>
                        <form method="POST" action="{{ route('teacher.profile.documents.destroy', $document) }}">
                            @csrf
                            @method('DELETE')
                            <x-button type="submit" variant="secondary" size="sm">Remove</x-button>
                        </form>
                    </li>
                @endforeach
            </ul>
        @endif

        <form method="POST" action="{{ route('teacher.profile.documents.store') }}" enctype="multipart/form-data" class="mt-3 flex items-end gap-2">
            @csrf
            <div class="flex-1">
                <label class="mb-1 block text-sm font-medium text-gray-700">Label</label>
                <input type="text" name="label" required class="block w-full rounded-md border-0 py-1.5 px-2.5 text-sm text-gray-900 ring-1 ring-inset ring-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-600">
            </div>
            <div class="flex-1">
                <label class="mb-1 block text-sm font-medium text-gray-700">File</label>
                <input type="file" name="document" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png" required class="block w-full text-sm text-gray-700 file:mr-3 file:rounded-md file:border-0 file:bg-gray-100 file:px-3 file:py-1.5 file:text-sm file:font-medium file:text-gray-700 hover:file:bg-gray-200">
            </div>
            <x-button type="submit" variant="secondary" size="sm">Upload</x-button>
        </form>
        @error('document')
            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
        @enderror
    </x-card>
@endsection
