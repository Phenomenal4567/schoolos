<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
    <meta charset="utf-8">
    <title>{{ $page['title'] }} - SchoolOS</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <x-theme-script />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="schoolos-public bg-white text-[#111827] antialiased">
<x-public-navbar />

<main>
    <section class="schoolos-hero bg-[radial-gradient(circle_at_top_right,#DCFCE7,transparent_34%),linear-gradient(180deg,#F0FDF4_0%,#FFFFFF_70%)] py-20">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="max-w-3xl" data-reveal>
                <p class="text-sm font-bold uppercase tracking-[0.14em] text-green-700">SchoolOS</p>
                <h1 class="mt-5 text-4xl font-bold tracking-tight text-gray-950 sm:text-5xl">{{ $page['title'] }}</h1>
                <p class="mt-6 text-lg leading-8 text-gray-600">{{ $page['intro'] }}</p>
                <div class="mt-8 flex flex-col gap-3 sm:flex-row">
                    <a href="{{ route('public.onboarding.create') }}" class="inline-flex min-h-12 items-center justify-center rounded-md bg-green-600 px-6 text-sm font-bold text-white shadow-sm transition hover:-translate-y-0.5 hover:bg-green-700">Get Started</a>
                    <a href="{{ route('marketing.demo') }}" class="inline-flex min-h-12 items-center justify-center rounded-md border border-gray-200 bg-white px-6 text-sm font-bold text-gray-800 shadow-sm transition hover:-translate-y-0.5 hover:border-green-200 hover:bg-green-50">Book a Demo</a>
                </div>
            </div>
        </div>
    </section>

    @if ($page['type'] === 'feature')
        <section class="bg-white py-16">
            <div class="mx-auto grid max-w-7xl gap-8 px-4 sm:px-6 lg:grid-cols-[0.8fr_1.2fr] lg:px-8">
                <div data-reveal>
                    <h2 class="text-3xl font-bold tracking-tight text-gray-950">Overview</h2>
                    <p class="mt-4 text-base leading-7 text-gray-600">{{ $page['intro'] }}</p>
                </div>
                <div class="grid gap-4 sm:grid-cols-2">
                    @foreach ($page['items'] as $item)
                        <article class="rounded-xl border border-gray-200 bg-slate-50 p-5 shadow-sm" data-reveal>
                            <h3 class="text-base font-bold text-gray-950">{{ $item }}</h3>
                            <p class="mt-3 text-sm leading-6 text-gray-600">Designed to keep everyday school workflows organized, visible, and easy for the right people to use.</p>
                        </article>
                    @endforeach
                </div>
            </div>
        </section>
        <section class="bg-slate-50 py-16">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <h2 class="text-3xl font-bold tracking-tight text-gray-950">Benefits</h2>
                <div class="mt-8 grid gap-4 md:grid-cols-3">
                    @foreach ($page['benefits'] as $benefit)
                        <div class="rounded-xl border border-gray-200 bg-white p-5 text-sm font-semibold text-gray-700 shadow-sm" data-reveal>{{ $benefit }}</div>
                    @endforeach
                </div>
            </div>
        </section>
    @elseif ($page['type'] === 'solution')
        <section class="bg-white py-16">
            <div class="mx-auto grid max-w-7xl gap-8 px-4 sm:px-6 lg:grid-cols-3 lg:px-8">
                <div data-reveal>
                    <h2 class="text-2xl font-bold text-gray-950">Problems We Solve</h2>
                    <ul class="mt-5 space-y-3">
                        @foreach ($page['items'] as $item)
                            <li class="rounded-lg border border-gray-200 bg-slate-50 px-4 py-3 text-sm font-semibold text-gray-700">{{ $item }}</li>
                        @endforeach
                    </ul>
                </div>
                <div data-reveal>
                    <h2 class="text-2xl font-bold text-gray-950">Relevant Features</h2>
                    <ul class="mt-5 space-y-3">
                        @foreach ($page['features'] as $feature)
                            <li class="rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm font-semibold text-green-800">{{ $feature }}</li>
                        @endforeach
                    </ul>
                </div>
                <div data-reveal>
                    <h2 class="text-2xl font-bold text-gray-950">Benefits</h2>
                    <ul class="mt-5 space-y-3">
                        @foreach ($page['benefits'] as $benefit)
                            <li class="rounded-lg border border-gray-200 bg-white px-4 py-3 text-sm font-semibold text-gray-700 shadow-sm">{{ $benefit }}</li>
                        @endforeach
                    </ul>
                </div>
            </div>
        </section>
    @elseif ($page['type'] === 'legal')
        <section class="bg-white py-16">
            <article class="mx-auto max-w-3xl px-4 text-base leading-8 text-gray-700 sm:px-6 lg:px-8" data-reveal>
                <h2 class="text-2xl font-bold text-gray-950">Document Overview</h2>
                <p class="mt-4">{{ $page['intro'] }}</p>
                <div class="mt-8 space-y-6">
                    @foreach ($page['items'] as $item)
                        <section>
                            <h3 class="text-lg font-bold text-gray-950">{{ ['Data Use', 'Access & Responsibility', 'Platform Care'][$loop->index] ?? 'Policy Detail' }}</h3>
                            <p class="mt-2">{{ $item }}</p>
                        </section>
                    @endforeach
                </div>
            </article>
        </section>
    @else
        <section class="bg-white py-16">
            <div class="mx-auto max-w-5xl px-4 sm:px-6 lg:px-8">
                <div class="grid gap-4 md:grid-cols-3">
                    @foreach ($page['items'] as $item)
                        <article class="rounded-xl border border-gray-200 bg-slate-50 p-5 shadow-sm" data-reveal>
                            <h2 class="text-base font-bold text-gray-950">{{ $item }}</h2>
                            <p class="mt-3 text-sm leading-6 text-gray-600">A practical part of helping schools adopt SchoolOS with clarity and confidence.</p>
                        </article>
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    <section class="bg-slate-50 py-16">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="rounded-xl border border-green-100 bg-green-50 p-6 sm:p-8" data-reveal>
                <h2 class="text-2xl font-bold text-gray-950">Bring your school into one connected system.</h2>
                <p class="mt-3 max-w-2xl text-sm leading-6 text-gray-600">Start with the essentials, invite the right people, and grow into the full SchoolOS platform as your workflows mature.</p>
                <div class="mt-6 flex flex-col gap-3 sm:flex-row">
                    <a href="{{ route('public.onboarding.create') }}" class="inline-flex min-h-12 items-center justify-center rounded-md bg-green-600 px-6 text-sm font-bold text-white shadow-sm transition hover:-translate-y-0.5 hover:bg-green-700">{{ $page['cta'] ?? 'Get Started' }}</a>
                    <a href="{{ route('marketing.support') }}" class="inline-flex min-h-12 items-center justify-center rounded-md border border-gray-200 bg-white px-6 text-sm font-bold text-gray-800 transition hover:-translate-y-0.5 hover:border-green-200 hover:bg-green-50">Contact Support</a>
                </div>
            </div>
        </div>
    </section>
</main>

<x-public-footer />
</body>
</html>
