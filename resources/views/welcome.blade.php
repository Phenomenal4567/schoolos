<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
    <meta charset="utf-8">
    <title>SchoolOS - Everything your school needs to run better</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="SchoolOS brings student records, staff management, attendance, results, fees, payments, examinations, and everyday school operations into one connected platform.">
    <x-theme-script />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="schoolos-public bg-white text-[#111827] antialiased">
@php
    $features = [
        [
            'Student Records & Admissions',
            'Centralize student biodata, enrollment history, admission numbers, class arms, and parent/guardian relationships in a single secure system.',
            'M16 21v-2a4 4 0 0 0-8 0v2M12 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8Z',
            'Admissions & Rosters',
        ],
        [
            'Daily Attendance Tracking',
            'Record class attendance in seconds with support for morning and afternoon registers, real-time absence tracking, and term percentage summaries.',
            'M9 12l2 2 4-5M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z',
            'Roll Call & Stats',
        ],
        [
            'Academics & Grading',
            'Manage academic sessions, class subjects, continuous assessments (CA1, CA2), exam marks, and standardized grading scales across all arms.',
            'M4 19.5V6a2 2 0 0 1 2-2h13v15.5M8 8h8M8 12h8M8 16h5',
            'Continuous Assessment',
        ],
        [
            'Fees, Payments & Debt Tracking',
            'Set up fee structures by class level, bill students automatically, accept full or part-payments, and monitor live outstanding balances.',
            'M4 7h16M4 11h16M7 15h4M6 19h12a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2H6a2 2 0 0 0-2 2v10a2 2 0 0 0 2 2Z',
            'Billing & Balances',
        ],
        [
            'Paystack Integration & Receipts',
            'Accept secure card and bank transfer payments with server-side HMAC verification and instant, tamper-proof private PDF receipts.',
            'M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z',
            'Verified Settlements',
        ],
        [
            'Examinations & Terminal Reports',
            'Compute final grades, subject positions, term averages, and teacher remarks automatically, generating clean, professional report cards.',
            'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4',
            'Report Cards & Rank',
        ],
        [
            'Timetables & Class Schedules',
            'Organize class arms, assign subject teachers to specific sections, and manage weekly period allocations without double-booking classrooms.',
            'M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z',
            'Schedules & Periods',
        ],
        [
            'Targeted Announcements & Alerts',
            'Broadcast official school circulars to specific classes, teachers, or all parents, complete with delivery logs and unread indicators.',
            'M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9',
            'Verified Delivery',
        ],
    ];

    $roles = [
        'School Owner' => [
            'Complete school oversight without micromanaging.',
            'Stay in command of institutional performance, financial health, and enrollment velocity from a high-level dashboard.',
            [
                'Real-time tuition collection rates and pending fee balances',
                'Enrollment growth tracking across nursery, primary, and secondary',
                'Staff attendance records and academic progress oversight',
                'Immutable audit logs tracking sensitive record changes',
            ],
            'Executive Oversight',
        ],
        'School Admin' => [
            'Run everyday school operations with calm confidence.',
            'Manage student admissions, allocate teachers to class arms, schedule terms, and oversee promotion workflows seamlessly.',
            [
                'Fast student enrollment and guardian linking',
                'Class arms, sections, and teacher allocations',
                'End-of-term promotion evaluation and criteria rules',
                'School-wide announcements with delivery confirmation',
            ],
            'Operational Hub',
        ],
        'Teacher' => [
            'A distraction-free workspace built for the classroom.',
            'Take roll calls in seconds, input continuous assessment scores, calculate exam results, and access class rosters effortlessly.',
            [
                'Rapid daily morning and afternoon roll call',
                'CA1, CA2, and terminal examination score entry',
                'Class and subject rosters with student biodata',
                'Weekly timetable view and period allocations',
            ],
            'Classroom Workspace',
        ],
        'Bursar' => [
            'Eliminate payment disputes and manual reconciliations.',
            'Manage fee structures, issue invoices, monitor part-payments, and verify Paystack transactions with server-side certainty.',
            [
                'Automated student fee assessments and discount waivers',
                'Real-time part-payment tracking with automated balances',
                'Instant Paystack webhook transaction verification',
                'Downloadable authenticated PDF payment receipts',
            ],
            'Financial Control',
        ],
        'Parent' => [
            'Stay connected to your child’s educational journey.',
            'Access verified attendance records, view terminal report cards, track outstanding fees, and make secure online payments.',
            [
                'Live attendance notifications and absence alerts',
                'Downloadable term report cards and grade breakdowns',
                'Clear fee invoices with transparent payment history',
                'Direct online fee settlement via cards or bank transfer',
            ],
            'Parental Visibility',
        ],
    ];

    // [role, x%, y%, icon path, task] - positions plot a pentagon around the central SchoolOS node.
    $hubRoles = [
        ['School Admin', 12, 39, 'M4 21V9a1 1 0 0 1 1-1h4V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v4h4a1 1 0 0 1 1 1v12M4 21h16M9 21v-4h6v4M9 12h.01M15 12h.01M9 16h.01M15 16h.01', 'Admissions & Staff'],
        ['Teacher', 88, 39, 'M12 14l9-5-9-5-9 5 9 5ZM12 14v7M5 12v5c2 2 12 2 14 0v-5', 'Grading & Roll Call'],
        ['Parent', 50, 12, 'M17 21v-2a4 4 0 0 0-4-4H7a4 4 0 0 0-4 4v2M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8ZM23 21v-2a4 4 0 0 0-3-3.87', 'Reports & Payments'],
        ['Student', 73.5, 78, 'M16 21v-2a4 4 0 0 0-8 0v2M12 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8Z', 'Timetable & Results'],
        ['Bursar', 26.5, 78, 'M5 8h14M5 8a2 2 0 0 0-2 2v9a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-9a2 2 0 0 0-2-2M5 8V6a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2v2M9 13a2 2 0 1 0 4 0 2 2 0 0 0-4 0ZM8 18c.5-1.5 1.8-2 4-2s3.5.5 4 2', 'Fees & Verification'],
    ];

    $faqs = [
        'What is SchoolOS?' => 'SchoolOS is a production-grade cloud school operating system designed to bring student records, staff management, attendance, grading, fee collections, online payments, report cards, and communication into one synchronized platform.',
        'Which school levels and curricula does SchoolOS support?' => 'SchoolOS supports Early Years (Creche, Nursery, Kindergarten), Primary, Junior Secondary (JSS), and Senior Secondary (SSS) schools, with configurable class arms, terms, and custom grading schemes.',
        'How does SchoolOS handle school fees and partial payments?' => 'Schools define term fee structures by class level or category. Students are assessed automatically. SchoolOS natively tracks full settlements, installment part-payments, outstanding balances, and generates authenticated receipts.',
        'How does the Paystack payment integration work?' => 'SchoolOS integrates Paystack directly for card and bank transfer payments. Transactions are verified cryptographically server-side using HMAC SHA-512 webhook signatures, ensuring no payment can ever be spoofed or double-credited.',
        'Can parents view terminal report cards and track academic progress?' => 'Yes. Parents receive dedicated portal access linked securely to their registered children, allowing them to view published report cards, daily attendance rates, fee balances, and school announcements.',
        'Is student and financial data secure?' => 'Yes. SchoolOS is built on a multi-tenant architecture with strict tenant data isolation, granular role-based access control (RBAC), immutable audit logging for sensitive actions, and private Supabase storage for receipts.',
        'Do we need to buy local servers or install software on school computers?' => 'No. SchoolOS runs entirely in the cloud. Administrators, teachers, bursars, and parents can access it from any modern web browser on laptops, desktops, tablets, or smartphones without local installation.',
        'Can teachers only view their assigned classes and subjects?' => 'Yes. Role-based permissions enforce strict boundaries. Subject teachers and class teachers only have access to marks entry, attendance, and rosters for the specific sections they are officially assigned to.',
        'How does continuous assessment (CA) and exam calculation work?' => 'Administrators configure term assessment weights (e.g., CA1 20%, CA2 20%, Exam 60%). Teachers enter raw scores, and SchoolOS automatically tabulates totals, percentages, letter grades, class positions, and term averages.',
        'How does onboarding work and how quickly can our school go live?' => 'Register your school in 2 minutes, configure your classes and subjects using our setup wizard, upload or enter your student and staff rosters, and begin running your school operations immediately.',
    ];
@endphp

<x-public-navbar />

<main>
    <!-- HERO SECTION -->
    <section class="schoolos-hero relative overflow-hidden bg-[radial-gradient(circle_at_top_right,#DCFCE7,transparent_34%),linear-gradient(180deg,#F0FDF4_0%,#FFFFFF_70%)]" data-glow-section>
        <div class="so-glow" data-cursor-glow aria-hidden="true"></div>
        <div class="relative mx-auto grid max-w-7xl gap-12 px-4 pb-16 pt-12 sm:px-6 lg:grid-cols-[1fr_1.05fr] lg:px-8 lg:pb-24 lg:pt-20">
            <div class="max-w-2xl">
                <div class="inline-flex items-center gap-2 rounded-full border border-green-200 bg-green-50/80 px-3.5 py-1 text-xs font-bold uppercase tracking-[0.14em] text-green-800 backdrop-blur-sm" data-hero-in style="--hero-delay:0ms">
                    <span class="h-2 w-2 rounded-full bg-green-600 animate-pulse"></span>
                    The Modern School Operating System
                </div>
                <h1 class="mt-5 text-4xl font-extrabold leading-[1.12] tracking-tight text-gray-950 sm:text-5xl lg:text-[3.5rem]" data-hero-in style="--hero-delay:90ms">
                    Everything your school needs to run better.
                </h1>
                <p class="mt-6 text-lg leading-relaxed text-gray-600 sm:text-xl" data-hero-in style="--hero-delay:180ms">
                    SchoolOS brings student records, staff management, attendance, results, fees, payments, examinations, and everyday school operations into one connected platform.
                </p>
                <div class="mt-8 flex flex-col gap-3 sm:flex-row" data-hero-in style="--hero-delay:270ms">
                    <a href="{{ route('public.onboarding.create') }}" class="inline-flex min-h-12 items-center justify-center rounded-lg bg-green-600 px-7 text-base font-bold text-white shadow-md shadow-green-600/20 transition duration-200 hover:-translate-y-0.5 hover:bg-green-700 hover:shadow-lg active:translate-y-0 active:scale-[0.98]">
                        Get Started
                    </a>
                    <a href="{{ route('login') }}" class="inline-flex min-h-12 items-center justify-center rounded-lg border border-gray-200 bg-white px-7 text-base font-bold text-gray-800 shadow-sm transition duration-200 hover:-translate-y-0.5 hover:border-green-300 hover:bg-green-50/40 active:translate-y-0 active:scale-[0.98]">
                        Log In
                    </a>
                </div>
                <div class="mt-8 flex flex-wrap items-center gap-x-6 gap-y-2 text-xs font-semibold text-gray-500" data-hero-in style="--hero-delay:330ms">
                    <span class="flex items-center gap-1.5">
                        <svg class="h-4 w-4 text-green-600" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                        Multi-tenant architecture
                    </span>
                    <span class="flex items-center gap-1.5">
                        <svg class="h-4 w-4 text-green-600" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                        Role-based security
                    </span>
                    <span class="flex items-center gap-1.5">
                        <svg class="h-4 w-4 text-green-600" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                        Paystack-verified fees
                    </span>
                </div>
            </div>

            <!-- Realistic Product Interface Preview Card -->
            <div class="relative min-h-[500px] lg:min-h-[580px]" data-parallax>
                <div class="schoolos-dashboard-dark absolute inset-x-0 top-4 mx-auto w-full max-w-2xl rounded-2xl border border-gray-200/90 bg-white p-3.5 shadow-2xl shadow-green-950/10" data-hero-in="scale" data-parallax-target style="--hero-delay:360ms">
                    <!-- Browser / Window Chrome -->
                    <div class="flex items-center justify-between border-b border-gray-100 pb-3 px-1.5">
                        <div class="flex items-center gap-1.5">
                            <span class="h-3 w-3 rounded-full bg-red-400/80"></span>
                            <span class="h-3 w-3 rounded-full bg-amber-400/80"></span>
                            <span class="h-3 w-3 rounded-full bg-green-400/80"></span>
                            <span class="ml-2 text-xs font-medium text-gray-400">schoolos.app › greenwood-academy › session-2026-2027</span>
                        </div>
                        <span class="inline-flex items-center gap-1.5 rounded-full bg-green-100 px-2.5 py-0.5 text-[11px] font-bold text-green-800">
                            <span class="h-1.5 w-1.5 rounded-full bg-green-600 animate-pulse"></span>
                            Live Term 1
                        </span>
                    </div>

                    <!-- Inner App Interface Canvas -->
                    <div class="mt-3 rounded-xl border border-gray-100 bg-slate-50/70 p-4">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <div>
                                <p class="text-xs font-bold uppercase tracking-wider text-green-700">School Admin Portal</p>
                                <h2 class="text-lg font-extrabold text-gray-950">Greenwood International School</h2>
                            </div>
                            <span class="text-xs font-medium text-gray-500">2026/2027 Academic Session</span>
                        </div>

                        <!-- 3 Metric Cards -->
                        <div class="mt-4 grid grid-cols-3 gap-2.5">
                            <div class="rounded-lg border border-gray-200/80 bg-white p-3 shadow-xs">
                                <p class="text-[11px] font-semibold text-gray-500">Active Students</p>
                                <p class="mt-1 text-xl font-extrabold text-gray-950" data-count-up="1280">1,280</p>
                                <span class="text-[10px] font-semibold text-green-700">+42 enrolled</span>
                            </div>
                            <div class="rounded-lg border border-gray-200/80 bg-white p-3 shadow-xs">
                                <p class="text-[11px] font-semibold text-gray-500">Today's Attendance</p>
                                <p class="mt-1 text-xl font-extrabold text-green-700">94.6%</p>
                                <span class="text-[10px] font-semibold text-gray-500">1,211 present</span>
                            </div>
                            <div class="rounded-lg border border-gray-200/80 bg-white p-3 shadow-xs">
                                <p class="text-[11px] font-semibold text-gray-500">Fees Collected</p>
                                <p class="mt-1 text-xl font-extrabold text-gray-950">₦8.42M</p>
                                <span class="text-[10px] font-semibold text-green-700">91.5% target</span>
                            </div>
                        </div>

                        <!-- Split Content: Attendance Trend & Real Operational Stream -->
                        <div class="mt-3.5 grid gap-3 lg:grid-cols-[1.1fr_0.9fr]">
                            <div class="rounded-lg border border-gray-200/80 bg-white p-3 shadow-xs">
                                <div class="flex items-center justify-between border-b border-gray-100 pb-2">
                                    <p class="text-xs font-bold text-gray-900">Attendance by Level</p>
                                    <span class="text-[10px] font-medium text-gray-400">Weekly average</span>
                                </div>
                                <div class="mt-3 flex h-28 items-end gap-2.5">
                                    @foreach ([
                                        ['Creche', 92],
                                        ['Nursery', 96],
                                        ['Pry 1-3', 94],
                                        ['Pry 4-6', 98],
                                        ['JSS', 93],
                                        ['SSS', 95]
                                    ] as [$class, $pct])
                                        <div class="flex flex-1 flex-col items-center gap-1">
                                            <span class="text-[9px] font-bold text-gray-600">{{ $pct }}%</span>
                                            <div class="w-full rounded-t-md bg-green-500/80 transition-all duration-300 hover:bg-green-600" style="height: {{ $pct * 0.9 }}px"></div>
                                            <span class="text-[9px] font-medium text-gray-500 truncate w-full text-center">{{ $class }}</span>
                                        </div>
                                    @endforeach
                                </div>
                            </div>

                            <div class="rounded-lg border border-gray-200/80 bg-white p-3 shadow-xs">
                                <div class="flex items-center justify-between border-b border-gray-100 pb-2">
                                    <p class="text-xs font-bold text-gray-900">Live System Activity</p>
                                    <span class="h-1.5 w-1.5 rounded-full bg-green-500 animate-ping"></span>
                                </div>
                                <div class="mt-2.5 space-y-2 text-[11px]">
                                    <div class="flex items-start gap-2 rounded border border-gray-100 bg-slate-50/60 p-2">
                                        <span class="rounded bg-green-100 px-1 py-0.5 text-[9px] font-bold text-green-800">Fee</span>
                                        <div class="leading-tight">
                                            <p class="font-semibold text-gray-900">₦85,000 paid for Daniel Okafor</p>
                                            <p class="text-[10px] text-gray-500">Verified via Paystack</p>
                                        </div>
                                    </div>
                                    <div class="flex items-start gap-2 rounded border border-gray-100 bg-slate-50/60 p-2">
                                        <span class="rounded bg-blue-100 px-1 py-0.5 text-[9px] font-bold text-blue-800">Grades</span>
                                        <div class="leading-tight">
                                            <p class="font-semibold text-gray-900">CA2 scores submitted: JSS 2 Maths</p>
                                            <p class="text-[10px] text-gray-500">By Mrs. Adeyemi</p>
                                        </div>
                                    </div>
                                    <div class="flex items-start gap-2 rounded border border-gray-100 bg-slate-50/60 p-2">
                                        <span class="rounded bg-amber-100 px-1 py-0.5 text-[9px] font-bold text-amber-800">Register</span>
                                        <div class="leading-tight">
                                            <p class="font-semibold text-gray-900">Primary 5 attendance closed: 32/34</p>
                                            <p class="text-[10px] text-gray-500">2 absent notifications sent</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Floating Accent Pills -->
                <div class="absolute -left-3 top-2 hidden w-52 rounded-xl border border-green-200/90 bg-white/95 p-3.5 shadow-xl shadow-green-950/10 backdrop-blur-sm sm:block" data-hero-in style="--hero-delay:440ms">
                    <div class="flex items-center justify-between">
                        <p class="text-xs font-bold text-gray-950">Daily Attendance</p>
                        <span class="rounded bg-green-100 px-1.5 py-0.5 text-[10px] font-bold text-green-800">Live</span>
                    </div>
                    <p class="mt-1 text-2xl font-extrabold text-green-700">94.6%</p>
                    <p class="text-[11px] text-gray-500">1,211 Present · 69 Absent</p>
                </div>

                <div class="absolute -right-2 bottom-4 w-56 rounded-xl border border-green-200/90 bg-white/95 p-3.5 shadow-xl shadow-green-950/10 backdrop-blur-sm" data-hero-in style="--hero-delay:500ms">
                    <div class="flex items-center justify-between">
                        <p class="text-xs font-bold text-gray-950">Paystack Verified</p>
                        <span class="h-2 w-2 rounded-full bg-green-600"></span>
                    </div>
                    <p class="mt-1 text-2xl font-extrabold text-gray-950">₦8.42M</p>
                    <p class="text-[11px] font-semibold text-green-700">Instant digital receipts issued</p>
                </div>
            </div>
        </div>
    </section>

    <!-- VALUE PROPOSITION CONTRAST: RUNNING A SCHOOL SHOULDN'T MEAN RUNNING AROUND -->
    <section class="border-y border-gray-100 bg-slate-50 py-20">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="max-w-3xl" data-reveal>
                <p class="text-xs font-bold uppercase tracking-[0.14em] text-green-700">The Problem with Fragmented Tools</p>
                <h2 class="mt-3 text-3xl font-extrabold tracking-tight text-gray-950 sm:text-4xl">
                    Running a school shouldn't mean running around.
                </h2>
                <p class="mt-4 text-base leading-relaxed text-gray-600 sm:text-lg">
                    Most schools struggle not because teachers aren’t working hard, but because vital information is trapped across paper registers, loose spreadsheets, chaotic WhatsApp groups, and lost bank slips. SchoolOS brings order to everyday operations.
                </p>
            </div>

            <div class="mt-12 grid gap-6 lg:grid-cols-2">
                <!-- WITHOUT SCHOOLOS -->
                <div class="rounded-2xl border border-red-200/80 bg-white p-7 shadow-xs" data-reveal style="--reveal-delay:100ms">
                    <div class="flex items-center gap-2 text-xs font-extrabold uppercase tracking-wider text-red-700">
                        <span class="flex h-6 w-6 items-center justify-center rounded-full bg-red-100 text-red-700">✕</span>
                        Without SchoolOS (Fragmented & Exhausting)
                    </div>
                    <div class="mt-6 space-y-4">
                        <div class="flex items-start gap-3">
                            <span class="mt-1 flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-red-50 text-xs font-bold text-red-600">1</span>
                            <div>
                                <h3 class="text-sm font-bold text-gray-900">Paper registers & physical file folders</h3>
                                <p class="mt-1 text-xs leading-relaxed text-gray-600">Attendance books wear out, get misplaced, and require hours of manual tallying at the end of each term to calculate attendance averages.</p>
                            </div>
                        </div>
                        <div class="flex items-start gap-3">
                            <span class="mt-1 flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-red-50 text-xs font-bold text-red-600">2</span>
                            <div>
                                <h3 class="text-sm font-bold text-gray-900">Loose spreadsheets locked on individual laptops</h3>
                                <p class="mt-1 text-xs leading-relaxed text-gray-600">Grades and assessments live in personal files. Combining CA marks and exam results into final report cards takes weeks of error-prone copy-pasting.</p>
                            </div>
                        </div>
                        <div class="flex items-start gap-3">
                            <span class="mt-1 flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-red-50 text-xs font-bold text-red-600">3</span>
                            <div>
                                <h3 class="text-sm font-bold text-gray-900">Unverified bank slips & payment disputes</h3>
                                <p class="mt-1 text-xs leading-relaxed text-gray-600">Parents send paper receipts or screenshots on WhatsApp. Bursars spend full workdays hunting through bank statements to match unverified payments.</p>
                            </div>
                        </div>
                        <div class="flex items-start gap-3">
                            <span class="mt-1 flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-red-50 text-xs font-bold text-red-600">4</span>
                            <div>
                                <h3 class="text-sm font-bold text-gray-900">Chaotic WhatsApp broadcasts with zero accountability</h3>
                                <p class="mt-1 text-xs leading-relaxed text-gray-600">Urgent circulars get buried under chit-chat. School leadership has no way to confirm whether parents actually received vital school announcements.</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- WITH SCHOOLOS -->
                <div class="rounded-2xl border border-green-300 bg-white p-7 shadow-md shadow-green-950/5 ring-1 ring-green-600/10" data-reveal style="--reveal-delay:200ms">
                    <div class="flex items-center gap-2 text-xs font-extrabold uppercase tracking-wider text-green-700">
                        <span class="flex h-6 w-6 items-center justify-center rounded-full bg-green-100 text-green-700">✓</span>
                        With SchoolOS (Connected & In Sync)
                    </div>
                    <div class="mt-6 space-y-4">
                        <div class="flex items-start gap-3">
                            <span class="mt-1 flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-green-100 text-xs font-bold text-green-700">1</span>
                            <div>
                                <h3 class="text-sm font-bold text-gray-900">Single source of truth in the cloud</h3>
                                <p class="mt-1 text-xs leading-relaxed text-gray-600">Student biodata, enrollment history, and guardian records are stored centrally and accessible securely from any browser on any device.</p>
                            </div>
                        </div>
                        <div class="flex items-start gap-3">
                            <span class="mt-1 flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-green-100 text-xs font-bold text-green-700">2</span>
                            <div>
                                <h3 class="text-sm font-bold text-gray-900">Automated grading & instant terminal report cards</h3>
                                <p class="mt-1 text-xs leading-relaxed text-gray-600">Teachers enter CA and exam marks once. SchoolOS automatically calculates totals, grades, positions, and prints clean terminal report sheets.</p>
                            </div>
                        </div>
                        <div class="flex items-start gap-3">
                            <span class="mt-1 flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-green-100 text-xs font-bold text-green-700">3</span>
                            <div>
                                <h3 class="text-sm font-bold text-gray-900">Paystack-verified payments & live balance tracking</h3>
                                <p class="mt-1 text-xs leading-relaxed text-gray-600">Every payment is cryptographically verified server-side. Parents get instant authenticated receipts and bursars see real-time outstanding balances.</p>
                            </div>
                        </div>
                        <div class="flex items-start gap-3">
                            <span class="mt-1 flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-green-100 text-xs font-bold text-green-700">4</span>
                            <div>
                                <h3 class="text-sm font-bold text-gray-900">Official, targeted announcements with delivery records</h3>
                                <p class="mt-1 text-xs leading-relaxed text-gray-600">Broadcast official circulars to specific classes, staff, or all parents directly in their portal with real-time read receipts and zero noise.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- THE CONNECTED SYSTEM (HUB) -->
    <section class="border-b border-gray-100 bg-white py-20">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="text-center" data-reveal>
                <p class="text-xs font-bold uppercase tracking-[0.14em] text-green-700">Connected School Architecture</p>
                <h2 class="mt-3 text-3xl font-extrabold tracking-tight text-gray-950 sm:text-4xl">
                    Everything your school needs. One connected system.
                </h2>
                <p class="mx-auto mt-4 max-w-2xl text-base text-gray-600 sm:text-lg">
                    When a teacher marks attendance, a parent pays tuition, or an administrator submits grades, every department stays updated automatically.
                </p>
            </div>

            <!-- Desktop: radial hub with SchoolOS at the center and each role connected live. -->
            <div class="so-hub relative mx-auto mt-16 hidden aspect-[16/9] w-full max-w-3xl lg:block" data-reveal style="--reveal-delay:120ms">
                <svg class="absolute inset-0 h-full w-full overflow-visible" viewBox="0 0 100 100" preserveAspectRatio="none" aria-hidden="true">
                    @foreach ($hubRoles as $i => [$label, $x, $y, $icon, $task])
                        <line class="so-hub-line" x1="50" y1="50" x2="{{ $x }}" y2="{{ $y }}" stroke="#16a34a" stroke-width="0.6" stroke-linecap="round" style="--line-delay: {{ 150 + $i * 110 }}ms"/>
                    @endforeach
                </svg>

                <div class="so-hub-ring absolute left-1/2 top-1/2 h-28 w-28 rounded-full border-2 border-green-500/80" aria-hidden="true"></div>
                <div class="so-hub-center absolute left-1/2 top-1/2 flex flex-col items-center" aria-hidden="true">
                    <span class="flex h-20 w-20 items-center justify-center rounded-full bg-green-600 text-2xl font-extrabold text-white shadow-xl shadow-green-900/30 ring-4 ring-green-100">S</span>
                    <span class="mt-2 whitespace-nowrap text-sm font-extrabold text-gray-950">SchoolOS Core</span>
                </div>

                @foreach ($hubRoles as $i => [$label, $x, $y, $icon, $task])
                    <article class="so-hub-node absolute w-38 rounded-xl border border-gray-200/90 bg-white p-3.5 text-center shadow-lg shadow-green-950/5 backdrop-blur-sm" style="left: {{ $x }}%; top: {{ $y }}%; --node-delay: {{ 260 + $i * 110 }}ms">
                        <span class="mx-auto flex h-9 w-9 items-center justify-center rounded-lg bg-green-50 text-green-700">
                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="{{ $icon }}" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
                        </span>
                        <p class="mt-2 text-xs font-extrabold text-gray-950">{{ $label }}</p>
                        <p class="mt-0.5 text-[10px] text-gray-500 font-medium">{{ $task }}</p>
                        <span class="mt-1.5 inline-flex items-center gap-1 text-[9px] font-bold uppercase tracking-wider text-green-700">
                            <span class="so-hub-dot h-1.5 w-1.5 rounded-full bg-green-500 animate-pulse"></span>
                            Live Sync
                        </span>
                    </article>
                @endforeach
            </div>

            <!-- Mobile/tablet: responsive stacked version -->
            <div class="mx-auto mt-10 max-w-md lg:hidden">
                <div class="flex flex-col items-center" data-reveal>
                    <span class="flex h-16 w-16 items-center justify-center rounded-full bg-green-600 text-xl font-extrabold text-white shadow-lg shadow-green-900/20">S</span>
                    <span class="mt-2 text-sm font-extrabold text-gray-950">SchoolOS Core Engine</span>
                </div>
                <div class="mt-6 grid grid-cols-2 gap-3 sm:grid-cols-3">
                    @foreach ($hubRoles as $i => [$label, $x, $y, $icon, $task])
                        <div class="rounded-xl border border-gray-200 bg-white p-3.5 text-center shadow-xs" data-reveal style="--reveal-delay: {{ min($i * 80, 320) }}ms">
                            <span class="mx-auto flex h-9 w-9 items-center justify-center rounded-lg bg-green-50 text-green-700">
                                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="{{ $icon }}" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
                            </span>
                            <p class="mt-2 text-xs font-bold text-gray-950">{{ $label }}</p>
                            <p class="text-[10px] text-gray-500 truncate">{{ $task }}</p>
                            <span class="mt-1 inline-flex items-center gap-1 text-[9px] font-semibold uppercase tracking-wider text-green-600">
                                <span class="h-1.5 w-1.5 animate-pulse rounded-full bg-green-500"></span>
                                Connected
                            </span>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </section>

    <!-- REAL SCHOOLOS FEATURES GRID -->
    <section id="features" class="relative overflow-hidden bg-white py-20" data-glow-section>
        <div class="so-glow" data-cursor-glow aria-hidden="true"></div>
        <div class="relative mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="max-w-3xl" data-reveal>
                <p class="text-xs font-bold uppercase tracking-[0.14em] text-green-700">Built for Everyday Operations</p>
                <h2 class="mt-3 text-3xl font-extrabold tracking-tight text-gray-950 sm:text-4xl">
                    Everything your school needs to operate smoothly.
                </h2>
                <p class="mt-4 text-base leading-relaxed text-gray-600 sm:text-lg">
                    SchoolOS provides real, production-ready functionality designed for the practical challenges of running a school.
                </p>
            </div>

            <div class="mt-12 grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
                @foreach ($features as [$title, $copy, $icon, $badge])
                    <article class="group rounded-2xl border border-gray-200/90 bg-white p-6 shadow-xs transition duration-300 hover:-translate-y-1 hover:border-green-300 hover:shadow-xl" data-reveal data-cursor-light style="--reveal-delay: {{ min($loop->index * 70, 450) }}ms">
                        <div class="flex items-center justify-between">
                            <span class="flex h-11 w-11 items-center justify-center rounded-xl bg-green-50 text-green-700 transition-transform duration-300 group-hover:scale-105 group-hover:bg-green-100">
                                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="{{ $icon }}" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
                            </span>
                            <span class="rounded-full bg-slate-100 px-2.5 py-0.5 text-[10px] font-bold text-gray-600">{{ $badge }}</span>
                        </div>
                        <h3 class="mt-5 text-base font-bold text-gray-950">{{ $title }}</h3>
                        <p class="mt-2.5 text-xs leading-relaxed text-gray-600">{{ $copy }}</p>
                    </article>
                @endforeach
            </div>
        </div>
    </section>

    <!-- ROLE-BASED VALUE (INTERACTIVE TABS) -->
    <section id="roles" class="border-y border-gray-100 bg-slate-50 py-20">
        <div class="mx-auto grid max-w-7xl gap-10 px-4 sm:px-6 lg:grid-cols-[0.8fr_1.2fr] lg:px-8">
            <div data-reveal>
                <p class="text-xs font-bold uppercase tracking-[0.14em] text-green-700">Role-Specific Workspaces</p>
                <h2 class="mt-3 text-3xl font-extrabold tracking-tight text-gray-950 sm:text-4xl">One platform. Every role.</h2>
                <p class="mt-4 text-base leading-relaxed text-gray-600">
                    School leadership, teachers, administrative staff, bursars, and parents each get a distraction-free workspace customized for their specific responsibilities.
                </p>
                <div class="mt-8 flex flex-wrap gap-2" role="tablist" aria-label="SchoolOS roles">
                    @foreach ($roles as $role => $content)
                        <button type="button" class="rounded-lg border border-gray-200 bg-white px-4 py-2.5 text-xs font-bold text-gray-700 transition duration-200 hover:-translate-y-0.5 hover:border-green-300 data-[active=true]:border-green-600 data-[active=true]:bg-green-600 data-[active=true]:text-white shadow-xs" data-role-tab="{{ $role }}" data-active="{{ $loop->first ? 'true' : 'false' }}">
                            {{ $role }}
                        </button>
                    @endforeach
                </div>
            </div>

            <div class="rounded-2xl border border-gray-200 bg-white p-7 shadow-xl shadow-green-950/5" data-reveal style="--reveal-delay:120ms">
                @foreach ($roles as $role => [$headline, $subtext, $items, $badge])
                    <div class="{{ $loop->first ? '' : 'hidden' }}" data-role-panel="{{ $role }}">
                        <div class="flex items-center justify-between">
                            <span class="rounded-full bg-green-100 px-3 py-1 text-xs font-bold text-green-800">{{ $badge }}</span>
                            <span class="text-xs font-medium text-gray-400">Portal View</span>
                        </div>
                        <h3 class="mt-4 text-2xl font-extrabold text-gray-950">{{ $headline }}</h3>
                        <p class="mt-2 text-sm leading-relaxed text-gray-600">{{ $subtext }}</p>

                        <div class="mt-6 grid gap-3 sm:grid-cols-2">
                            @foreach ($items as $item)
                                <div class="flex items-start gap-2.5 rounded-xl border border-gray-100 bg-slate-50/70 p-3.5 text-xs font-semibold text-gray-800 shadow-xs">
                                    <span class="mt-0.5 text-green-600 font-bold">✓</span>
                                    <span>{{ $item }}</span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    <!-- DETAILED DEEP-DIVE: ATTENDANCE & ACADEMICS -->
    <section class="bg-white py-20">
        <div class="mx-auto grid max-w-7xl items-center gap-12 px-4 sm:px-6 lg:grid-cols-2 lg:px-8">
            <div data-reveal>
                <div class="inline-flex items-center gap-1.5 rounded-full bg-green-50 px-3 py-1 text-xs font-bold text-green-800">
                    <span class="h-1.5 w-1.5 rounded-full bg-green-600"></span>
                    Fast Classroom Attendance
                </div>
                <h2 class="mt-4 text-3xl font-extrabold tracking-tight text-gray-950 sm:text-4xl">
                    Attendance without the morning paperwork.
                </h2>
                <p class="mt-4 text-base leading-relaxed text-gray-600">
                    Teachers can mark daily morning and afternoon roll calls on their smartphone or classroom PC in less than 30 seconds. Absentees are flagged immediately, and cumulative statistics are recorded for end-of-term report cards.
                </p>
                <ul class="mt-6 space-y-3 text-sm text-gray-700">
                    <li class="flex items-center gap-2.5">
                        <svg class="h-4 w-4 text-green-600 shrink-0" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                        <span>Single-tap toggles for Present, Absent, and Late</span>
                    </li>
                    <li class="flex items-center gap-2.5">
                        <svg class="h-4 w-4 text-green-600 shrink-0" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                        <span>Automatic absence alerts in the parent portal</span>
                    </li>
                    <li class="flex items-center gap-2.5">
                        <svg class="h-4 w-4 text-green-600 shrink-0" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                        <span>Automated percentage computation for term reports</span>
                    </li>
                </ul>
            </div>

            <div class="rounded-2xl border border-gray-200 bg-slate-50/80 p-5 shadow-xl shadow-green-950/5" data-reveal style="--reveal-delay:120ms">
                <div class="rounded-xl border border-gray-200/80 bg-white p-5 shadow-xs">
                    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-100 pb-3">
                        <div>
                            <p class="text-sm font-extrabold text-gray-950">Primary 5 Gold · Morning Roll Call</p>
                            <p class="text-xs text-gray-500">{{ now()->format('l, F j, Y') }} · Class Teacher: Mrs. Adeyemi</p>
                        </div>
                        <span class="rounded-full bg-green-100 px-3 py-1 text-xs font-bold text-green-800">94.1% Present</span>
                    </div>

                    <div class="mt-4 space-y-2.5">
                        @foreach ([
                            ['Daniel Johnson', 'ADM-2024-018', 'Present', 'bg-green-50 text-green-700 border-green-200'],
                            ['Mariam Bello', 'ADM-2024-042', 'Present', 'bg-green-50 text-green-700 border-green-200'],
                            ['Chinedu Okafor', 'ADM-2024-055', 'Absent', 'bg-red-50 text-red-700 border-red-200'],
                            ['Aisha Musa', 'ADM-2024-067', 'Present', 'bg-green-50 text-green-700 border-green-200'],
                            ['Emmanuel Adeleke', 'ADM-2024-089', 'Present', 'bg-green-50 text-green-700 border-green-200']
                        ] as [$student, $adm, $status, $badgeClass])
                            <div class="flex items-center justify-between rounded-lg border border-gray-100 bg-slate-50/50 px-3.5 py-2.5 transition hover:bg-white hover:shadow-xs">
                                <div>
                                    <p class="text-xs font-bold text-gray-900">{{ $student }}</p>
                                    <p class="text-[10px] text-gray-500 font-mono">{{ $adm }}</p>
                                </div>
                                <span class="rounded-md border px-2.5 py-0.5 text-xs font-bold {{ $badgeClass }}">{{ $status }}</span>
                            </div>
                        @endforeach
                    </div>
                    <div class="mt-4 rounded-lg border border-green-200 bg-green-50/70 px-3.5 py-2.5 text-xs font-bold text-green-800 flex items-center justify-between">
                        <span>Register locked and synchronized with admin dashboard.</span>
                        <span class="text-[10px] text-green-700">08:45 AM</span>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- DETAILED DEEP-DIVE: FEES, PART-PAYMENTS & PAYSTACK -->
    <section class="border-y border-gray-100 bg-slate-50 py-20">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="max-w-3xl" data-reveal>
                <p class="text-xs font-bold uppercase tracking-[0.14em] text-green-700">Financial Management & Reconciliation</p>
                <h2 class="mt-3 text-3xl font-extrabold tracking-tight text-gray-950 sm:text-4xl">
                    Make school fees transparent and effortless to collect.
                </h2>
                <p class="mt-4 text-base leading-relaxed text-gray-600 sm:text-lg">
                    Full payments, flexible installments, and custom levies are all tracked automatically with server-side Paystack verification and instant PDF receipts.
                </p>
            </div>

            <div class="mt-12 grid gap-6 lg:grid-cols-[0.8fr_1.2fr]">
                <!-- Financial Overview Cards -->
                <div class="grid gap-3 sm:grid-cols-3 lg:grid-cols-1">
                    @foreach ([
                        ['Term Assessment Target', '₦24,500,000', '100% assessed across 1,280 students', 'text-gray-950'],
                        ['Collected & Verified', '₦18,750,000', '76.5% collection rate to date', 'text-green-700'],
                        ['Live Outstanding Balance', '₦5,750,000', 'Tracked across part-paying accounts', 'text-amber-800'],
                    ] as [$label, $value, $sub, $color])
                        <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-xs transition duration-300 hover:-translate-y-0.5 hover:shadow-md" data-reveal style="--reveal-delay: {{ $loop->index * 80 }}ms">
                            <p class="text-xs font-bold uppercase tracking-wider text-gray-500">{{ $label }}</p>
                            <p class="mt-2 text-2xl font-extrabold {{ $color }}">{{ $value }}</p>
                            <p class="mt-1 text-[11px] text-gray-500">{{ $sub }}</p>
                        </div>
                    @endforeach
                </div>

                <!-- Interactive Payment Simulation -->
                <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-xl shadow-green-950/5" data-reveal style="--reveal-delay:150ms">
                    <div class="flex flex-wrap gap-2" role="tablist" aria-label="Payment scenarios">
                        @foreach (['Full payment', 'Part payment', 'Custom levy'] as $tab)
                            <button type="button" class="rounded-lg border border-gray-200 px-4 py-2 text-xs font-bold transition duration-200 hover:-translate-y-0.5 data-[active=true]:border-green-600 data-[active=true]:bg-green-600 data-[active=true]:text-white shadow-xs" data-payment-tab="{{ $tab }}" data-active="{{ $loop->first ? 'true' : 'false' }}">
                                {{ $tab }}
                            </button>
                        @endforeach
                    </div>

                    <div class="mt-6">
                        <!-- Panel: Full Payment -->
                        <div data-payment-panel="Full payment">
                            <div class="flex items-center justify-between">
                                <span class="text-xs font-bold uppercase tracking-wider text-green-700">Scenario 01 · Full Settlement</span>
                                <span class="rounded bg-green-100 px-2 py-0.5 text-[10px] font-bold text-green-800">Complete</span>
                            </div>
                            <h3 class="mt-2 text-xl font-bold text-gray-950">Pay tuition in full with automated receipting</h3>
                            <p class="mt-1 text-xs text-gray-600">Parent initiates payment via Paystack; funds clear directly into the school account and a signed PDF receipt is delivered instantly.</p>

                            <div class="mt-5 rounded-xl border border-green-200 bg-green-50/70 p-5">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <p class="text-xs text-gray-600 font-medium">JSS 2 Term 1 Tuition & Sundry</p>
                                        <p class="text-2xl font-extrabold text-gray-950 mt-0.5">₦250,000</p>
                                    </div>
                                    <span class="rounded-full bg-green-600 px-3 py-1 text-xs font-bold text-white shadow-xs">Fully Settled</span>
                                </div>
                                <div class="mt-4 pt-3 border-t border-green-200/60 flex items-center justify-between text-[11px] text-gray-600">
                                    <span>Paystack Ref: <code class="font-mono text-gray-900 font-bold">pstk_849204818</code></span>
                                    <span class="text-green-800 font-bold">✓ PDF Receipt Generated</span>
                                </div>
                            </div>
                        </div>

                        <!-- Panel: Part Payment -->
                        <div class="hidden" data-payment-panel="Part payment">
                            <div class="flex items-center justify-between">
                                <span class="text-xs font-bold uppercase tracking-wider text-amber-700">Scenario 02 · Installment Tracking</span>
                                <span class="rounded bg-amber-100 px-2 py-0.5 text-[10px] font-bold text-amber-800">Partially Paid</span>
                            </div>
                            <h3 class="mt-2 text-xl font-bold text-gray-950">Flexible installment tracking without bookkeeping headaches</h3>
                            <p class="mt-1 text-xs text-gray-600">Accept initial deposits while keeping exact track of what remains outstanding per child.</p>

                            <div class="mt-5 rounded-xl border border-gray-200 bg-slate-50/80 p-5">
                                <div class="grid grid-cols-3 gap-2 text-center sm:text-left">
                                    <div>
                                        <span class="text-[10px] font-bold text-gray-500 uppercase">Assessed Total</span>
                                        <p class="text-base font-extrabold text-gray-950">₦250,000</p>
                                    </div>
                                    <div>
                                        <span class="text-[10px] font-bold text-green-700 uppercase">Paid So Far</span>
                                        <p class="text-base font-extrabold text-green-700">₦150,000</p>
                                    </div>
                                    <div>
                                        <span class="text-[10px] font-bold text-amber-700 uppercase">Remaining</span>
                                        <p class="text-base font-extrabold text-amber-700">₦100,000</p>
                                    </div>
                                </div>
                                <div class="mt-4 h-2.5 w-full rounded-full bg-gray-200 overflow-hidden">
                                    <div class="h-full rounded-full bg-green-600" style="width: 60%"></div>
                                </div>
                                <div class="mt-3.5 space-y-1.5 text-xs text-gray-600">
                                    <div class="flex justify-between font-mono text-[11px]">
                                        <span>Installment 1 (Sept 12): ₦100,000</span>
                                        <span class="text-green-700 font-bold">Verified</span>
                                    </div>
                                    <div class="flex justify-between font-mono text-[11px]">
                                        <span>Installment 2 (Oct 05): ₦50,000</span>
                                        <span class="text-green-700 font-bold">Verified</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Panel: Custom Levy -->
                        <div class="hidden" data-payment-panel="Custom levy">
                            <div class="flex items-center justify-between">
                                <span class="text-xs font-bold uppercase tracking-wider text-blue-700">Scenario 03 · Specific Levies & Excursions</span>
                                <span class="rounded bg-blue-100 px-2 py-0.5 text-[10px] font-bold text-blue-800">Targeted</span>
                            </div>
                            <h3 class="mt-2 text-xl font-bold text-gray-950">Add targeted fees for excursions, uniforms, or labs</h3>
                            <p class="mt-1 text-xs text-gray-600">Bill specific class arms or individual participants without mixing into general tuition accounts.</p>

                            <div class="mt-5 rounded-xl border border-gray-200 bg-slate-50/80 p-5">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <p class="text-xs text-gray-500 font-medium">SSS 2 Science Laboratory Assessment</p>
                                        <p class="text-2xl font-extrabold text-gray-950 mt-0.5">₦35,000</p>
                                    </div>
                                    <span class="rounded-full bg-amber-100 px-3 py-1 text-xs font-bold text-amber-800">Due in 5 Days</span>
                                </div>
                                <p class="mt-3 text-xs text-gray-600">Assessed to 84 science students · 62 payments recorded · ₦770,000 collected</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- CLOUD PLATFORM: YOUR SCHOOL, ORGANIZED ONLINE -->
    <section class="bg-white py-20">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="max-w-3xl" data-reveal>
                <p class="text-xs font-bold uppercase tracking-[0.14em] text-green-700">Cloud Accessibility</p>
                <h2 class="mt-3 text-3xl font-extrabold tracking-tight text-gray-950 sm:text-4xl">
                    Your school, organized online.
                </h2>
                <p class="mt-4 text-base leading-relaxed text-gray-600 sm:text-lg">
                    School records should never be trapped on a single desktop computer in an administrative office. SchoolOS gives your leadership, teachers, and parents instant, role-restricted access from anywhere in the world.
                </p>
            </div>

            <div class="mt-12 grid gap-6 sm:grid-cols-3">
                <article class="rounded-2xl border border-gray-200/90 bg-white p-6 shadow-xs transition duration-300 hover:-translate-y-1 hover:border-green-300 hover:shadow-lg" data-reveal style="--reveal-delay:100ms">
                    <span class="flex h-11 w-11 items-center justify-center rounded-xl bg-green-50 text-green-700">
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    </span>
                    <h3 class="mt-4 text-base font-bold text-gray-950">Access from Any Device</h3>
                    <p class="mt-2 text-xs leading-relaxed text-gray-600">Access your school from laptops, tablets, or smartphones without installing bloated software or buying expensive local servers.</p>
                </article>

                <article class="rounded-2xl border border-gray-200/90 bg-white p-6 shadow-xs transition duration-300 hover:-translate-y-1 hover:border-green-300 hover:shadow-lg" data-reveal style="--reveal-delay:180ms">
                    <span class="flex h-11 w-11 items-center justify-center rounded-xl bg-green-50 text-green-700">
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    </span>
                    <h3 class="mt-4 text-base font-bold text-gray-950">Instant Synchronization</h3>
                    <p class="mt-2 text-xs leading-relaxed text-gray-600">When teachers input exam scores or bursars approve a payment, updates are immediately reflected across admin reports and parent portals.</p>
                </article>

                <article class="rounded-2xl border border-gray-200/90 bg-white p-6 shadow-xs transition duration-300 hover:-translate-y-1 hover:border-green-300 hover:shadow-lg" data-reveal style="--reveal-delay:260ms">
                    <span class="flex h-11 w-11 items-center justify-center rounded-xl bg-green-50 text-green-700">
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    </span>
                    <h3 class="mt-4 text-base font-bold text-gray-950">Zero Local Hardware Risk</h3>
                    <p class="mt-2 text-xs leading-relaxed text-gray-600">Never worry about hard drive failures, power surges, or lost flash drives. Your school's database is encrypted and backed up automatically.</p>
                </article>
            </div>
        </div>
    </section>

    <!-- HOW SCHOOLOS WORKS (4 STEPS) -->
    <section class="border-y border-gray-100 bg-slate-50 py-20">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="text-center" data-reveal>
                <p class="text-xs font-bold uppercase tracking-[0.14em] text-green-700">Simple 4-Step Setup</p>
                <h2 class="mt-3 text-3xl font-extrabold tracking-tight text-gray-950 sm:text-4xl">
                    From onboarding to full operation in minutes.
                </h2>
                <p class="mx-auto mt-4 max-w-xl text-base text-gray-600">
                    Our guided setup wizard ensures your school is configured cleanly and ready for staff and parents.
                </p>
            </div>

            <div class="relative mt-14 grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
                @foreach ([
                    ['01', 'Set Up Your School', 'Register your school name, unique workspace slug, academic levels (Creche, Nursery, Primary, Secondary), and term calendar.'],
                    ['02', 'Configure School Structure', 'Set up classes, arms/sections, academic subjects, and customized continuous assessment grading scales.'],
                    ['03', 'Add Staff & Students', 'Import or enter student rosters, link parent guardian emails, and assign teachers to their respective classes and subjects.'],
                    ['04', 'Run Your School', 'Take daily attendance, publish announcements, enter continuous assessment scores, and collect fees with complete confidence.'],
                ] as [$num, $title, $copy])
                    <article class="relative rounded-2xl border border-gray-200 bg-white p-6 shadow-xs transition duration-300 hover:-translate-y-1 hover:border-green-300 hover:shadow-lg" data-reveal style="--reveal-delay: {{ $loop->index * 100 }}ms">
                        <span class="flex h-12 w-12 items-center justify-center rounded-xl bg-green-600 text-sm font-extrabold text-white shadow-sm shadow-green-600/30">{{ $num }}</span>
                        <h3 class="mt-5 text-base font-bold text-gray-950">{{ $title }}</h3>
                        <p class="mt-2 text-xs leading-relaxed text-gray-600">{{ $copy }}</p>
                    </article>
                @endforeach
            </div>
        </div>
    </section>

    <!-- GENUINE TRUST SECTION: ARCHITECTURE & SECURITY GUARANTEES (NO FAKE TESTIMONIALS) -->
    <section class="bg-white py-20">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="max-w-3xl" data-reveal>
                <p class="text-xs font-bold uppercase tracking-[0.14em] text-green-700">Enterprise Engineering</p>
                <h2 class="mt-3 text-3xl font-extrabold tracking-tight text-gray-950 sm:text-4xl">
                    Engineered for integrity. Built on trust.
                </h2>
                <p class="mt-4 text-base leading-relaxed text-gray-600 sm:text-lg">
                    Schools manage sensitive student records and financial transactions. Rather than displaying fabricated testimonial quotes, we guarantee transparency in our architecture and security safeguards.
                </p>
            </div>

            <div class="mt-12 grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
                <article class="rounded-2xl border border-gray-200/90 bg-slate-50/60 p-6 shadow-xs transition duration-300 hover:-translate-y-1 hover:bg-white hover:shadow-md" data-reveal style="--reveal-delay:80ms">
                    <span class="rounded bg-green-100 px-2 py-1 text-[10px] font-extrabold uppercase tracking-wider text-green-800">Multi-Tenant</span>
                    <h3 class="mt-4 text-base font-bold text-gray-950">Tenant Data Isolation</h3>
                    <p class="mt-2 text-xs leading-relaxed text-gray-600">Every school’s records are strictly segregated. Server-side tenant scoping prevents cross-institution data exposure at the database level.</p>
                </article>

                <article class="rounded-2xl border border-gray-200/90 bg-slate-50/60 p-6 shadow-xs transition duration-300 hover:-translate-y-1 hover:bg-white hover:shadow-md" data-reveal style="--reveal-delay:160ms">
                    <span class="rounded bg-green-100 px-2 py-1 text-[10px] font-extrabold uppercase tracking-wider text-green-800">Security</span>
                    <h3 class="mt-4 text-base font-bold text-gray-950">Role-Based Access (RBAC)</h3>
                    <p class="mt-2 text-xs leading-relaxed text-gray-600">Strict permission boundaries enforced on every server request. Teachers only access their assigned classes; parents only see verified dependents.</p>
                </article>

                <article class="rounded-2xl border border-gray-200/90 bg-slate-50/60 p-6 shadow-xs transition duration-300 hover:-translate-y-1 hover:bg-white hover:shadow-md" data-reveal style="--reveal-delay:240ms">
                    <span class="rounded bg-green-100 px-2 py-1 text-[10px] font-extrabold uppercase tracking-wider text-green-800">Finance</span>
                    <h3 class="mt-4 text-base font-bold text-gray-950">Cryptographic Webhooks</h3>
                    <p class="mt-2 text-xs leading-relaxed text-gray-600">Never trust client amounts. All online transactions validate Paystack HMAC SHA-512 signatures with idempotent database recording.</p>
                </article>

                <article class="rounded-2xl border border-gray-200/90 bg-slate-50/60 p-6 shadow-xs transition duration-300 hover:-translate-y-1 hover:bg-white hover:shadow-md" data-reveal style="--reveal-delay:320ms">
                    <span class="rounded bg-green-100 px-2 py-1 text-[10px] font-extrabold uppercase tracking-wider text-green-800">Accountability</span>
                    <h3 class="mt-4 text-base font-bold text-gray-950">Immutable Audit Trails</h3>
                    <p class="mt-2 text-xs leading-relaxed text-gray-600">Grade modifications, fee adjustments, and user role updates are permanently recorded with timestamps and operator user IDs.</p>
                </article>
            </div>
        </div>
    </section>

    <!-- ACCURATE FAQ SECTION -->
    <section id="faq" class="border-t border-gray-100 bg-slate-50 py-20">
        <div class="mx-auto max-w-4xl px-4 sm:px-6 lg:px-8">
            <div class="text-center" data-reveal>
                <p class="text-xs font-bold uppercase tracking-[0.14em] text-green-700">Frequently Asked Questions</p>
                <h2 class="mt-3 text-3xl font-extrabold tracking-tight text-gray-950 sm:text-4xl">
                    Answers to common questions.
                </h2>
                <p class="mt-3 text-base text-gray-600">
                    Everything you need to know about SchoolOS capabilities, pricing, and architecture.
                </p>
            </div>

            <div class="mt-12 divide-y divide-gray-200/90 rounded-2xl border border-gray-200 bg-white shadow-xs" data-reveal style="--reveal-delay:100ms">
                @foreach ($faqs as $question => $answer)
                    <details class="group p-5 transition-colors hover:bg-slate-50/40">
                        <summary class="flex cursor-pointer list-none items-center justify-between gap-4 text-sm font-bold text-gray-950">
                            <span>{{ $question }}</span>
                            <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-green-50 text-sm font-bold text-green-700 transition group-open:rotate-45">+</span>
                        </summary>
                        <p class="mt-3 text-xs leading-relaxed text-gray-600 pr-8">{{ $answer }}</p>
                    </details>
                @endforeach
            </div>
        </div>
    </section>

    <!-- FINAL CALL TO ACTION -->
    <section class="relative overflow-hidden bg-white py-20" data-glow-section>
        <div class="so-glow" data-cursor-glow aria-hidden="true"></div>
        <div class="relative mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="rounded-3xl border border-green-200 bg-gradient-to-b from-green-50/80 to-emerald-50/40 p-8 sm:p-12 lg:p-16 text-center shadow-lg shadow-green-950/5" data-reveal>
                <span class="inline-flex items-center gap-1.5 rounded-full bg-green-100 px-3.5 py-1 text-xs font-bold uppercase tracking-wider text-green-800">
                    <span class="h-2 w-2 rounded-full bg-green-600"></span>
                    Ready for your school
                </span>
                <h2 class="mx-auto mt-6 max-w-2xl text-3xl font-extrabold tracking-tight text-gray-950 sm:text-4xl lg:text-5xl">
                    Ready to bring your school operations together?
                </h2>
                <p class="mx-auto mt-5 max-w-xl text-base leading-relaxed text-gray-600 sm:text-lg">
                    Manage student records, attendance, academics, fees, and communication from one connected platform.
                </p>
                <div class="mt-8 flex flex-col justify-center gap-3 sm:flex-row">
                    <a href="{{ route('public.onboarding.create') }}" class="inline-flex min-h-12 items-center justify-center rounded-lg bg-green-600 px-8 text-base font-bold text-white shadow-md shadow-green-600/25 transition duration-200 hover:-translate-y-0.5 hover:bg-green-700 hover:shadow-lg active:translate-y-0 active:scale-[0.98]">
                        Get Started
                    </a>
                    <a href="{{ route('login') }}" class="inline-flex min-h-12 items-center justify-center rounded-lg border border-gray-300 bg-white px-8 text-base font-bold text-gray-800 shadow-xs transition duration-200 hover:-translate-y-0.5 hover:border-green-400 hover:bg-green-50/50 active:translate-y-0 active:scale-[0.98]">
                        Log In
                    </a>
                </div>
                <p class="mt-6 text-xs font-semibold text-gray-500">
                    No complicated hardware installation · Guided setup wizard · Instant school activation
                </p>
            </div>
        </div>
    </section>
</main>

<x-public-footer />
</body>
</html>
