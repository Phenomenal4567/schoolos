# 02 — Authentication Map (GegoK12, verified from local source)

**Status:** Verified against actual files (not upstream docs). Sources: `auth/LoginController.php`, `auth/User.php`, `routes/web.php`, `role-search.txt` grep dump, migrations.

---

## 1. Web authentication (session-based)

- `App\Http\Controllers\Auth\LoginController` uses the standard Laravel `AuthenticatesUsers` trait (a **custom app trait at `App\Traits\AuthenticatesUsers`**, not the framework default — confirmed by the `use App\Traits\AuthenticatesUsers;` import). This is important: GegoK12 has overridden the default login behavior, so assumptions from stock Laravel don't hold.
- `$redirectTo = '/admin/dashboard'` — every non-stock-keeper role redirects to the same URL after login; role-specific views are then resolved downstream (dashboard controller / middleware), not by the login controller itself.
- The controller's own docblock says usergroup 12 (Stock Keeper) is special-cased to `/stock/dashboard`, but that branching isn't in `LoginController` itself — it must live in the `AuthenticatesUsers` trait's `authenticated()` hook or in middleware. **Not yet confirmed — need `app/Traits/AuthenticatesUsers.php` to verify.**
- `role-search.txt` confirms the trait does contain school-scoping logic: `app/Traits/AuthenticatesUsers.php:71` — *"SuperAdmins (usergroup_id == 1) bypass school checks"* (line 86: `if ($users->usergroup_id == 1)`). This is a real, explicit tenant-isolation bypass for usergroup 1, worth flagging (see security findings below).
- `Auth::routes()` in `web.php` wires up the full standard Laravel auth route set (login, register, password reset) unmodified.

## 2. API / mobile authentication — NOT unified

This is the most important finding: **GegoK12 does not have one API authentication flow — it has at least three separate, hand-rolled, credential-specific login endpoints**, all doing raw `Auth::attempt()` or manual `User::where()` lookups rather than a shared service:

| Endpoint / file | Credential shape | Notes |
|---|---|---|
| `app/Http/Controllers/Api/Teacher/LoginController.php:43` | `mobile_no` + password, hardcoded `usergroup_id=5` | `Auth::attempt(['mobile_no' => ..., 'password' => ..., 'usergroup_id' => 5])` |
| `app/Http/Controllers/Api/TokenController.php:52` | `mobile_no` + password, hardcoded `usergroup_id=7` | Parent-specific token endpoint |
| `app/Http/Controllers/Api/LoginController.php:99` | `mobile_no` + `device_id != null` + `usergroup_id=7` | A **third**, differently-shaped parent login path in the same codebase |
| `app/Http/Requests/LoginRequest.php:30` | `mobile_no`, hardcoded `usergroup_id=7` | Form-request-level lookup, parent-only |
| `app/Http/Requests/TeacherLoginRequest.php:30` | `mobile_no`, hardcoded `usergroup_id=5` | Form-request-level lookup, teacher-only, parallel to the one above |
| `app/Http/Requests/API/OTPRequest.php:31` | `mobile_no` + `usergroup` (request param, not hardcoded) | OTP flow takes usergroup as **client-supplied input** — worth checking whether it's validated against the authenticated principal or trusted blindly |

**Consequence for SchoolOS:** there is no single "authenticate a user" code path in GegoK12 — parent and teacher auth are duplicated (at least twice each) with slightly different credential rules, and the usergroup is sometimes hardcoded server-side (safe) and sometimes taken from request input (`OTPRequest`, `UserController.php` — needs scrutiny). SchoolOS should have exactly one authentication service that both web and API/mobile call into, with usergroup/role never trusted from client input.

## 3. Impersonation

`app/Http/Controllers/Auth/ImpersonateController.php` (via `Nckg\Impersonate\Traits\CanImpersonate` on `User`) lets school admins impersonate teachers/students, and superadmins impersonate school admins (`web.php` lines 19–24, gated by `auth`+`schooladmin` / `auth`+`superadmin` middleware). The controller branches on raw `usergroup_id` values (5, 3, 6, 8 seen in the grep) rather than named constants — same "magic number" pattern as everywhere else. Functionally reasonable, but needs an explicit audit trail requirement in SchoolOS (Section 42 of the handoff already calls for this).

## 4. School/tenant context in auth

- `users.school_id` is a required, non-nullable foreign key (confirmed in `2020_02_18_000000_create_users_table.php` pattern and `User::school()` relation).
- **Confirmed bypass:** superadmin (`usergroup_id == 1`) explicitly skips school-scoping in the login trait. This is a legitimate design choice for a platform-level superadmin *if* deliberate and tightly scoped — but it means tenant isolation in GegoK12 is enforced by **role-based bypass logic scattered in the auth trait**, not by a structural boundary (e.g., a global query scope). One missed `usergroup_id == 1` check elsewhere = one cross-tenant leak.

## 5. `app/Traits/AuthenticatesUsers.php` — full review (RESOLVED, with a new bug found)

### 5a. Flexible login field, but validators don't agree on it
`username()` inspects the submitted `email` field and decides at runtime whether the user is logging in with an email address or a `registration_number`, then merges the result back into the request under the resolved field name. So far so good — but the four custom validators built around this don't all respect that resolution:

| Validator | Field it actually queries by | Consistent with `username()`? |
|---|---|---|
| `checkschool` | `orWhere` across **email, mobile_no, name, registration_number** | Broader than needed, and see 5c |
| `checkusers` | hardcoded `'email'` | **No** — never used in the final `validate()` call anyway (dead code, see 5b) |
| `checkactive` | hardcoded `'email'` | **No** |
| `checkexit` | hardcoded `'email'` | **No** |

**Confirmed bug:** when a user logs in via `registration_number` (the whole reason `username()` exists), `checkactive` and `checkexit` still do `User::where('email', request('email'))->with('userprofile')->first()`. Since `request('email')` in that case holds a registration number, not an email, this lookup returns `null` for essentially any registration-number-based login. The very next line calls `$users->userprofile->status` on that `null` — a fatal error, not a clean "invalid credentials" response. **Registration-number login is very likely broken today**, or only "works" by accident if the registration number happens to also be stored/matched somewhere as an email. This needs a live test to confirm, but the code as written should crash.

### 5b. `checkusers` is registered but never applied
`Validator::extend('checkusers', ...)` is defined but the actual `$this->validate($request, [...])` call at the bottom only wires up `checkactive|checkexit` on the username field and `checkschool` on password. `checkusers` is dead code — harmless, but worth noting as another sign this trait has drifted from a cleaner original design.

### 5c. `checkschool`'s broad `orWhere` is a second latent bug
```php
$users = User::orWhere('email', request('email'))
    ->orWhere('mobile_no', request('email'))
    ->orWhere('name', request('email'))
    ->orWhere('registration_number', request('email'))
    ->first();
```
This matches on **name** as one of the OR branches — `name` is not a unique/credential-like field. If two users at different schools happen to share a name (or a name collides with another user's email/mobile/reg-number string), this can silently resolve to the *wrong* `$users` record, and the school-active check then runs against the wrong school. Combined with 5a's null-pointer risk (if literally nothing matches, `$users` is `null` and `$users->usergroup_id` fatals here too), this validator is the weakest link in the login flow.

**Net assessment:** the docblocks in this trait ("Validates that the user exists," "Validates school is active") describe intent that the implementation doesn't fully deliver — field-matching is inconsistent across the four validators, one is unused, and at least one crash path is real. SchoolOS should use one canonical user-lookup (by whichever identifier was actually resolved) shared by every validator, and should return a normal validation failure — never a fatal error — when no user is found.

### 5d. Post-login redirect logic — RESOLVED (fully, against real `app/` source)
`RedirectIfAuthenticated` (the `'guest'` middleware) is confirmed: a small, standard override that just redirects an already-logged-in user hitting `/login` back to `/admin/dashboard` — no role branching here either.

**The stock-keeper redirect described in `LoginController`'s docblock does not exist anywhere in the codebase. Confirmed dead/never-shipped, not merely undocumented:**
- `Auth\LoginController::$redirectTo` is the literal hardcoded string `/admin/dashboard` — no conditional logic.
- `AuthenticatesUsers::authenticated()` — the one hook Laravel gives this trait specifically for post-login role branching — is an empty stub (`protected function authenticated(Request $request, $user) { // }`).
- `RedirectIfAuthenticated` unconditionally sends every authenticated user hitting `/login` to `/admin/dashboard`, regardless of `usergroup_id`.
- `App\Http\Controllers\Admin\DashboardController@index` was reviewed in full: no stock-keeper branch, no role-based redirect of any kind — it clears three caches, builds the admin dashboard payload, and returns a view. Nothing in it explains the docblock's claim.
- There is no `Stock/DashboardController.php` anywhere under `app/Http/Controllers` — every other role has one (`Admin`, `Student`, `Teacher`, `Accountant`, `Receptionist`, `Librarian`, `Api/Teacher`), Stock does not.
- This lines up with two things already known: `routes/stock.php` has 0 lines (`04-route-map.md` §1), and `MustBeStockKeeper` middleware is the *only* stock-related file in the entire `app/` tree — it's wired up but has nothing behind it to gate.

**Conclusion:** Stock Keeper is a partially-scaffolded role (usergroup constant exists, middleware alias exists) with no actual dashboard, no route file content, and no redirect logic — not a case of "the redirect exists somewhere we haven't looked yet." The docblock in `LoginController` describes a feature that was never built, not one that rotted.

**SchoolOS implication:** if SchoolOS keeps a Stock Keeper role at all, treat it as needing to be built from scratch — there's no GegoK12 implementation to reference for this role's actual workflow, only its authorization scaffolding.

### 5e. `MustBePrivilege` — misnamed; it's an onboarding gate, not authorization
Reviewed. This middleware (aliased `'privilegeconditions'`) has nothing to do with roles or permissions — it checks whether the school has an active academic year and at least one `Standard` (class/grade level) configured, and redirects to setup screens if not (`/admin/academics` or `/admin/standard/create`). It's onboarding-completeness enforcement disguised as a privilege check by its name and alias. Minor code smell: there's an `abort(404);` after the `if/else` block that is **unreachable dead code** (every branch above it already returns). Harmless, but another small sign of drift/incomplete cleanup.

**SchoolOS implication:** "has this tenant finished onboarding" is a legitimately different concern from "is this user authorized" and should be its own explicit, separately-named middleware/gate — not filed under a privilege/permission-sounding name.

## 6. `app/Http/Kernel.php` — full review (RESOLVED)

- **Session middleware is global, not scoped to `web`.** `StartSession` sits in the top-level `$middleware` stack (runs on *every* request, API included), while the `web` group has it commented out (`// \Illuminate\Session\Middleware\StartSession::class`). This is what actually allows Sanctum's `EnsureFrontendRequestsAreStateful` (in the `api` group) to work — session state is available to API requests too. Functionally coherent, but easy to miss: a new engineer reading only the `web` group would wrongly conclude sessions aren't available there.
- **Impersonation middleware (`Nckg\Impersonate\Impersonate::class`) is also global** — runs on every request, not just the impersonation routes. Likely intentional (it probably checks a session flag and no-ops otherwise), but worth confirming it's cheap, since it's now unconditional per-request overhead.
- **13 separate, near-identical role middleware classes** (`MustBeSchoolAdmin`, `MustBeSchoolSubAdmin`, `MustBeTeacher`, `MustBeLibrarian`, `MustBeStudent`, `MustBeParent`, `MustBeReceptionist`, `MustBeAccountant`, `MustBeStockKeeper`, `MustBeAlumni`, plus `MustBeSiteAdmin`/`MustBeSiteSubAdmin`) exist side-by-side with Laratrust's generic, parameterized `role` middleware (`Laratrust\Middleware\Role::class`, aliased as `'role'`). This is a duplicate mechanism problem exactly like the model-layer one in `03-role-permission-map.md`: the app could have used `role:teacher` everywhere via Laratrust, but instead hand-wrote a dedicated middleware class per `usergroup_id`. SchoolOS should use exactly one role-gating middleware, parameterized by role name.
- `'guest'` is mapped to a **custom** `RedirectIfAuthenticated` (not the framework default) — worth a quick look at what it does differently, not yet reviewed.
- `'privilegeconditions' => MustBePrivilege::class` — doc comment says *"checks academic year and standards"*; this is authorization logic entangled with academic-calendar state, an unusual mix worth understanding before SchoolOS designs its own middleware (not yet reviewed).

## 7. Open items — still need more source

- ~~`DashboardController@index`~~ — **RESOLVED**, see 5d. No stock-keeper branching exists anywhere.
- ~~`App\Http\Middleware\RedirectIfAuthenticated`~~ — **RESOLVED**, see 5d.
- `MustBePrivilege` — still not reviewed against real source (grep-only so far).
- Sanctum config (`config/sanctum.php`) — still not reviewed; not present in the `app/`-only source pack received, would need `config/` specifically.
- New from this pass: `App\Traits\ResetPasswordProcess` and `App\Traits\AuthenticationProcess` (used by `Api\UserController`, referenced in F4's resolution in `11-security-findings.md`) — not yet reviewed; would confirm exactly how/whether OTP SMS dispatch is rate-limited.
