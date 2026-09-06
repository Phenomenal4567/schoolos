@props(['id', 'title' => null])

<dialog id="{{ $id }}" class="w-full max-w-md rounded-lg border-0 p-0 shadow-xl backdrop:bg-gray-900/40" data-dialog>
    <div class="flex items-start justify-between border-b border-gray-200 px-4 py-3">
        <h2 class="text-sm font-semibold text-gray-900">{{ $title }}</h2>
        <button type="button" class="text-gray-400 hover:text-gray-600" data-dialog-close aria-label="Close dialog">
            <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor"><path d="M6 6l8 8M14 6l-8 8" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>
        </button>
    </div>
    <div class="px-4 py-4">
        {{ $slot }}
    </div>
</dialog>
