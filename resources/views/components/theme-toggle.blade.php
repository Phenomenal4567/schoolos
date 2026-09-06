@props([
    'compact' => false,
])

<div class="{{ $compact ? 'grid gap-2' : 'flex items-center gap-1' }}" data-theme-toggle>
    @foreach (['light' => 'Light', 'dark' => 'Dark', 'system' => 'System'] as $value => $label)
        <button
            type="button"
            class="{{ $compact ? 'w-full justify-start' : '' }} inline-flex min-h-10 items-center gap-2 rounded-md border border-[color:var(--so-border)] bg-[color:var(--so-surface-elevated)] px-3 text-sm font-semibold text-[color:var(--so-text-secondary)] transition hover:text-[color:var(--so-text)] focus:outline-none focus:ring-4 focus:ring-[color:var(--so-focus)] data-[active=true]:border-[color:var(--so-brand)] data-[active=true]:text-[color:var(--so-brand-strong)]"
            data-theme-option="{{ $value }}"
            data-active="false"
            aria-pressed="false"
        >
            <span aria-hidden="true">{{ $value === 'light' ? '☀' : ($value === 'dark' ? '☾' : '◐') }}</span>
            <span>{{ $label }}</span>
        </button>
    @endforeach
</div>
