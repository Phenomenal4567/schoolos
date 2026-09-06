@props([
    'name',
    'label' => null,
    'options' => [], // value => label
    'value' => null,
    'required' => false,
    'error' => null,
    'useOld' => true, // set false when $name repeats across several forms on one page (old() can't disambiguate which one)
])

@php
    $error = $error ?? $errors->first($name);
    $selected = $useOld ? old($name, $value) : $value;
@endphp

<div>
    @if ($label)
        <label for="{{ $name }}" class="mb-1 block text-sm font-medium text-gray-700">
            {{ $label }}
            @if ($required)<span class="text-red-500">*</span>@endif
        </label>
    @endif

    <select
        id="{{ $name }}"
        name="{{ $name }}"
        @if ($required) required @endif
        {{ $attributes->merge([
            'class' => 'block w-full rounded-md border-0 py-1.5 pl-2.5 pr-8 text-sm text-gray-900 ring-1 ring-inset '
                . ($error ? 'ring-red-300 focus:ring-red-500' : 'ring-gray-300 focus:ring-indigo-600')
                . ' focus:outline-none focus:ring-2',
        ]) }}
    >
        @foreach ($options as $optValue => $optLabel)
            <option value="{{ $optValue }}" @selected((string) $selected === (string) $optValue)>{{ $optLabel }}</option>
        @endforeach
    </select>

    @if ($error)
        <p class="mt-1 text-sm text-red-600">{{ $error }}</p>
    @endif
</div>
