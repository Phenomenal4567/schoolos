# 28 — Account Creation & Onboarding System

**Status:** Built and tested 2026-09-05. Full suite: 556/556 passing, 1666 assertions (baseline before this work: 490/1496).

## Context

SchoolOS already had a complete single-identity auth system (`AuthenticationService`, one `SessionController` for every role), real RBAC (`Role`/`Permission`, `role:` middleware, `ScopeService::tenantScope()`/`relationshipScope()`), and working per-role portals (school_admin, teacher, parent, student) plus admin-created staff/parent accounts and a full public-admission-to-enrollment pipeline. What was missing was the onboarding layer on top: invitation/activation instead of an admin typing someone else's password, a guided first-run setup wizard, a safe self-service path for parents, and two closed gaps (no admin-direct "register a new student" action, no generic staff-role portal). This system adds exactly that layer — no auth/RBAC/domain rebuild, no new class/subject/attendance/timetable/fee logic.

## What already existed (verified before building anything)

- `User` — one identity table for every role, `role_id` FK, `school_id` nullable only for `super_admin`. `StaffProfile`/`StudentEnrollment`/`StudentParentLink` are the existing role-specific profile/relationship tables, reused as-is.
- `AuthenticationService::authenticate()`/`issueSession()` and (formerly) `SessionController::dashboardPathFor()` — identifier-agnostic login + per-role post-login routing.
- `Admin\StaffController::store()` and `Admin\ParentEnrollmentController::store()` — admin already creates staff/parents, by typing a password directly. No invitation existed anywhere (confirmed by repo-wide search).
- `Public\AdmissionApplicationController` — the exact pattern for a guest, school-scoped public page: `guest` middleware, `?school=` query string (not a route parameter) resolved server-side by `short_code`, deliberately exempt from the route-table's scope-checked lint since it has no authenticated actor to scope with. Every new guest page here copies that pattern.
- `AdmissionApplicationRepository::accept()` — already creates a brand-new student `User` row with an unusable `Str::random(32)` password. "Student record without a usable login" already existed, just unlabeled.
- `ParentLinkController`/`ParentLinkRepository::link()` — explicitly documented as "admin-initiated, not self-service... deliberately no parent-facing self-linking route anywhere in this codebase." This constraint shaped the parent self-service design below.
- Enum columns are widened in place elsewhere via `$table->enum(...)->change()`, proven to work on both the SQLite test connection and MySQL with no doctrine/dbal dependency — reused for every new enum value here.

## Decisions

1. **Multi-role-per-user: out of scope.** `users.role_id` stays a single FK. A switcher would mean a schema change rippling through `ScopeService`, every `role:` middleware check, and `User::role()`'s singular relation — an RBAC redesign, not onboarding.
2. **Parent self-service never creates an active link.** Self-registration creates only the parent `User` row. "Identify a child" creates a request (`student_parent_links.status = 'pending'`) via a new `ParentLinkRepository::request()`. Only an admin approval action calls the existing `link()` to promote it to `'active'` — that method's "admin-initiated only" contract stays literally true.
3. **Staff Portal is a minimal generic landing page**, not new accountant/librarian feature domains — those don't exist anywhere in SchoolOS yet and are out of scope for an onboarding task.
4. **The setup wizard never gates the dashboard** — a guided sequence of links into *existing* admin screens, with a "finish" step that flips a status flag. Admin can always jump straight to `/admin/dashboard`.
5. **Invitations are DB-row-backed** (hashed token + status + expiry), not a stateless encrypted token — single-use and revocation both need persisted state.

## New: Invitation mechanism (shared by teacher/staff/parent/student/school-admin)

- `invitations` table: `school_id`, `user_id` (the already-created pending account), `role_id` (display only), `token_hash` (unique, `hash('sha256', $plainToken)`), `status` enum(`pending`,`accepted`,`revoked`), `expires_at`, `accepted_at`, `invited_by`.
- `App\Models\Invitation`.
- `App\Repositories\InvitationRepository`:
  - `issue(User $forUser, User $actor, int $ttlDays = 7)` — generates a 64-char token, persists only its hash, sets the user to `status = 'invited'`, dispatches `InvitationNotification`.
  - `accept(string $plainToken, string $password)` — hashes input, looks up with `lockForUpdate()` inside a transaction (same TOCTOU pattern as `AttendanceRepository::mark()`), rejects on wrong/used/revoked token or expiry, sets the real password + `status = 'active'`.
  - `revoke(Invitation $invitation, User $actor)`.
- `App\Notifications\InvitationNotification` — first `mail`-channel notification in the app (every other one is `database`-channel/in-app, which can't reach someone with no session yet). Logs under `MAIL_MAILER=log` in this environment, same as everything else.
- Guest routes mirroring `Public\AdmissionApplicationController` exactly: `GET/POST /invitations/accept?token=...` → `Public\InvitationController`.
- Existing admin-facing creators (`Admin\StaffController`, `Admin\ParentEnrollmentController`, `SuperAdmin\SchoolAdminController`) gained an `invite` boolean — additive, not a replacement. When true, `password` becomes optional (`Rule::requiredIf(!$invite)` — not `required_if:invite,false`, which silently never fires when `invite` is simply absent from the request; this was caught and fixed during implementation), the account is created with `status = 'invited'`, and `InvitationRepository::issue()` fires instead.

## 1. School Admin onboarding

- `schools.setup_status` enum(`pending`,`in_progress`,`complete`) + `setup_step`.
- `Admin\SetupWizardController` (`index`/`advance`/`complete`) — thin: each of the 6 steps links into the *existing* admin dashboard sections (School Information → `SchoolProfileController`; Academic Configuration/Subjects/Staff/Parents & Students → anchored sections of the existing all-in-one `admin/dashboard.blade.php`, which already contained every CRUD form). No new CRUD was built for classes/subjects/staff/students — confirmed already fully school-defined (no hardcoded Nursery/Primary/JSS anywhere).
- `resources/views/admin/setup/index.blade.php` — progress bar + 6-step checklist, steps completable out of order, never a gate.
- `Admin\DashboardController` gained a dismissible "Finish setting up your school" banner when `setup_status !== 'complete'`.

## 2. Teacher onboarding

Rides entirely on the shared invitation mechanism (`Admin\StaffController::store(role: 'teacher', invite: true)`). Accept flow lands in the existing `TeacherPortal\ProfileController` screens — no new profile fields. Subject-attendance authorization was verified already `teacher_id`-only, not class-membership (built in an earlier session) — confirmed, not changed.

## 3. Staff onboarding

- `Admin\StaffController`'s allowed-role list is now sourced from `StaffAttendanceRepository::STAFF_ROLE_KEYS` (previously a separately hand-maintained array that had drifted into agreement by luck, not design).
- New `StaffPortal\DashboardController` + `/staff/dashboard`, gated to `accountant,librarian,receptionist,staff` (not teacher/school_admin) — shows `StaffProfile` completion status (read-only) and the existing staff-attendance self-check-in action, which previously had no reachable page for a non-teacher staff role at all.
- `AuthenticationService::dashboardPathFor()` gained the one new branch these four roles needed (previously fell through to `/`).

## 4. Parent onboarding

- `Public\ParentRegistrationController` (`GET/POST /register/parent?school=...`) — same short_code pattern as admissions. Creates the parent `User` row only; never links to any student.
- `ParentPortal\ChildLinkRequestController::store()` — authenticated parent identifies a child by `student_id` (the ID-card identifier, not the login-only `registration_number`) plus a name match, both checked within the parent's own `school_id`. Calls the new `ParentLinkRepository::request()`, creating a `'pending'` row.
- `Admin\ParentLinkController::approve()`/`reject()` — `approve()` calls the existing `link()` (the only thing that ever writes `'active'`); `reject()` deletes the pending row outright (nothing worth preserving, unlike a real unlink).
- `ParentPortal\DashboardController` needed no change to hide pending rows — its existing query already only surfaces active links.

## 5. Student onboarding

- `StudentRepository::register()` — mirrors `AdmissionApplicationRepository::accept()`'s create-User-then-enroll transaction (reusing `EnrollmentRepository::enroll()`, not duplicating its checks), plus optional immediate parent linking. Deliberately sets `status = 'invited'` (not `'active'`, unlike the untouched admission path) — new code with no backward-compatibility constraint, so it uses the honest label from the start.
- `Admin\StudentController::store()` — direct registration, bypassing the public admission form.
- `Admin\StudentController::invite()` — the one new capability the record-vs-login-account distinction needed: issues an `Invitation` against an already-registered student.

## Cross-cutting security

- No new controller accepts `role`/`role_id` from request input beyond a fixed server-side allow-list.
- Every new single-record-by-id route carries `scope.checked`; every new guest page uses the query-string-token pattern so it's honestly exempt, not falsely marked.
- `InvitationRepository::accept()`'s `lockForUpdate()` closes the same-token-two-concurrent-accepts race.
- **Found and fixed a real pre-existing gap while implementing this:** `AuthenticationService::authenticate()` checked school-level suspension but never the user's own `status` column at all — an `'inactive'`/`'exited'` account with a known password could still log in. Added `AccountDisabledFailure` (inactive/exited) and `AccountNotActivatedFailure` (invited — a distinct, more useful message than a generic wrong-password error).

## Migrations added

1. `add_setup_status_to_schools_table`
2. `create_invitations_table`
3. `add_invited_to_users_status_enum`
4. `add_pending_to_student_parent_links_status_enum`

## Tests added/extended

`InvitationRepositoryTest`, `Public\InvitationControllerTest`, `Admin\SetupWizardControllerTest`, `StaffPortal\DashboardControllerTest`, `Public\ParentRegistrationControllerTest`, `ParentPortal\ChildLinkRequestControllerTest`, `Admin\StudentControllerTest`; extended `Admin\AccountCreationControllerTest` (invite paths), `SuperAdmin\SchoolAdminControllerTest` (new file), `Admin\ParentLinkControllerTest` (approve/reject), `AuthenticationServiceTest`/`SessionControllerTest` (disabled/invited account rejection). Explicit coverage of: cross-school rejection on every new endpoint, invitation expiry/reuse/revocation, forged `role`/`school_id` request fields having no effect, disabled-account login rejection. Full regression suite verified green throughout, not just at the end.

## Known gaps (by design, not oversights)

- **Multi-role-per-user** — `users.role_id` stays single-valued; a real switcher is an RBAC redesign out of this task's scope.
- **Rich per-staff-role feature portals** — an accountant's finance view, a librarian's catalog — don't exist anywhere in SchoolOS yet; the Staff Portal is deliberately generic rather than inventing new product surface under "onboarding."
- **Admission-created students still default to `status = 'active'`** (with an unusable password) rather than `'invited'` — left untouched to avoid changing existing, tested behavior for a label with no functional effect on login either way. Only the new direct-registration path (`StudentRepository::register()`) uses the honest `'invited'` label.
