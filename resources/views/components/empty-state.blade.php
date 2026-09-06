@props(['title', 'description' => null])

<div {{ $attributes->merge(['class' => 'flex flex-col items-center justify-center rounded-lg border border-dashed border-gray-300 px-6 py-12 text-center']) }}>
    <svg class="h-10 w-10 text-gray-300" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.4">
        <path d="M4 7h12M4 7a1 1 0 011-1h10a1 1 0 011 1M4 7v9a1 1 0 001 1h10a1 1 0 001-1V7M8 4h4" stroke-linecap="round" stroke-linejoin="round"/>
    </svg>
    <p class="mt-3 text-sm font-medium text-gray-900">{{ $title }}</p>
    @if ($description)
        <p class="mt-1 max-w-sm text-sm text-gray-500">{{ $description }}</p>
    @endif
    @isset($action)
        <div class="mt-4">{{ $action }}</div>
    @endisset
</div>
