@props(['variant' => 'info', 'dismissible' => true])

@php
    $styles = [
        'success' => ['bg-green-50 text-green-800 ring-green-600/20', 'M16.7 5.3a1 1 0 010 1.4l-7 7a1 1 0 01-1.4 0l-3-3a1 1 0 111.4-1.4l2.3 2.29 6.3-6.3a1 1 0 011.4 0z'],
        'error' => ['bg-red-50 text-red-800 ring-red-600/20', 'M10 2a8 8 0 100 16 8 8 0 000-16zM9 6h2v6H9V6zm0 8h2v2H9v-2z'],
        'warning' => ['bg-amber-50 text-amber-800 ring-amber-600/20', 'M10 2a8 8 0 100 16 8 8 0 000-16zM9 6h2v6H9V6zm0 8h2v2H9v-2z'],
        'info' => ['bg-blue-50 text-blue-800 ring-blue-600/20', 'M10 2a8 8 0 100 16 8 8 0 000-16zM9 6h2v2H9V6zm0 4h2v4H9v-4z'],
    ];
    [$colorClasses, $iconPath] = $styles[$variant] ?? $styles['info'];
@endphp

<div {{ $attributes->merge(['class' => "flex items-start gap-2.5 rounded-md p-3 text-sm ring-1 ring-inset $colorClasses"]) }} role="status" @if($dismissible) data-alert @endif>
    <svg class="mt-0.5 h-4 w-4 shrink-0" viewBox="0 0 20 20" fill="currentColor"><path d="{{ $iconPath }}"/></svg>
    <div class="flex-1">{{ $slot }}</div>
    @if ($dismissible)
        <button type="button" class="shrink-0 text-current opacity-60 hover:opacity-100" data-alert-dismiss aria-label="Dismiss">
            <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor"><path d="M6 6l8 8M14 6l-8 8" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>
        </button>
    @endif
</div>
