<!DOCTYPE html>
<html lang="en" class="h-full bg-gray-50">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'SchoolOS')</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="h-full text-gray-900 antialiased">
@php
    $actor = auth()->user();
@endphp

<div class="min-h-full lg:flex">
    {{-- Sidebar --}}
    <aside
        id="app-sidebar"
        class="hidden lg:flex lg:w-64 lg:flex-col lg:fixed lg:inset-y-0 border-r border-gray-200 bg-white"
    >
        <div class="flex h-16 items-center gap-2 border-b border-gray-200 px-5">
            <span class="flex h-8 w-8 items-center justify-center rounded-md bg-indigo-600 text-sm font-semibold text-white">S</span>
            <span class="text-sm font-semibold tracking-tight text-gray-900">SchoolOS</span>
        </div>

        <nav class="flex-1 space-y-1 overflow-y-auto px-3 py-4" aria-label="Main navigation">
            @include('layouts.nav', ['actor' => $actor])
        </nav>
    </aside>

    {{-- Mobile nav overlay --}}
    <div id="mobile-nav-overlay" class="fixed inset-0 z-40 hidden bg-gray-900/40 lg:hidden" data-mobile-nav-overlay></div>
    <aside
        id="mobile-nav"
        class="fixed inset-y-0 left-0 z-50 w-72 -translate-x-full transform bg-white shadow-xl transition-transform duration-200 ease-in-out lg:hidden"
        data-mobile-nav
    >
        <div class="flex h-16 items-center justify-between border-b border-gray-200 px-5">
            <span class="text-sm font-semibold tracking-tight text-gray-900">SchoolOS</span>
            <button type="button" class="rounded-md p-1.5 text-gray-500 hover:bg-gray-100" data-mobile-nav-close aria-label="Close navigation">
                <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor"><path d="M6 6l8 8M14 6l-8 8" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>
            </button>
        </div>
        <nav class="flex-1 space-y-1 overflow-y-auto px-3 py-4" aria-label="Main navigation (mobile)">
            @include('layouts.nav', ['actor' => $actor])
        </nav>
    </aside>

    {{-- Main column --}}
    <div class="flex min-h-full flex-1 flex-col lg:pl-64">
        <header class="sticky top-0 z-30 flex h-16 items-center gap-3 border-b border-gray-200 bg-white px-4 sm:px-6">
            <button type="button" class="rounded-md p-2 text-gray-500 hover:bg-gray-100 lg:hidden" data-mobile-nav-open aria-label="Open navigation">
                <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor"><path d="M3 5h14M3 10h14M3 15h14" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>
            </button>

            <div class="min-w-0 flex-1">
                @if ($actor?->school)
                    <p class="truncate text-xs font-medium text-gray-400">{{ $actor->school->name }}</p>
                @endif
                <h1 class="truncate text-base font-semibold text-gray-900">@yield('page-title', 'SchoolOS')</h1>
            </div>

            @php
                // Notifications exist as a route for teacher/parent/student
                // portals only (see routes/web.php) — admin has no
                // notifications.index route, so the bell stays a disabled
                // placeholder for that role rather than linking to a route
                // that doesn't exist for it.
                $notificationsRoute = match ($actor?->role?->key) {
                    'teacher' => 'teacher.notifications.index',
                    'parent' => 'parent.notifications.index',
                    'student' => 'student.notifications.index',
                    default => null,
                };
                $unreadNotificationsCount = $notificationsRoute ? $actor->unreadNotifications()->count() : 0;
            @endphp

            @if ($notificationsRoute)
                <a
                    href="{{ route($notificationsRoute) }}"
                    class="relative rounded-md p-2 text-gray-400 hover:bg-gray-100 hover:text-gray-600"
                    aria-label="Notifications"
                >
                    <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor"><path d="M10 2a5 5 0 00-5 5v2.586l-1.707 1.707A1 1 0 004 13h12a1 1 0 00.707-1.707L15 9.586V7a5 5 0 00-5-5zM8.5 15a1.5 1.5 0 003 0h-3z"/></svg>
                    @if ($unreadNotificationsCount > 0)
                        <span class="absolute -right-0.5 -top-0.5 flex h-4 min-w-[1rem] items-center justify-center rounded-full bg-red-500 px-1 text-[10px] font-semibold leading-none text-white">
                            {{ $unreadNotificationsCount > 99 ? '99+' : $unreadNotificationsCount }}
                        </span>
                    @endif
                </a>
            @else
                <button type="button" class="rounded-md p-2 text-gray-400 hover:bg-gray-100" title="Notifications (coming soon)" disabled aria-label="Notifications">
                    <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor"><path d="M10 2a5 5 0 00-5 5v2.586l-1.707 1.707A1 1 0 004 13h12a1 1 0 00.707-1.707L15 9.586V7a5 5 0 00-5-5zM8.5 15a1.5 1.5 0 003 0h-3z"/></svg>
                </button>
            @endif

            <div class="relative" data-user-menu>
                <button type="button" class="flex items-center gap-2 rounded-md py-1.5 pl-1.5 pr-2 hover:bg-gray-100" data-user-menu-toggle>
                    <span class="flex h-7 w-7 items-center justify-center rounded-full bg-gray-200 text-xs font-semibold text-gray-700">
                        {{ strtoupper(substr($actor?->name ?? '?', 0, 1)) }}
                    </span>
                    <span class="hidden text-sm font-medium text-gray-700 sm:block">{{ $actor?->name }}</span>
                    <svg class="h-4 w-4 text-gray-400" viewBox="0 0 20 20" fill="currentColor"><path d="M5.5 7.5l4.5 4 4.5-4"/></svg>
                </button>
                <div class="absolute right-0 z-40 mt-2 hidden w-48 rounded-md border border-gray-200 bg-white py-1 shadow-lg" data-user-menu-panel>
                    <div class="border-b border-gray-100 px-3 py-2">
                        <p class="truncate text-sm font-medium text-gray-900">{{ $actor?->name }}</p>
                        <p class="truncate text-xs text-gray-500">{{ ucwords(str_replace('_', ' ', $actor?->role?->key ?? '')) }}</p>
                    </div>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="block w-full px-3 py-2 text-left text-sm text-gray-700 hover:bg-gray-50">Sign out</button>
                    </form>
                </div>
            </div>
        </header>

        <main class="flex-1 px-4 py-6 sm:px-6 lg:px-8">
            <div class="mx-auto max-w-5xl space-y-4">
                @if (session('status'))
                    <x-alert variant="success">{{ session('status') }}</x-alert>
                @endif

                @yield('content')
            </div>
        </main>
    </div>
</div>
</body>
</html>
