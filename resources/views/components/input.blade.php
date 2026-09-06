@props([
    'name',
    'label' => null,
    'type' => 'text',
    'value' => null,
    'required' => false,
    'error' => null,
])

@php
    $error = $error ?? $errors->first($name);
    $inputValue = old($name, $value);
@endphp

<div>
    @if ($label)
        <label for="{{ $name }}" class="mb-1 block text-sm font-medium text-gray-700">
            {{ $label }}
            @if ($required)<span class="text-red-500">*</span>@endif
        </label>
    @endif

    <input
        id="{{ $name }}"
        name="{{ $name }}"
        type="{{ $type }}"
        value="{{ $inputValue }}"
        @if ($required) required @endif
        {{ $attributes->merge([
            'class' => 'block w-full rounded-md border-0 py-1.5 px-2.5 text-sm text-gray-900 ring-1 ring-inset '
                . ($error ? 'ring-red-300 focus:ring-red-500' : 'ring-gray-300 focus:ring-indigo-600')
                . ' placeholder:text-gray-400 focus:outline-none focus:ring-2',
        ]) }}
    >

    @if ($error)
        <p class="mt-1 text-sm text-red-600">{{ $error }}</p>
    @endif
</div>
