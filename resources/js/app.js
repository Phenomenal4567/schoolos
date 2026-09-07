// SchoolOS application shell behavior. Deliberately dependency-free
// (no Alpine/jQuery/etc. are installed — see package.json) so this
// is the one place shell-wide interactivity lives.

document.addEventListener('DOMContentLoaded', () => {
    initThemeSwitcher();
    initMobileNav();
    initUserMenu();
    initDialogTriggers();
    initAlertDismiss();
    initOnboardingWizard();
    initPasswordToggles();
    initLoadingForms();
    initLandingPage();
    initFooterAccordions();
});

function initThemeSwitcher() {
    const media = window.matchMedia('(prefers-color-scheme: dark)');
    const applyTheme = (choice) => {
        const selected = choice || localStorage.getItem('schoolos-theme') || 'system';
        const resolved = selected === 'system' ? (media.matches ? 'dark' : 'light') : selected;

        document.documentElement.dataset.theme = resolved;
        document.documentElement.dataset.themeChoice = selected;
        document.documentElement.style.colorScheme = resolved;
        localStorage.setItem('schoolos-theme', selected);

        document.querySelectorAll('[data-theme-option]').forEach((button) => {
            const isActive = button.dataset.themeOption === selected;
            button.dataset.active = String(isActive);
            button.setAttribute('aria-pressed', String(isActive));
        });
    };

    document.querySelectorAll('[data-theme-option]').forEach((button) => {
        button.addEventListener('click', () => applyTheme(button.dataset.themeOption));
    });

    media.addEventListener?.('change', () => {
        if ((localStorage.getItem('schoolos-theme') || 'system') === 'system') {
            applyTheme('system');
        }
    });

    applyTheme(localStorage.getItem('schoolos-theme') || 'system');
}

function initMobileNav() {
    const nav = document.querySelector('[data-mobile-nav]');
    const overlay = document.querySelector('[data-mobile-nav-overlay]');
    const openBtn = document.querySelector('[data-mobile-nav-open]');
    const closeBtn = document.querySelector('[data-mobile-nav-close]');
    if (!nav || !overlay) return;

    const open = () => {
        nav.classList.remove('-translate-x-full');
        overlay.classList.remove('hidden');
    };
    const close = () => {
        nav.classList.add('-translate-x-full');
        overlay.classList.add('hidden');
    };

    openBtn?.addEventListener('click', open);
    closeBtn?.addEventListener('click', close);
    overlay.addEventListener('click', close);
}

function initUserMenu() {
    document.querySelectorAll('[data-user-menu]').forEach((menu) => {
        const toggle = menu.querySelector('[data-user-menu-toggle]');
        const panel = menu.querySelector('[data-user-menu-panel]');
        if (!toggle || !panel) return;

        toggle.addEventListener('click', (event) => {
            event.stopPropagation();
            panel.classList.toggle('hidden');
        });

        document.addEventListener('click', (event) => {
            if (!menu.contains(event.target)) {
                panel.classList.add('hidden');
            }
        });
    });
}

// Any element with data-dialog-open="<dialog id>" opens that <dialog>.
// Any element with data-dialog-close (inside a dialog) closes its dialog.
function initDialogTriggers() {
    document.querySelectorAll('[data-dialog-open]').forEach((trigger) => {
        trigger.addEventListener('click', () => {
            const dialog = document.getElementById(trigger.getAttribute('data-dialog-open'));
            dialog?.showModal();
        });
    });

    document.querySelectorAll('[data-dialog]').forEach((dialog) => {
        dialog.querySelectorAll('[data-dialog-close]').forEach((btn) => {
            btn.addEventListener('click', () => dialog.close());
        });
        // Click on the ::backdrop (i.e. the dialog element itself, not its content) closes it.
        dialog.addEventListener('click', (event) => {
            if (event.target === dialog) dialog.close();
        });
    });
}

function initAlertDismiss() {
    document.querySelectorAll('[data-alert]').forEach((alert) => {
        alert.querySelector('[data-alert-dismiss]')?.addEventListener('click', () => {
            alert.remove();
        });
    });
}

function initOnboardingWizard() {
    const form = document.querySelector('[data-onboarding-form]');
    if (!form) return;

    const sections = Array.from(form.querySelectorAll('[data-step]'));
    const nextBtn = form.querySelector('[data-next-step]');
    const prevBtn = form.querySelector('[data-prev-step]');
    const submitBtn = form.querySelector('[data-submit-button]');
    const mobileLabel = document.querySelector('[data-mobile-step-label]');
    const mobileTitle = document.querySelector('[data-mobile-step-title]');
    const mobileProgress = document.querySelector('[data-mobile-progress]');
    const progressItems = Array.from(document.querySelectorAll('[data-progress-item]'));
    const stepWithError = sections.find((section) => section.querySelector('.text-red-600'));
    let currentStep = stepWithError ? Number(stepWithError.dataset.step) : 1;

    const showStep = (step) => {
        currentStep = Math.min(Math.max(step, 1), sections.length);

        sections.forEach((section) => {
            section.classList.toggle('hidden', Number(section.dataset.step) !== currentStep);
        });

        progressItems.forEach((item) => {
            const itemStep = Number(item.dataset.progressItem);
            const marker = item.querySelector('span');
            item.className = 'flex items-center gap-3 text-sm';
            marker.className = 'flex h-7 w-7 items-center justify-center rounded-full border text-xs font-semibold';

            if (itemStep < currentStep) {
                item.classList.add('text-green-700');
                marker.classList.add('border-green-600', 'bg-green-600', 'text-white');
                marker.textContent = '✓';
            } else if (itemStep === currentStep) {
                item.classList.add('font-semibold', 'text-gray-950');
                marker.classList.add('border-green-600', 'bg-green-50', 'text-green-700');
                marker.textContent = String(itemStep);
            } else {
                item.classList.add('text-gray-500');
                marker.classList.add('border-gray-200', 'text-gray-400');
                marker.textContent = '○';
            }
        });

        const activeSection = sections[currentStep - 1];
        mobileLabel && (mobileLabel.textContent = `Step ${currentStep} of ${sections.length}`);
        mobileTitle && (mobileTitle.textContent = activeSection.dataset.stepTitle || '');
        mobileProgress && (mobileProgress.style.width = `${(currentStep / sections.length) * 100}%`);

        prevBtn?.classList.toggle('hidden', currentStep === 1);
        nextBtn?.classList.toggle('hidden', currentStep === sections.length);
        submitBtn?.classList.toggle('hidden', currentStep !== sections.length);
    };

    nextBtn?.addEventListener('click', () => showStep(currentStep + 1));
    prevBtn?.addEventListener('click', () => showStep(currentStep - 1));
    showStep(currentStep);

    const weekSelect = form.querySelector('[data-school-week]');
    const workingDayInputs = Array.from(form.querySelectorAll('[data-working-days] input'));
    weekSelect?.addEventListener('change', () => {
        const selectedDays = weekSelect.value === 'saturday'
            ? ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday']
            : ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'];

        workingDayInputs.forEach((input) => {
            input.checked = selectedDays.includes(input.value);
        });
    });
}

function initPasswordToggles() {
    document.querySelectorAll('[data-password-toggle]').forEach((button) => {
        const input = document.getElementById(button.getAttribute('aria-controls'));
        if (!input) return;

        button.addEventListener('click', () => {
            const isPassword = input.type === 'password';
            input.type = isPassword ? 'text' : 'password';
            button.textContent = isPassword ? 'Hide' : 'Show';
        });
    });
}

function initLoadingForms() {
    document.querySelectorAll('form[data-loading-text]').forEach((form) => {
        form.addEventListener('submit', () => {
            const button = form.querySelector('[data-submit-button], button[type="submit"]');
            if (!button) return;

            button.disabled = true;
            button.dataset.originalText = button.textContent;
            button.textContent = form.dataset.loadingText;
        });
    });
}

function prefersReducedMotion() {
    return window.matchMedia('(prefers-reduced-motion: reduce)').matches;
}

function canHoverFine() {
    return window.matchMedia('(hover: hover) and (pointer: fine)').matches;
}

// Ensures a mousemove handler updates the DOM at most once per frame.
function rafThrottle(fn) {
    let scheduled = false;
    let lastEvent = null;
    return (event) => {
        lastEvent = event;
        if (scheduled) return;
        scheduled = true;
        requestAnimationFrame(() => {
            scheduled = false;
            fn(lastEvent);
        });
    };
}

function initLandingPage() {
    const nav = document.querySelector('[data-landing-nav]');
    if (nav) {
        const updateNav = () => {
            nav.classList.toggle('border-gray-200', window.scrollY > 8);
            nav.classList.toggle('shadow-sm', window.scrollY > 8);
        };
        updateNav();
        window.addEventListener('scroll', updateNav, { passive: true });
    }

    const mobileToggle = document.querySelector('[data-mobile-menu-toggle]');
    const mobileMenu = document.querySelector('[data-mobile-menu]');
    mobileToggle?.addEventListener('click', () => {
        mobileMenu?.classList.toggle('hidden');
    });

    initTabs('[data-role-tab]', '[data-role-panel]', 'role');
    initTabs('[data-payment-tab]', '[data-payment-panel]', 'payment');

    initHeroEntrance();
    initRevealAnimations();
    initCountUp();

    if (!prefersReducedMotion() && canHoverFine()) {
        initAmbientGlow();
        initCardCursorLight();
        initCardTilt();
        initDashboardParallax();
    }
}

// The hero entrance animation runs once on load and then holds its end state
// via animation-fill-mode: forwards - that's fine for elements nothing else
// touches. But the dashboard preview also gets a JS-driven parallax transform
// afterward, and a forwards-filled animation would permanently block that.
// For that one element, bake the end state into an inline style and drop the
// animation once it finishes, so the later transform can take over cleanly.
function initHeroEntrance() {
    document.querySelectorAll('[data-hero-in][data-parallax-target]').forEach((el) => {
        el.addEventListener('animationend', () => {
            el.style.opacity = '1';
            el.style.animation = 'none';
        }, { once: true });
    });
}

// Scroll-triggered reveal: fades/slides any [data-reveal] element into place
// once it's ~15-25% into the viewport. Runs once per element, then stops.
function initRevealAnimations() {
    const items = document.querySelectorAll('[data-reveal]');
    if (!items.length) return;

    if (prefersReducedMotion()) {
        items.forEach((item) => item.classList.add('is-revealed'));
        return;
    }

    const observer = new IntersectionObserver((entries) => {
        entries.forEach((entry) => {
            if (!entry.isIntersecting) return;
            entry.target.classList.add('is-revealed');
            observer.unobserve(entry.target);
        });
    }, { threshold: 0.18, rootMargin: '0px 0px -8% 0px' });

    items.forEach((item) => observer.observe(item));
}

function initCountUp() {
    if (prefersReducedMotion()) return;

    document.querySelectorAll('[data-count-up]').forEach((item) => {
        const target = Number(item.dataset.countUp || 0);
        if (!target) return;

        let start = null;
        const formatter = new Intl.NumberFormat();
        const tick = (time) => {
            start ??= time;
            const progress = Math.min((time - start) / 800, 1);
            item.textContent = formatter.format(Math.round(target * progress));
            if (progress < 1) requestAnimationFrame(tick);
        };
        requestAnimationFrame(tick);
    });
}

// Large, soft radial glow that drifts toward the cursor within a section.
// Desktop only - CSS transitions supply the "slight delay" smoothing so no
// continuous animation loop is needed.
function initAmbientGlow() {
    document.querySelectorAll('[data-cursor-glow]').forEach((glow) => {
        const section = glow.closest('[data-glow-section]') || glow.parentElement;
        if (!section) return;

        const updatePosition = rafThrottle((event) => {
            const rect = section.getBoundingClientRect();
            const x = ((event.clientX - rect.left) / rect.width) * 100;
            const y = ((event.clientY - rect.top) / rect.height) * 100;
            glow.style.setProperty('--gx', `${x}%`);
            glow.style.setProperty('--gy', `${y}%`);
        });

        section.addEventListener('mouseenter', () => glow.classList.add('is-active'));
        section.addEventListener('mousemove', updatePosition);
        section.addEventListener('mouseleave', () => glow.classList.remove('is-active'));
    });
}

// Soft light that tracks the pointer inside a card, like a subtle reflection.
function initCardCursorLight() {
    document.querySelectorAll('[data-cursor-light]').forEach((card) => {
        const updatePosition = rafThrottle((event) => {
            const rect = card.getBoundingClientRect();
            const x = ((event.clientX - rect.left) / rect.width) * 100;
            const y = ((event.clientY - rect.top) / rect.height) * 100;
            card.style.setProperty('--cx', `${x}%`);
            card.style.setProperty('--cy', `${y}%`);
        });

        card.addEventListener('mouseenter', () => card.classList.add('is-hovering'));
        card.addEventListener('mousemove', updatePosition);
        card.addEventListener('mouseleave', () => card.classList.remove('is-hovering'));
    });
}

// Extremely subtle (max ~2deg) tilt toward the cursor, reserved for a few
// flagship cards (hero dashboard preview, popular pricing plan).
function initCardTilt() {
    const maxTilt = 2;
    const lift = -4;

    document.querySelectorAll('[data-tilt]').forEach((card) => {
        const updateTilt = rafThrottle((event) => {
            const rect = card.getBoundingClientRect();
            const px = (event.clientX - rect.left) / rect.width;
            const py = (event.clientY - rect.top) / rect.height;
            const rotateY = (px - 0.5) * (maxTilt * 2);
            const rotateX = (0.5 - py) * (maxTilt * 2);
            card.style.setProperty('--rx', `${rotateX}deg`);
            card.style.setProperty('--ry', `${rotateY}deg`);
            card.style.setProperty('--lift', `${lift}px`);
        });

        card.addEventListener('mousemove', updateTilt);
        card.addEventListener('mouseleave', () => {
            card.style.setProperty('--rx', '0deg');
            card.style.setProperty('--ry', '0deg');
            card.style.setProperty('--lift', '0px');
        });
    });
}

// Very subtle parallax (a few pixels) for the hero dashboard preview only.
function initDashboardParallax() {
    document.querySelectorAll('[data-parallax]').forEach((wrapper) => {
        const target = wrapper.querySelector('[data-parallax-target]');
        if (!target) return;

        const updateParallax = rafThrottle((event) => {
            const rect = wrapper.getBoundingClientRect();
            const px = (event.clientX - rect.left) / rect.width - 0.5;
            const py = (event.clientY - rect.top) / rect.height - 0.5;
            const x = px * 12;
            const y = py * 8;
            target.style.transform = `translate3d(${x}px, ${y}px, 0)`;
        });

        wrapper.addEventListener('mousemove', updateParallax);
        wrapper.addEventListener('mouseleave', () => {
            target.style.transform = 'translate3d(0, 0, 0)';
        });
    });
}

function initFooterAccordions() {
    const groups = Array.from(document.querySelectorAll('.schoolos-footer-group'));
    if (!groups.length) return;

    const media = window.matchMedia('(max-width: 639px)');
    const sync = () => {
        groups.forEach((group) => {
            group.open = !media.matches;
        });
    };

    sync();
    media.addEventListener?.('change', sync);
}

function initTabs(tabSelector, panelSelector, key) {
    const tabs = Array.from(document.querySelectorAll(tabSelector));
    const panels = Array.from(document.querySelectorAll(panelSelector));
    if (!tabs.length || !panels.length) return;

    tabs.forEach((tab) => {
        tab.addEventListener('click', () => {
            const value = tab.getAttribute(`data-${key}-tab`);
            tabs.forEach((item) => item.dataset.active = String(item === tab));
            panels.forEach((panel) => {
                panel.classList.toggle('hidden', panel.getAttribute(`data-${key}-panel`) !== value);
            });
        });
    });
}
