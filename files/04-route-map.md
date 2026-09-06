# 04 — Route Map (GegoK12, verified from local source)

**Status:** Verified against `app/Providers/RouteServiceProvider.php` (the actual middleware/namespace/prefix wiring) plus the route files themselves. This resolves the open question from earlier passes: none of the route files carry their own middleware — it's all applied externally, per-file, in this one provider.

---

## 1. Full route-group table

| Route file | URL prefix | Middleware | Controller namespace | Lines |
|---|---|---|---|---|
| `web.php` | *(none)* | `web` | `App\Http\Controllers` | 60 |
| `api.php` (+ `@include`s `teacherapi.php`) | `/api` | `api` | `App\Http\Controllers` | 277 + 366 |
| `admin.php` (+ `include`s `addon.php`) | `/admin` | `web, auth, schooladmin, privilegeconditions` | `App\Http\Controllers\Admin` | 879 + 2 |
| `setting.php` | `/admin` | `web, auth, schooladmin` | `App\Http\Controllers\Admin` | 42 |
| `inventory.php` | `/admin` | `web, auth, schooladmin, privilegeconditions` | `App\Http\Controllers\Staff` | 2 |
| `stock.php` | `/stock` | `web, auth, stockkeeper` | `App\Http\Controllers\Stock` | 0 |
| `librarian.php` | `/library` | `web, auth, librarian` | `App\Http\Controllers\Librarian` | 79 |
| `student.php` | `/student` | `web, auth, student` | `App\Http\Controllers\Student` | 136 |
| `teacher.php` | `/teacher` | `web, auth, teacher` | `App\Http\Controllers\Teacher` | 441 |
| `receptionist.php` | `/receptionist` | `web, auth, receptionist` | `App\Http\Controllers\Receptionist` | 192 |
| `accountant.php` | `/accountant` | `web, auth, accountant` | `App\Http\Controllers\Accountant` | 90 |
| `payroll.php` | `/accountant` **(same prefix as above)** | `web, auth, adminaccountant` | `App\Http\Controllers\Payroll` | 72 |
| `superadmin.php` | `/superadmin` | `web, auth, superadmin` | `App\Http\Controllers\Superadmin` | 21 |
| — parent — | **none — no route file, no map method** | — | — | — |
| `channels.php`, `console.php` | framework-standard, not part of `map()` | — | — | 23 / 18 |

## 2. Finding: there is no parent-facing web route group — confirmed at the source

The handoff's Section 0 flagged this as an observation; `RouteServiceProvider::map()` confirms it structurally. Every other role has a dedicated `map*Routes()` method (`mapStudentRoutes`, `mapTeacherRoutes`, `mapLibrarianRoutes`, etc.) wiring up a prefix, middleware stack, and controller namespace. **There is no `mapParentRoutes()`.** No `/parent` prefix, no `App\Http\Controllers\Parent` namespace, nothing.

Yet `Kernel.php` defines a `'parent' => \App\Http\Middleware\MustBeParent::class` route middleware alias that is never referenced by anything in this provider. That middleware alias is either:
- dead code (defined, wired to nothing), or
- used ad-hoc on individual routes somewhere outside this provider (possible but not yet seen), or
- a genuine gap — parents were meant to get a web dashboard and it was never finished.

Combined with what we already know — parent authentication is API/mobile-only (`TokenController`, `Api/LoginController`, both hardcode `usergroup_id=7`, both are API-namespaced) — the most likely explanation is that **parents are intentionally mobile-app-only and were never meant to have a web dashboard**, which is a legitimate product decision, not necessarily a bug. But it's worth explicit confirmation before SchoolOS assumes parent-web-access is a requirement to replicate.

**SchoolOS implication:** decide explicitly, up front, whether parents need a web dashboard — don't let it be an accidental omission the way it may have been here.

## 3. Finding: silent-failure include for `teacherapi.php`

`routes/api.php` line 3: `@include('teacherapi.php');`

The `@` here is PHP's error-suppression operator applied to the `include()` call. Contrast with `admin.php`'s plain `include('addon.php')` (no suppression) — which is *why* the missing `AddonInstallExamController` class produced a loud, repeated, logged fatal error (finding F1). If `teacherapi.php` — 366 lines, the single largest route file after `admin.php` and `teacher.php` — ever develops the same kind of missing-class problem, the `@` would suppress the warning/error output for the include itself (though a `ReflectionException` from route resolution would likely still surface at request time, same as F1's case — the suppression mainly hides *file-not-found*-class issues at include-time, not route-dispatch-time errors). Still, using error suppression around a routes file is a fragile pattern: it can mask a missing-file problem (e.g. a rename or move) that would otherwise fail loudly and immediately at boot.

## 4. Finding: `/accountant` prefix is shared by two unrelated route files with different middleware

`mapAccountantRoutes()` and `mapPayrollRoutes()` both register under `Route::prefix('accountant')`, but:
- `accountant.php` requires the `accountant` middleware (→ `MustBeAccountant`, presumably `usergroup_id == 11`)
- `payroll.php` requires `adminaccountant` middleware instead (a *different* middleware class, `AdminAccountant.php` — already seen in the earlier grep checking `Auth::user()->usergroup_id` directly)

Two different authorization rules protecting URLs under the same prefix is exactly the kind of thing that's easy to misroute during maintenance — a new payroll route added by copy-pasting an accountant route (or vice versa) could accidentally end up gated by the wrong middleware, and nothing about the URL shape would warn a developer. **SchoolOS implication:** one prefix segment should map to one, unambiguous authorization boundary.

## 5. Finding: `privilegeconditions` (the onboarding gate) is on `admin` and `inventory`, not on other role dashboards

`admin.php` and `inventory.php` both require `privilegeconditions` (the `MustBePrivilege` onboarding-completeness check reviewed last pass — academic year + at least one `Standard` configured) in addition to `schooladmin`. No other role's route group has this middleware. That's plausibly correct — only school admins actually manage academic-year/standard setup — but it does mean a school that hasn't finished onboarding could still have teachers/students/etc. hitting fully-functional dashboards that assume that setup exists, since only the *admin* routes are gated on it. **Not yet confirmed as a bug** — would need to see what a teacher/student dashboard controller does when academic year data doesn't exist yet.

## 6. Confirms earlier finding: `verifyotp` middleware is present in code but commented out

Both `mapAdminRoutes()` and `mapInventoryRoutes()` have `//, 'verifyotp'` commented out alongside the real middleware list. An OTP-verification step for admin/inventory access was apparently built (`MustBeOTP` exists in `Kernel.php`) and then disabled, not removed. Worth noting for SchoolOS: if OTP-gating admin actions was a deliberate security control, its removal (even if temporary/for testing) should be understood before deciding whether SchoolOS needs an equivalent.

---

**Open items:**
- Confirm whether `'parent'` middleware (`MustBeParent`) is referenced anywhere outside this provider (a manual `Route::middleware('parent')->group(...)` elsewhere, or truly dead).
- `Standard.php` model (used by `MustBePrivilege`) not yet reviewed.
- Still open from `02-authentication-map.md`: `DashboardController@index`, to resolve the stock-keeper redirect question.
