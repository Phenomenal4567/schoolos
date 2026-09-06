<header class="sticky top-0 z-50 border-b border-transparent bg-white/80 backdrop-blur transition" data-landing-nav>
    <nav class="mx-auto flex h-16 max-w-7xl items-center justify-between px-4 sm:px-6 lg:px-8" aria-label="Primary navigation">
        <x-schoolos-logo />

        <div class="hidden items-center gap-7 text-sm font-semibold text-gray-600 lg:flex">
            <a href="{{ route('marketing.features.index') }}" class="hover:text-green-700">Features</a>
            <a href="{{ route('marketing.solutions.school-admins') }}" class="hover:text-green-700">Solutions</a>
            <a href="{{ route('marketing.pricing') }}" class="hover:text-green-700">Pricing</a>
            <a href="{{ route('marketing.help') }}" class="hover:text-green-700">Resources</a>
        </div>

        <div class="hidden items-center gap-3 lg:flex">
            <x-theme-toggle />
            <a href="{{ route('login') }}" class="rounded-md px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">Sign in</a>
            <a href="{{ route('public.onboarding.create') }}" class="rounded-md bg-green-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-green-700">Get started</a>
        </div>

        <div class="flex items-center gap-2 lg:hidden">
            <a href="{{ route('public.onboarding.create') }}" class="rounded-md bg-green-600 px-3 py-2 text-sm font-semibold text-white">Get started</a>
            <button type="button" class="rounded-md border border-gray-200 p-2 text-gray-700" data-mobile-menu-toggle aria-label="Open navigation">
                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M4 7h16M4 12h16M4 17h16" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
            </button>
        </div>
    </nav>
    <div class="hidden border-t border-gray-100 bg-white px-4 py-3 lg:hidden" data-mobile-menu>
        <div class="grid gap-2 text-sm font-semibold text-gray-700">
            <a href="{{ route('marketing.features.index') }}" class="rounded-md px-3 py-2 hover:bg-green-50">Features</a>
            <a href="{{ route('marketing.solutions.school-admins') }}" class="rounded-md px-3 py-2 hover:bg-green-50">Solutions</a>
            <a href="{{ route('marketing.pricing') }}" class="rounded-md px-3 py-2 hover:bg-green-50">Pricing</a>
            <a href="{{ route('marketing.help') }}" class="rounded-md px-3 py-2 hover:bg-green-50">Resources</a>
            <a href="{{ route('login') }}" class="rounded-md px-3 py-2 hover:bg-green-50">Sign in</a>
            <x-theme-toggle compact />
        </div>
    </div>
</header>
