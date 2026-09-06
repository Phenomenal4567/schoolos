@props(['padded' => true])

<div {{ $attributes->merge(['class' => 'rounded-lg border border-gray-200 bg-white shadow-sm ' . ($padded ? 'p-4 sm:p-6' : '')]) }}>
    {{ $slot }}
</div>
