# 29 - Onboarding & Authentication UI: Progress Status

**Status:** Public onboarding and authentication UI is implemented. Backend foundation, public school signup, Welcome/role-selection, four-step onboarding wizard, Login redesign, Forgot Password, and Reset Password are built and tested.

## Context

This continued from `28-account-creation-onboarding.md` and the first pass recorded in this file. The product target was a complete, green-themed, mobile-and-desktop responsive SchoolOS entry experience:

- Welcome / role selection
- School Information
- Academic Setup
- Modules & Features
- Admin Account
- Login
- Forgot Password / Reset Password where supported by the existing auth stack

The implementation keeps the existing SchoolOS authentication/RBAC architecture. It does not introduce a second auth provider, a second user store, fake authentication, or a separate onboarding identity model.

## Done

### Public School Onboarding

- Added `App\Http\Controllers\Public\SchoolOnboardingController`.
- Registered guest routes:
  - `GET /get-started`
  - `POST /get-started`
- The POST endpoint creates the following in one transaction:
  - `School`
  - starter `Standard` rows from the selected education levels
  - current `AcademicYear`
  - onboarding preferences on `schools`
  - `billing_plan` and `trial_ends_at`
  - first `school_admin` `User`
- The newly created admin is logged in through `AuthenticationService::issueSession()` and redirected to the existing `admin.setup.index` flow.
- Trial length comes from `PlatformSetting::current()->trial_days`.
- The "pay now" path records `billing_plan = paid` with no `trial_ends_at`; no payment collection was added.

### Welcome / Landing Page

- Replaced the stock Laravel welcome page at `/` with a premium SchoolOS landing page.
- The landing page includes sticky navigation, SaaS hero, dashboard preview, capability trust indicators, problem/solution, feature grid, role tabs, attendance showcase, fees/payment showcase, product preview, mobile portal preview, how-it-works, pricing, FAQ, final CTA, and a large footer.
- `Get started` / `Get started free` links continue to `/get-started`.
- `Sign in` links continue to `/login`.
- The page keeps the SchoolOS green + white + neutral visual identity and avoids unsupported product claims.

### Onboarding Wizard UI

- Added `resources/views/public/onboarding/create.blade.php`.
- Built a four-step Blade + Tailwind + vanilla JS wizard:
  1. School Information
  2. Academic Setup
  3. Modules & Features
  4. Admin Account
- Desktop has a persistent progress sidebar.
- Mobile has compact step text, a progress bar, single-column fields, and sticky bottom actions.
- Uses real backend fields only. Attendance configuration is shown as the currently supported assigned-teacher behavior and is not stored as a fake setting.
- Added password show/hide controls and button loading state.

### Authentication UI

- Rebuilt `resources/views/auth/login.blade.php` in the green SchoolOS design system.
- Kept the existing `identifier` login field behavior, so email, mobile number, and registration number still authenticate through `AuthenticationService`.
- Added remember-me support by extending `AuthenticationService::issueSession(User $user, bool $remember = false)` and passing that from `SessionController`.
- Restyled the demo-account quick-fill panel.
- Added password visibility and loading state.

### Forgot / Reset Password

- Added `Auth\ForgotPasswordController` using Laravel's existing password broker.
- Added `Auth\ResetPasswordController` using Laravel's existing password broker.
- Registered routes:
  - `GET /forgot-password`
  - `POST /forgot-password`
  - `GET /reset-password`
  - `POST /reset-password`
- The reset route uses token/email query parameters, matching this app's existing public-route convention.

### Shared UI

- Added `resources/views/components/schoolos-logo.blade.php`.
- Added Tailwind v4 theme token `--color-schoolos-soft: #F4FBF7` in `resources/css/app.css`.
- New public/auth screens use white + green + neutral styling.
- No pink accent references remain in the new public/auth screens.
- Existing authenticated dashboard/portal UI remains indigo and was not re-themed.

## Verification

- `php artisan test tests\Feature\Public\SchoolOnboardingControllerTest.php tests\Feature\PasswordResetControllerTest.php tests\Feature\SessionControllerTest.php` passes.
- `php artisan test` passes: 569 tests, 1712 assertions.
- `npm.cmd run build` passes. PowerShell blocks `npm run build` through `npm.ps1` on this machine, so `npm.cmd` is the working command.
- Local HTTP checks returned 200 for `/`, `/get-started`, `/login`, `/forgot-password`, and `/reset-password?token=test&email=test@example.test`.
- Browser DOM checks before the browser plugin cache disappeared showed no horizontal overflow at mobile/desktop widths and zero computed matches for the old pink accent colors on the new public/auth pages.

## Remaining Notes

- `enabled_modules` storage exists and is written by onboarding, but module-key gating beyond the previously built promotion gate remains deliberately out of scope.
- `library` and `inventory` remain stored/displayed module choices only; there is no library or inventory domain in SchoolOS yet.
- The local SQLite database needed `php artisan migrate --force` before `/get-started` could render outside the test database because the new platform settings table had not been applied locally.
