@php
    $footerGroups = [
        'Product' => [
            ['Features', route('marketing.features.index')],
            ['Student Management', route('marketing.features.student-management')],
            ['Attendance', route('marketing.features.attendance')],
            ['Academics', route('marketing.features.academics')],
            ['Fees & Payments', route('marketing.features.fees-payments')],
            ['Communication', route('marketing.features.communication')],
            ['Reports', route('marketing.features.reports')],
            ['Pricing', route('marketing.pricing')],
        ],
        'Solutions' => [
            ['For School Admins', route('marketing.solutions.school-admins')],
            ['For Teachers', route('marketing.solutions.teachers')],
            ['For Parents', route('marketing.solutions.parents')],
            ['For Students', route('marketing.solutions.students')],
            ['For Staff', route('marketing.solutions.staff')],
            ['For Private Schools', route('marketing.solutions.private-schools')],
            ['For Multi-campus Schools', route('marketing.solutions.multi-campus')],
        ],
        'Resources' => [
            ['Help Center', route('marketing.help')],
            ['Documentation', route('marketing.docs')],
            ['Getting Started', route('marketing.getting-started')],
            ['FAQs', route('marketing.faqs')],
            ['Contact Support', route('marketing.support')],
        ],
        'Company' => [
            ['About SchoolOS', route('marketing.about')],
            ['Contact', route('marketing.contact')],
            ['Careers', route('marketing.careers')],
            ['Partners', route('marketing.partners')],
        ],
        'Legal' => [
            ['Privacy Policy', route('marketing.privacy')],
            ['Terms of Service', route('marketing.terms')],
            ['Cookie Policy', route('marketing.cookies')],
        ],
    ];

    $socialLinks = [
        ['X', 'https://x.com/schoolosafrica', 'M18.244 2.25h3.308l-7.227 8.26 8.502 11.24h-6.656l-5.214-6.817-5.968 6.817H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231 5.45-6.231Zm-1.161 17.52h1.833L7.084 4.126H5.117L17.083 19.77Z'],
        ['LinkedIn', 'https://www.linkedin.com/company/schoolos-africaa', 'M4.98 3.5C4.98 4.88 3.87 6 2.5 6S.02 4.88.02 3.5 1.13 1 2.5 1s2.48 1.12 2.48 2.5ZM.34 8.08h4.32V23H.34V8.08ZM7.55 8.08h4.14v2.04h.06c.58-1.1 1.99-2.26 4.09-2.26 4.37 0 5.18 2.88 5.18 6.62V23h-4.31v-7.56c0-1.8-.03-4.12-2.51-4.12-2.52 0-2.9 1.96-2.9 3.99V23H7.55V8.08Z'],
        ['Instagram', 'https://www.instagram.com/schoolosafrica', 'M7 2h10a5 5 0 0 1 5 5v10a5 5 0 0 1-5 5H7a5 5 0 0 1-5-5V7a5 5 0 0 1 5-5Zm0 2a3 3 0 0 0-3 3v10a3 3 0 0 0 3 3h10a3 3 0 0 0 3-3V7a3 3 0 0 0-3-3H7Zm5 3.8A4.2 4.2 0 1 1 12 16.2 4.2 4.2 0 0 1 12 7.8Zm0 2A2.2 2.2 0 1 0 12 14.2 2.2 2.2 0 0 0 12 9.8Zm5.05-3.05a1.05 1.05 0 1 1-1.05 1.05 1.05 1.05 0 0 1 1.05-1.05Z'],
    ];
@endphp

<footer class="schoolos-footer" data-reveal>
    <div class="mx-auto max-w-7xl px-4 py-14 sm:px-6 lg:px-8 lg:py-16">
        <section class="schoolos-footer-cta rounded-xl border p-6 sm:p-8 lg:flex lg:items-center lg:justify-between lg:gap-10" aria-labelledby="footer-cta-title">
            <div class="max-w-2xl">
                <h2 id="footer-cta-title" class="text-2xl font-bold tracking-tight sm:text-3xl">Ready to simplify your school?</h2>
                <p class="mt-3 text-sm leading-6 sm:text-base">Bring admissions, students, teachers, attendance, academics, communication, fees, and reporting together in one modern platform.</p>
            </div>
            <div class="mt-6 flex flex-col gap-3 sm:flex-row lg:mt-0">
                <a href="{{ route('public.onboarding.create') }}" class="schoolos-footer-button schoolos-footer-button-primary">Get Started</a>
                <a href="{{ route('marketing.demo') }}" class="schoolos-footer-button schoolos-footer-button-secondary">Book a Demo</a>
            </div>
        </section>

        <div class="mt-12 grid gap-10 lg:grid-cols-[1.35fr_repeat(5,minmax(0,1fr))]">
            <div>
                <x-schoolos-logo :on-dark="true" />
                <p class="mt-5 max-w-sm text-sm leading-6">Modern school management software built to help schools run smarter, communicate better, and spend more time focused on education.</p>
                <div class="mt-6 flex gap-3">
                    @foreach ($socialLinks as [$label, $href, $path])
                        <a href="{{ $href }}" target="_blank" rel="noopener noreferrer" aria-label="Visit SchoolOS on {{ $label }}" title="{{ $label }}" class="schoolos-social-link">
                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="{{ $path }}"/></svg>
                        </a>
                    @endforeach
                </div>
            </div>

            @foreach ($footerGroups as $heading => $links)
                <details class="schoolos-footer-group group" open>
                    <summary class="flex cursor-pointer list-none items-center justify-between gap-3 text-sm font-bold uppercase tracking-[0.12em]">
                        {{ $heading }}
                        <span class="schoolos-footer-chevron sm:hidden" aria-hidden="true">+</span>
                    </summary>
                    <ul class="mt-4 grid gap-3">
                        @foreach ($links as [$label, $href])
                            <li>
                                <a href="{{ $href }}" class="schoolos-footer-link">{{ $label }}</a>
                            </li>
                        @endforeach
                    </ul>
                </details>
            @endforeach
        </div>

        <div class="schoolos-footer-contact mt-12 flex flex-col gap-4 border-y py-6 text-sm sm:flex-row sm:items-center sm:justify-between">
            <p class="font-semibold">Questions? We're here to help.</p>
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:gap-5">
                <a href="mailto:support@schoolos.com" class="schoolos-footer-link">support@schoolos.com</a>
                <a href="{{ route('marketing.support') }}" class="schoolos-footer-link font-semibold">Contact Support</a>
            </div>
        </div>

        <div class="mt-6 flex flex-col gap-4 text-sm sm:flex-row sm:items-center sm:justify-between">
            <p>&copy; 2026 SchoolOS. All rights reserved.</p>
            <div class="flex flex-wrap gap-4">
                <a href="{{ route('marketing.privacy') }}" class="schoolos-footer-link">Privacy</a>
                <a href="{{ route('marketing.terms') }}" class="schoolos-footer-link">Terms</a>
                <a href="{{ route('marketing.cookies') }}" class="schoolos-footer-link">Cookies</a>
            </div>
        </div>
    </div>
</footer>
