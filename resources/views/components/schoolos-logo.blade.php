@props([
    'href' => route('welcome'),
    'onDark' => false,
])

<a href="{{ $href }}" {{ $attributes->merge(['class' => 'flex items-center gap-2']) }} aria-label="SchoolOS home">
    <span class="flex h-10 w-10 items-center justify-center rounded-md {{ $onDark ? 'bg-green-500' : 'bg-green-600' }} text-sm font-bold text-white">S</span>
    <span class="{{ $onDark ? 'text-white' : 'text-gray-950' }} text-lg font-semibold">SchoolOS</span>
</a>
