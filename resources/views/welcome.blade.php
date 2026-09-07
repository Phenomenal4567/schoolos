<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
    <meta charset="utf-8">
    <title>SchoolOS - One connected school platform</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <x-theme-script />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="schoolos-public bg-white text-[#111827] antialiased">
@php
    $features = [
        ['Student Management', 'Manage student profiles, enrollment, guardians, classes, and academic history.', 'M16 21v-2a4 4 0 0 0-8 0v2M12 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8Z'],
        ['Attendance', 'Record attendance quickly and give schools flexible attendance workflows.', 'M9 12l2 2 4-5M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z'],
        ['Academics', 'Manage classes, subjects, assessments, results, report cards, and academic records.', 'M4 19.5V6a2 2 0 0 1 2-2h13v15.5M8 8h8M8 12h8M8 16h5'],
        ['Fees & Payments', 'Track school fees, payments, outstanding balances, part-payments, and receipts.', 'M4 7h16M4 11h16M7 15h4M6 19h12a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2H6a2 2 0 0 0-2 2v10a2 2 0 0 0 2 2Z'],
        ['Parent Portal', 'Give parents access to the information that matters about their children.', 'M17 21v-2a4 4 0 0 0-4-4H7a4 4 0 0 0-4 4v2M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8ZM23 21v-2a4 4 0 0 0-3-3.87'],
        ['Teacher Portal', 'Give teachers a focused workspace for attendance, academics, classes, and daily tasks.', 'M12 14l9-5-9-5-9 5 9 5ZM12 14v7M5 12v5c2 2 12 2 14 0v-5'],
        ['Communication', 'Keep parents, teachers, students, and administrators connected.', 'M21 15a4 4 0 0 1-4 4H8l-5 3V7a4 4 0 0 1 4-4h10a4 4 0 0 1 4 4v8Z'],
        ['Reports', 'Turn school data into clear reports and useful insights.', 'M4 19V5M9 19V9M14 19v-6M19 19V7'],
    ];

    $roles = [
        'School Admin' => ['See your whole school at a glance.', ['Enrollment', 'Attendance', 'Fees', 'Staff', 'Classes', 'Reports']],
        'Teacher' => ['Everything you need for your classroom.', ['Classes', 'Attendance', 'Subjects', 'Assessments', 'Results']],
        'Parent' => ["Stay connected to your child's school life.", ['Attendance', 'Results', 'Fees', 'Announcements', 'School communication']],
        'Student' => ['Everything you need to stay on track.', ['Timetable', 'Results', 'Attendance', 'Assignments', 'Announcements']],
        'Staff' => ['Simple tools for everyday school operations.', ['Daily tasks', 'Records', 'Attendance', 'Operations']],
    ];

    // [role, x%, y%, icon path] - positions plot a pentagon around the
    // central SchoolOS node in the "one connected system" hub diagram.
    $hubRoles = [
        ['School Admin', 12, 39, 'M4 21V9a1 1 0 0 1 1-1h4V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v4h4a1 1 0 0 1 1 1v12M4 21h16M9 21v-4h6v4M9 12h.01M15 12h.01M9 16h.01M15 16h.01'],
        ['Teacher', 88, 39, 'M12 14l9-5-9-5-9 5 9 5ZM12 14v7M5 12v5c2 2 12 2 14 0v-5'],
        ['Parent', 50, 12, 'M17 21v-2a4 4 0 0 0-4-4H7a4 4 0 0 0-4 4v2M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8ZM23 21v-2a4 4 0 0 0-3-3.87'],
        ['Student', 73.5, 78, 'M16 21v-2a4 4 0 0 0-8 0v2M12 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8Z'],
        ['Staff', 26.5, 78, 'M5 8h14M5 8a2 2 0 0 0-2 2v9a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-9a2 2 0 0 0-2-2M5 8V6a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2v2M9 13a2 2 0 1 0 4 0 2 2 0 0 0-4 0ZM8 18c.5-1.5 1.8-2 4-2s3.5.5 4 2'],
    ];

    $faqs = [
        'What is SchoolOS?' => 'SchoolOS is an all-in-one school management platform for student records, attendance, academics, fees, communication, reports, and daily operations.',
        'What types of schools can use SchoolOS?' => 'SchoolOS is built for creche, nursery, primary, secondary, private schools, academies, and growing educational institutions.',
        'Can parents access SchoolOS?' => 'Yes. Parent access is part of the SchoolOS product direction, with portals for child information, fees, results, announcements, and communication.',
        'Can schools accept part-payments?' => 'Yes. SchoolOS is designed to track full payments and part-payments against the same fee record.',
        'Can schools choose which modules they use?' => 'Yes. During onboarding, schools can choose the modules they need for their operations.',
        'Can one user have multiple roles?' => 'SchoolOS currently uses a single role per user in the existing RBAC architecture.',
        'Can schools manage multiple classes?' => 'Yes. Schools configure their academic structure, classes, sections, subjects, and related records.',
        'Can SchoolOS handle report cards?' => 'Yes. Academic records, assessments, results, and report-card workflows are part of the SchoolOS academic direction.',
        'How does school onboarding work?' => 'A school creates its workspace, chooses academic levels and modules, creates the first admin account, then continues into the existing setup wizard.',
        'Can schools customize their setup?' => 'Yes. School admins control the school profile, academic structure, modules, staff, students, and ongoing setup.',
    ];
@endphp

<x-public-navbar />

<main>
    <section class="schoolos-hero relative overflow-hidden bg-[radial-gradient(circle_at_top_right,#DCFCE7,transparent_34%),linear-gradient(180deg,#F0FDF4_0%,#FFFFFF_70%)]" data-glow-section>
        <div class="so-glow" data-cursor-glow aria-hidden="true"></div>
        <div class="relative mx-auto grid max-w-7xl gap-12 px-4 pb-16 pt-16 sm:px-6 lg:grid-cols-[0.9fr_1.1fr] lg:px-8 lg:pb-24 lg:pt-24">
            <div class="max-w-2xl">
                <p class="text-sm font-bold uppercase tracking-[0.14em] text-green-700" data-hero-in style="--hero-delay:0ms">The modern school operating system</p>
                <h1 class="mt-5 text-4xl font-bold leading-tight text-gray-950 sm:text-5xl lg:text-6xl" data-hero-in style="--hero-delay:90ms">Run your entire school from one simple platform.</h1>
                <p class="mt-6 text-lg leading-8 text-gray-600" data-hero-in style="--hero-delay:180ms">SchoolOS brings students, teachers, parents, attendance, academics, fees, communication, and everyday school operations into one beautifully connected system.</p>
                <div class="mt-8 flex flex-col gap-3 sm:flex-row" data-hero-in style="--hero-delay:270ms">
                    <a href="{{ route('public.onboarding.create') }}" class="inline-flex min-h-12 items-center justify-center rounded-md bg-green-600 px-6 text-sm font-bold text-white shadow-sm transition duration-200 hover:-translate-y-0.5 hover:bg-green-700 hover:shadow-md active:translate-y-0 active:scale-[0.98]">Get started free</a>
                    <a href="{{ route('marketing.features.index') }}" class="inline-flex min-h-12 items-center justify-center rounded-md border border-gray-200 bg-white px-6 text-sm font-bold text-gray-800 shadow-sm transition duration-200 hover:-translate-y-0.5 hover:border-green-200 hover:bg-green-50 active:translate-y-0 active:scale-[0.98]">See how SchoolOS works</a>
                </div>
                <p class="mt-5 text-sm font-medium text-gray-500" data-hero-in style="--hero-delay:330ms">Built for schools of every size - from early years to secondary education.</p>
            </div>

            <div class="relative min-h-[480px] lg:min-h-[560px]" data-parallax>
                <div class="schoolos-dashboard-dark absolute inset-x-0 top-6 mx-auto w-full max-w-2xl rounded-2xl border border-gray-200 bg-white p-4 shadow-2xl shadow-green-900/10" data-hero-in="scale" data-parallax-target style="--hero-delay:360ms">
                    <div class="rounded-xl border border-gray-100 bg-slate-50 p-4">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm font-semibold text-gray-500">Admin dashboard</p>
                                <h2 class="mt-1 text-xl font-bold text-gray-950">Greenwood Private School</h2>
                            </div>
                            <span class="rounded-full bg-green-100 px-3 py-1 text-xs font-bold text-green-700">Live term</span>
                        </div>
                        <div class="mt-5 grid gap-3 sm:grid-cols-3">
                            <div class="rounded-lg bg-white p-4 shadow-sm">
                                <p class="text-xs font-semibold text-gray-500">Students</p>
                                <p class="mt-2 text-2xl font-bold" data-count-up="1357">1,357</p>
                            </div>
                            <div class="rounded-lg bg-white p-4 shadow-sm">
                                <p class="text-xs font-semibold text-gray-500">Attendance</p>
                                <p class="mt-2 text-2xl font-bold text-green-700">94.6%</p>
                            </div>
                            <div class="rounded-lg bg-white p-4 shadow-sm">
                                <p class="text-xs font-semibold text-gray-500">Collected</p>
                                <p class="mt-2 text-2xl font-bold">₦8.42M</p>
                            </div>
                        </div>
                        <div class="mt-4 grid gap-4 lg:grid-cols-[1.1fr_0.9fr]">
                            <div class="rounded-lg bg-white p-4 shadow-sm">
                                <div class="flex items-center justify-between">
                                    <p class="text-sm font-bold">Attendance trend</p>
                                    <p class="text-xs text-gray-500">This week</p>
                                </div>
                                <div class="mt-5 flex h-32 items-end gap-3">
                                    @foreach ([55, 72, 64, 88, 82, 94] as $bar)
                                        <span class="flex-1 rounded-t-md bg-green-500/80" style="height: {{ $bar }}%"></span>
                                    @endforeach
                                </div>
                            </div>
                            <div class="space-y-3">
                                @foreach (['Teacher entered JSS 2 attendance', 'Parent notification queued', 'Mathematics class starts at 10:00'] as $activity)
                                    <div class="rounded-lg bg-white p-3 text-sm shadow-sm">
                                        <span class="font-semibold text-gray-950">{{ $activity }}</span>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>

                <div class="absolute left-0 top-0 w-56 rounded-xl border border-green-100 bg-white p-4 shadow-xl shadow-green-900/10" data-hero-in style="--hero-delay:440ms">
                    <p class="text-sm font-bold text-gray-950">Today's Attendance</p>
                    <p class="mt-2 text-3xl font-bold text-green-700">94.6%</p>
                    <p class="mt-2 text-xs text-gray-500">Present: 1,284</p>
                    <p class="text-xs text-gray-500">Absent: 73</p>
                </div>
                <div class="absolute bottom-8 right-2 w-56 rounded-xl border border-green-100 bg-white p-4 shadow-xl shadow-green-900/10" data-hero-in style="--hero-delay:500ms">
                    <p class="text-sm font-bold text-gray-950">Fee Collection</p>
                    <p class="mt-2 text-3xl font-bold">₦8.42M</p>
                    <p class="mt-2 text-xs font-semibold text-green-700">+12.4%</p>
                </div>
                <div class="absolute bottom-0 left-8 hidden max-w-xs rounded-xl border border-green-100 bg-white p-4 shadow-xl shadow-green-900/10 sm:block" data-hero-in style="--hero-delay:560ms">
                    <p class="text-sm font-bold text-gray-950">Parent notification sent</p>
                    <p class="mt-2 text-sm text-gray-600">"Attendance report delivered to parents."</p>
                </div>
            </div>
        </div>
    </section>

    <section class="border-y border-gray-100 bg-white py-16">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <h2 class="text-center text-2xl font-bold text-gray-950 sm:text-3xl" data-reveal>Everything your school needs. One connected system.</h2>
            <p class="mx-auto mt-3 max-w-xl text-center text-sm text-gray-500" data-reveal style="--reveal-delay:80ms">SchoolOS keeps every role live and in sync - one update reaches everyone who needs it.</p>

            <!-- Desktop: radial hub with SchoolOS at the center and each role connected live. -->
            <div class="so-hub relative mx-auto mt-14 hidden aspect-[16/9] w-full max-w-3xl lg:block" data-reveal style="--reveal-delay:120ms">
                <svg class="absolute inset-0 h-full w-full overflow-visible" viewBox="0 0 100 100" preserveAspectRatio="none" aria-hidden="true">
                    @foreach ($hubRoles as $i => [$label, $x, $y, $icon])
                        <line class="so-hub-line" x1="50" y1="50" x2="{{ $x }}" y2="{{ $y }}" stroke="#16a34a" stroke-width="0.5" stroke-linecap="round" style="--line-delay: {{ 150 + $i * 110 }}ms"/>
                    @endforeach
                </svg>

                <div class="so-hub-ring absolute left-1/2 top-1/2 h-28 w-28 rounded-full border-2 border-green-500" aria-hidden="true"></div>
                <div class="so-hub-center absolute left-1/2 top-1/2 flex flex-col items-center" aria-hidden="true">
                    <span class="flex h-20 w-20 items-center justify-center rounded-full bg-green-600 text-2xl font-extrabold text-white shadow-lg shadow-green-900/20">S</span>
                    <span class="mt-2 whitespace-nowrap text-sm font-extrabold text-gray-950">SchoolOS</span>
                </div>

                @foreach ($hubRoles as $i => [$label, $x, $y, $icon])
                    <article class="so-hub-node absolute w-36 rounded-xl border border-gray-200 bg-white p-3 text-center shadow-md shadow-green-900/5" style="left: {{ $x }}%; top: {{ $y }}%; --node-delay: {{ 260 + $i * 110 }}ms">
                        <span class="mx-auto flex h-9 w-9 items-center justify-center rounded-lg bg-green-50 text-green-700">
                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="{{ $icon }}" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
                        </span>
                        <p class="mt-2 text-xs font-bold text-gray-950">{{ $label }}</p>
                        <span class="mt-1.5 inline-flex items-center gap-1 text-[10px] font-semibold uppercase tracking-wide text-green-600">
                            <span class="so-hub-dot h-1.5 w-1.5 rounded-full bg-green-500"></span>
                            Live
                        </span>
                    </article>
                @endforeach
            </div>

            <!-- Mobile/tablet: simplified stacked version, still live, no absolute positioning. -->
            <div class="mx-auto mt-10 max-w-md lg:hidden">
                <div class="flex flex-col items-center" data-reveal>
                    <span class="flex h-16 w-16 items-center justify-center rounded-full bg-green-600 text-xl font-extrabold text-white shadow-lg shadow-green-900/20">S</span>
                    <span class="mt-2 text-sm font-extrabold text-gray-950">SchoolOS</span>
                </div>
                <div class="mt-6 grid grid-cols-2 gap-3 sm:grid-cols-3">
                    @foreach ($hubRoles as $i => [$label, $x, $y, $icon])
                        <div class="rounded-xl border border-gray-200 bg-white p-3 text-center shadow-sm" data-reveal style="--reveal-delay: {{ min($i * 90, 360) }}ms">
                            <span class="mx-auto flex h-9 w-9 items-center justify-center rounded-lg bg-green-50 text-green-700">
                                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="{{ $icon }}" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
                            </span>
                            <p class="mt-2 text-xs font-bold text-gray-950">{{ $label }}</p>
                            <span class="mt-1.5 inline-flex items-center gap-1 text-[10px] font-semibold uppercase tracking-wide text-green-600">
                                <span class="h-1.5 w-1.5 animate-pulse rounded-full bg-green-500"></span>
                                Live
                            </span>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </section>

    <section class="bg-slate-50 py-20">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="max-w-3xl">
                <h2 class="text-3xl font-bold tracking-tight text-gray-950 sm:text-4xl" data-reveal>Less paperwork. Less confusion. More time for education.</h2>
            </div>
            <div class="mt-10 grid gap-4 lg:grid-cols-4">
                @foreach ([
                    ['Before SchoolOS', 'Scattered records', 'Student information lives across spreadsheets, notebooks, files, and disconnected systems.'],
                    ['Before SchoolOS', 'Manual processes', 'Attendance, fee tracking, reports, communication, and administration consume valuable staff time.'],
                    ['Before SchoolOS', 'Disconnected communication', "Teachers, administrators, students, and parents don't always have one reliable place to stay connected."],
                    ['With SchoolOS', 'One connected school platform.', 'SchoolOS brings school records, roles, workflows, and communication into one calm operating system.'],
                ] as [$label, $title, $copy])
                    <article class="rounded-xl border {{ $label === 'With SchoolOS' ? 'border-green-200 bg-green-50' : 'border-gray-200 bg-white' }} p-6 shadow-sm transition duration-300 hover:-translate-y-1 hover:shadow-lg" data-reveal style="--reveal-delay: {{ $loop->index * 100 }}ms">
                        <p class="text-xs font-bold uppercase tracking-[0.12em] {{ $label === 'With SchoolOS' ? 'text-green-700' : 'text-gray-400' }}">{{ $label }}</p>
                        <h3 class="mt-4 text-lg font-bold text-gray-950">{{ $title }}</h3>
                        <p class="mt-3 text-sm leading-6 text-gray-600">{{ $copy }}</p>
                    </article>
                @endforeach
            </div>
        </div>
    </section>

    <section id="features" class="relative overflow-hidden bg-white py-20" data-glow-section>
        <div class="so-glow" data-cursor-glow aria-hidden="true"></div>
        <div class="relative mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="max-w-3xl">
                <h2 class="text-3xl font-bold tracking-tight text-gray-950 sm:text-4xl" data-reveal>Everything your school needs to run smoothly.</h2>
            </div>
            <div class="mt-10 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                @foreach ($features as [$title, $copy, $icon])
                    <article class="group rounded-xl border border-gray-200 bg-white p-5 shadow-sm transition duration-300 hover:-translate-y-1 hover:border-green-200 hover:shadow-lg" data-reveal data-cursor-light style="--reveal-delay: {{ min($loop->index * 90, 450) }}ms">
                        <span class="flex h-11 w-11 items-center justify-center rounded-lg bg-green-50 text-green-700 transition-transform duration-300 group-hover:-translate-y-0.5">
                            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="{{ $icon }}" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
                        </span>
                        <h3 class="mt-5 text-base font-bold text-gray-950">{{ $title }}</h3>
                        <p class="mt-3 text-sm leading-6 text-gray-600">{{ $copy }}</p>
                    </article>
                @endforeach
            </div>
        </div>
    </section>

    <section id="roles" class="bg-slate-50 py-20">
        <div class="mx-auto grid max-w-7xl gap-8 px-4 sm:px-6 lg:grid-cols-[0.85fr_1.15fr] lg:px-8">
            <div data-reveal>
                <h2 class="text-3xl font-bold tracking-tight text-gray-950 sm:text-4xl">One platform. Every role.</h2>
                <div class="mt-8 flex flex-wrap gap-2" role="tablist" aria-label="SchoolOS roles">
                    @foreach ($roles as $role => $content)
                        <button type="button" class="rounded-full border border-gray-200 bg-white px-4 py-2 text-sm font-bold text-gray-700 transition duration-200 hover:-translate-y-0.5 data-[active=true]:border-green-600 data-[active=true]:bg-green-600 data-[active=true]:text-white" data-role-tab="{{ $role }}" data-active="{{ $loop->first ? 'true' : 'false' }}">{{ $role }}</button>
                    @endforeach
                </div>
            </div>
            <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-xl shadow-green-900/5" data-reveal style="--reveal-delay:120ms">
                @foreach ($roles as $role => [$headline, $items])
                    <div class="{{ $loop->first ? '' : 'hidden' }}" data-role-panel="{{ $role }}">
                        <p class="text-sm font-bold text-green-700">{{ $role }}</p>
                        <h3 class="mt-2 text-2xl font-bold text-gray-950">{{ $headline }}</h3>
                        <div class="mt-6 grid gap-3 sm:grid-cols-2">
                            @foreach ($items as $item)
                                <div class="rounded-lg border border-gray-200 bg-slate-50 px-4 py-3 text-sm font-semibold text-gray-700">{{ $item }}</div>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    <section class="bg-white py-20">
        <div class="mx-auto grid max-w-7xl gap-10 px-4 sm:px-6 lg:grid-cols-2 lg:px-8">
            <div class="self-center" data-reveal>
                <h2 class="text-3xl font-bold tracking-tight text-gray-950 sm:text-4xl">Attendance without the paperwork.</h2>
                <p class="mt-4 text-lg leading-8 text-gray-600">SchoolOS keeps attendance organized, accessible, and connected to the rest of the school.</p>
            </div>
            <div class="rounded-2xl border border-gray-200 bg-slate-50 p-4 shadow-xl shadow-green-900/5" data-reveal style="--reveal-delay:120ms">
                <div class="rounded-xl bg-white p-5 shadow-sm">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <p class="text-sm font-bold text-gray-950">Primary 5 - Attendance</p>
                            <p class="text-sm text-gray-500">Today, {{ now()->format('F j') }} · Mrs. Adeyemi</p>
                        </div>
                        <span class="rounded-full bg-green-100 px-3 py-1 text-sm font-bold text-green-700">96% present</span>
                    </div>
                    <div class="mt-5 space-y-3">
                        @foreach (['Daniel Johnson', 'Mariam Bello', 'Chinedu Okafor', 'Aisha Musa'] as $index => $student)
                            <div class="flex items-center justify-between rounded-lg border border-gray-100 px-3 py-3">
                                <span class="text-sm font-semibold text-gray-800">{{ $student }}</span>
                                <span class="rounded-md {{ $index === 2 ? 'bg-red-50 text-red-700' : 'bg-green-50 text-green-700' }} px-3 py-1 text-xs font-bold">{{ $index === 2 ? 'Absent' : 'Present' }}</span>
                            </div>
                        @endforeach
                    </div>
                    <div class="mt-4 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm font-bold text-green-800" data-attendance-toast>Attendance recorded successfully.</div>
                </div>
            </div>
        </div>
    </section>

    <section class="bg-slate-50 py-20">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="max-w-3xl" data-reveal>
                <h2 class="text-3xl font-bold tracking-tight text-gray-950 sm:text-4xl">Make school fees easier to manage.</h2>
                <p class="mt-4 text-lg text-gray-600">Track what is owed, what has been paid, and everything in between.</p>
            </div>
            <div class="mt-10 grid gap-5 lg:grid-cols-[0.9fr_1.1fr]">
                <div class="grid gap-3 sm:grid-cols-3 lg:grid-cols-1">
                    @foreach ([['Total fees', '₦24,500,000'], ['Collected', '₦18,750,000'], ['Outstanding', '₦5,750,000']] as [$label, $value])
                        <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm transition duration-300 hover:-translate-y-1 hover:shadow-lg" data-reveal style="--reveal-delay: {{ $loop->index * 100 }}ms">
                            <p class="text-sm font-semibold text-gray-500">{{ $label }}</p>
                            <p class="mt-2 text-2xl font-bold text-gray-950">{{ $value }}</p>
                        </div>
                    @endforeach
                </div>
                <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-xl shadow-green-900/5" data-reveal style="--reveal-delay:180ms">
                    <div class="flex flex-wrap gap-2" role="tablist" aria-label="Payment examples">
                        @foreach (['Full payment', 'Part payment', 'Custom payment'] as $tab)
                            <button type="button" class="rounded-full border border-gray-200 px-4 py-2 text-sm font-bold data-[active=true]:border-green-600 data-[active=true]:bg-green-600 data-[active=true]:text-white" data-payment-tab="{{ $tab }}" data-active="{{ $loop->first ? 'true' : 'false' }}">{{ $tab }}</button>
                        @endforeach
                    </div>
                    <div class="mt-6">
                        <div data-payment-panel="Full payment">
                            <p class="text-sm font-bold text-green-700">01 - Full Payment</p>
                            <h3 class="mt-2 text-2xl font-bold">Pay in full</h3>
                            <p class="mt-2 text-gray-600">For families who want to settle the entire school fee at once.</p>
                            <div class="mt-5 rounded-xl bg-green-50 p-5">
                                <p class="text-3xl font-bold">₦250,000</p>
                                <p class="mt-3 inline-flex rounded-full bg-green-600 px-3 py-1 text-sm font-bold text-white">Fully paid</p>
                            </div>
                        </div>
                        <div class="hidden" data-payment-panel="Part payment">
                            <p class="text-sm font-bold text-green-700">02 - Part Payment</p>
                            <h3 class="mt-2 text-2xl font-bold">Pay in parts</h3>
                            <div class="mt-5 rounded-xl bg-slate-50 p-5">
                                <div class="grid gap-3 sm:grid-cols-3">
                                    <p><span class="block text-xs text-gray-500">Total</span><strong>₦250,000</strong></p>
                                    <p><span class="block text-xs text-gray-500">Paid</span><strong>₦150,000</strong></p>
                                    <p><span class="block text-xs text-gray-500">Remaining</span><strong>₦100,000</strong></p>
                                </div>
                                <div class="mt-4 h-3 rounded-full bg-gray-200"><div class="h-3 w-[60%] rounded-full bg-green-600"></div></div>
                                <p class="mt-3 text-sm font-bold text-amber-700">Partially paid</p>
                                <div class="mt-4 grid gap-2 text-sm text-gray-600">
                                    <span>Payment 1 - ₦100,000</span>
                                    <span>Payment 2 - ₦50,000</span>
                                </div>
                            </div>
                        </div>
                        <div class="hidden" data-payment-panel="Custom payment">
                            <p class="text-sm font-bold text-green-700">03 - Custom Payment</p>
                            <h3 class="mt-2 text-2xl font-bold">Custom payment</h3>
                            <div class="mt-5 rounded-xl bg-slate-50 p-5">
                                <p class="text-sm text-gray-500">School Trip</p>
                                <p class="mt-1 text-3xl font-bold">₦35,000</p>
                                <p class="mt-3 text-sm text-gray-600">Due: October 18</p>
                                <p class="mt-3 inline-flex rounded-full bg-amber-100 px-3 py-1 text-sm font-bold text-amber-800">Pending</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section id="dashboard" class="relative overflow-hidden bg-white py-20" data-glow-section>
        <div class="so-glow" data-cursor-glow aria-hidden="true"></div>
        <div class="relative mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <h2 class="text-3xl font-bold tracking-tight text-gray-950 sm:text-4xl" data-reveal>See your school clearly.</h2>
            <div class="mt-10 grid gap-4 rounded-2xl border border-gray-200 bg-slate-950 p-4 text-white shadow-2xl shadow-green-900/10 sm:grid-cols-2 lg:grid-cols-5" data-reveal style="--reveal-delay:120ms">
                @foreach (['Dashboard', 'Students', 'Attendance', 'Fees', 'Academics', 'Notifications'] as $panel)
                    <div class="rounded-xl border border-white/10 bg-white/10 p-5 transition duration-300 hover:-translate-y-1 hover:bg-white/15">
                        <p class="text-sm font-bold">{{ $panel }}</p>
                        <div class="mt-5 h-24 rounded-lg bg-white/10"></div>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    <section class="bg-slate-50 py-20">
        <div class="mx-auto grid max-w-7xl gap-10 px-4 sm:px-6 lg:grid-cols-[0.8fr_1.2fr] lg:px-8">
            <div data-reveal>
                <h2 class="text-3xl font-bold tracking-tight text-gray-950 sm:text-4xl">Built for mobile school life.</h2>
                <p class="mt-4 text-lg text-gray-600">Parents, students, teachers, and staff can reach the information that matters from focused portal views.</p>
            </div>
            <div class="flex gap-4 overflow-x-auto pb-4">
                @foreach ([
                    ['Parent dashboard', 'Daniel Johnson', 'Attendance 96%', '₦75,000 outstanding'],
                    ['Latest result', 'Mathematics - A', 'Upcoming', 'Mathematics - 10:00 AM'],
                    ['Announcements', 'Sports day reminder', 'Fees', 'Receipt confirmed'],
                ] as $phone)
                    <div class="min-w-[230px] rounded-[2rem] border border-gray-200 bg-white p-3 shadow-xl transition duration-300 hover:-translate-y-1 hover:shadow-2xl" data-reveal style="--reveal-delay: {{ $loop->index * 100 }}ms">
                        <div class="rounded-[1.5rem] bg-slate-50 p-4">
                            @foreach ($phone as $line)
                                <div class="mb-3 rounded-lg bg-white px-3 py-3 text-sm font-semibold text-gray-700 shadow-sm">{{ $line }}</div>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    <section class="bg-white py-20">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <h2 class="text-3xl font-bold tracking-tight text-gray-950 sm:text-4xl" data-reveal>From setup to school management in minutes.</h2>
            <div class="relative mt-10 grid gap-4 lg:grid-cols-3">
                <div class="absolute left-0 right-0 top-8 hidden h-px bg-gray-200 lg:block"></div>
                @foreach ([['01', 'Set up your school', 'Configure your school, academic structure, classes, users, and modules.'], ['02', 'Bring your school together', 'Invite teachers, staff, parents, and students.'], ['03', 'Run everything from SchoolOS', 'Manage academics, attendance, payments, communication, and daily operations from one place.']] as [$num, $title, $copy])
                    <article class="relative rounded-xl border border-gray-200 bg-white p-6 shadow-sm transition duration-300 hover:-translate-y-1 hover:shadow-lg" data-reveal style="--reveal-delay: {{ $loop->index * 120 }}ms">
                        <span class="flex h-16 w-16 items-center justify-center rounded-full bg-green-600 text-lg font-bold text-white">{{ $num }}</span>
                        <h3 class="mt-6 text-xl font-bold">{{ $title }}</h3>
                        <p class="mt-3 text-sm leading-6 text-gray-600">{{ $copy }}</p>
                    </article>
                @endforeach
            </div>
        </div>
    </section>

    <section id="pricing" class="relative overflow-hidden bg-slate-50 py-20" data-glow-section>
        <div class="so-glow" data-cursor-glow aria-hidden="true"></div>
        <div class="relative mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="max-w-3xl" data-reveal>
                <h2 class="text-3xl font-bold tracking-tight text-gray-950 sm:text-4xl">Simple pricing that grows with your school.</h2>
                <p class="mt-4 text-lg text-gray-600">Start with what you need. Add more when your school grows.</p>
            </div>
            <div class="mt-10 grid gap-5 lg:grid-cols-3">
                @foreach ([
                    ['Starter', 'For smaller schools getting started.', ['Student management', 'Basic attendance', 'Classes & subjects', 'Parent access', 'Basic reports'], 'Get started', false],
                    ['Professional', 'For schools ready to connect the full operation.', ['Everything in Starter', 'Advanced attendance', 'Fee management', 'Part-payment tracking', 'Report cards', 'Teacher portal', 'Parent portal', 'Communication', 'Advanced reports'], 'Start with Professional', true],
                    ['Enterprise', 'For larger or multi-campus schools.', ['Everything in Professional', 'Multiple campuses', 'Advanced administration', 'Custom workflows', 'Priority support', 'Custom integrations'], 'Talk to us', false],
                ] as [$name, $copy, $items, $cta, $popular])
                    <article class="{{ $popular ? '' : 'transition duration-300 hover:-translate-y-1.5 hover:shadow-xl' }} rounded-2xl border {{ $popular ? 'border-green-600 bg-white shadow-2xl shadow-green-900/10' : 'border-gray-200 bg-white shadow-sm' }} p-6" data-reveal data-cursor-light @if($popular) data-tilt @endif style="--reveal-delay: {{ $loop->index * 110 }}ms">
                        @if ($popular)
                            <span class="rounded-full bg-green-100 px-3 py-1 text-xs font-bold text-green-700">MOST POPULAR</span>
                        @endif
                        <h3 class="mt-4 text-2xl font-bold">{{ $name }}</h3>
                        <p class="mt-2 text-sm text-gray-600">{{ $copy }}</p>
                        <p class="mt-6 text-3xl font-bold">Contact us</p>
                        <ul class="mt-6 space-y-3">
                            @foreach ($items as $item)
                                <li class="flex gap-2 text-sm text-gray-700"><span class="text-green-600">✓</span>{{ $item }}</li>
                            @endforeach
                        </ul>
                        <a href="{{ route('public.onboarding.create') }}" class="mt-7 inline-flex min-h-12 w-full items-center justify-center rounded-md {{ $popular ? 'bg-green-600 text-white hover:bg-green-700' : 'border border-gray-200 text-gray-800 hover:bg-green-50' }} px-5 text-sm font-bold transition duration-200 hover:-translate-y-0.5 active:translate-y-0 active:scale-[0.98]">{{ $cta }}</a>
                    </article>
                @endforeach
            </div>
        </div>
    </section>

    <section class="bg-white py-20">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <h2 class="text-3xl font-bold tracking-tight text-gray-950 sm:text-4xl" data-reveal>What schools can expect from SchoolOS.</h2>
            <div class="mt-10 grid gap-4 lg:grid-cols-3">
                @foreach ([['“It brings the work into one place, from attendance to fees.”', 'Placeholder Admin', 'School Administrator', 'Private School'], ['“Teachers get a clearer daily workspace without extra complexity.”', 'Placeholder Teacher', 'Teacher', 'Academy'], ['“Parents can follow the information that matters most.”', 'Placeholder Parent', 'Parent Representative', 'Primary School']] as [$quote, $name, $role, $school])
                    <article class="rounded-xl border border-gray-200 bg-slate-50 p-6 transition duration-300 hover:-translate-y-1 hover:shadow-lg" data-reveal style="--reveal-delay: {{ $loop->index * 100 }}ms">
                        <p class="text-base leading-7 text-gray-700">{{ $quote }}</p>
                        <p class="mt-5 font-bold text-gray-950">{{ $name }}</p>
                        <p class="text-sm text-gray-500">{{ $role }} · {{ $school }}</p>
                    </article>
                @endforeach
            </div>
        </div>
    </section>

    <section id="faq" class="bg-slate-50 py-20">
        <div class="mx-auto max-w-4xl px-4 sm:px-6 lg:px-8">
            <h2 class="text-3xl font-bold tracking-tight text-gray-950 sm:text-4xl" data-reveal>Questions schools ask.</h2>
            <div class="mt-8 divide-y divide-gray-200 rounded-2xl border border-gray-200 bg-white" data-reveal style="--reveal-delay:100ms">
                @foreach ($faqs as $question => $answer)
                    <details class="group p-5">
                        <summary class="flex cursor-pointer list-none items-center justify-between gap-4 text-base font-bold text-gray-950">
                            {{ $question }}
                            <span class="text-green-700 transition group-open:rotate-45">+</span>
                        </summary>
                        <p class="mt-3 text-sm leading-6 text-gray-600">{{ $answer }}</p>
                    </details>
                @endforeach
            </div>
        </div>
    </section>

    <section class="relative overflow-hidden bg-white py-20" data-glow-section>
        <div class="so-glow" data-cursor-glow aria-hidden="true"></div>
        <div class="relative mx-auto grid max-w-7xl items-center gap-8 px-4 sm:px-6 lg:grid-cols-[0.9fr_1.1fr] lg:px-8">
            <div>
                <h2 class="text-4xl font-bold tracking-tight text-gray-950" data-reveal>Your school deserves simpler software.</h2>
                <p class="mt-4 text-lg text-gray-600" data-reveal style="--reveal-delay:100ms">Bring your school's everyday operations together with SchoolOS.</p>
                <div class="mt-8 flex flex-col gap-3 sm:flex-row" data-reveal style="--reveal-delay:200ms">
                    <a href="{{ route('public.onboarding.create') }}" class="inline-flex min-h-12 items-center justify-center rounded-md bg-green-600 px-6 text-sm font-bold text-white shadow-sm transition duration-200 hover:-translate-y-0.5 hover:bg-green-700 hover:shadow-md active:translate-y-0 active:scale-[0.98]">Get started</a>
                    <a href="{{ route('marketing.features.index') }}" class="inline-flex min-h-12 items-center justify-center rounded-md border border-gray-200 px-6 text-sm font-bold text-gray-800 shadow-sm transition duration-200 hover:-translate-y-0.5 hover:bg-green-50 active:translate-y-0 active:scale-[0.98]">Explore SchoolOS</a>
                </div>
            </div>
            <div class="rounded-2xl border border-green-100 bg-green-50 p-5" data-reveal style="--reveal-delay:150ms">
                <div class="rounded-xl bg-white p-5 shadow-sm">
                    <p class="text-sm font-bold text-green-700">One school. One connected system.</p>
                    <div class="mt-5 grid gap-3 sm:grid-cols-2">
                        @foreach (['Students', 'Teachers', 'Parents', 'Staff', 'Academics', 'Fees'] as $item)
                            <div class="rounded-lg border border-gray-200 px-4 py-3 text-sm font-semibold transition duration-200 hover:-translate-y-0.5 hover:border-green-200 hover:bg-green-50/50">{{ $item }}</div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    </section>
</main>

<x-public-footer />
</body>
</html>
