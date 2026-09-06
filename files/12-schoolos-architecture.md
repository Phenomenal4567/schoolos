# 12 — SchoolOS Architecture (synthesized from GegoK12 audit, F1–F22)

**Status:** Draft. Built directly from confirmed findings in `02`–`10` and `11-security-findings.md`. Every design decision below cites the specific GegoK12 finding it responds to — nothing here is speculative "best practice," it's a reaction to something actually observed. §3a (Scope enforcement) is currently the most evidence-backed section, resting on four independently-confirmed authorization defects (F18, F20, F21, F22) spanning teacher-side, parent/student-side, and both read and write paths, that share the same root cause and — per F21/F22 — the same failure mode even at different severity tiers.

---

## 1. Core principle carried over unchanged

GegoK12 got the tenant boundary right at the schema level (§1 of `05-database-map.md`): every domain table has a `school_id` FK. **Keep this.** What failed was *enforcement* — a scattered, per-call-site superadmin bypass (F3) instead of a single structural mechanism. SchoolOS's schema should look similar to GegoK12's; its query layer should not.

## 2. Authentication — one service, not five

**What GegoK12 does (confirmed):** at least 3 separate, differently-shaped API login endpoints for parent/teacher (`02-authentication-map.md` §2), plus a web login trait with 4 validators that don't even agree on which field to query by (F5, F6). Registration-number login is very likely a hard crash in production right now.

**SchoolOS design:**
```
AuthenticationService
  ├── resolveIdentifier(input) -> {type: email|registration_number|mobile, value}
  ├── authenticate(identifier, credential) -> User | AuthFailure
  └── issueSession(User) -> WebSession | ApiToken
```
- One resolution function decides the credential type; every subsequent check (active/exited/school-status) uses *that* resolved identifier — never a hardcoded field, unlike F5/F6.
- Web and mobile/API both call the same `authenticate()` — no parallel Api\LoginController, TokenController, and form-request-level lookups doing three different things for the same role.
- "User not found" is always a typed failure result, never a null-dereference (F5's direct fix).
- Role/usergroup is **never** accepted as client input during authentication (contra the `OTPRequest` pattern flagged as unconfirmed-severity in F4) — it's looked up from the resolved identity, full stop.

**Implementation update:** `super_admin` now has its own post-login destination (`/super-admin/dashboard`) rather than falling through to the public home page. This is presentation routing only; authorization still comes from the `role:super_admin` middleware and the scope model below.

## 3. Authorization — collapse five mechanisms into two concepts

**What GegoK12 does (confirmed):** `usergroup_id` (System A, load-bearing, checked by hand everywhere) + Laratrust roles (System B, used for ~4 teacher-only sub-privileges) + `permission_user` (System C, wired up, no confirmed usage) + 13 hand-written `MustBe*` middleware classes duplicating Laratrust's generic `role` middleware (F8) + unenforced type constraints on specialized `User` subclasses, where the class name promises filtering the code doesn't deliver (F9).

**SchoolOS design:**
- **Role** — a real enum/lookup table (not a bare int with hand-maintained constants), answers "what can this user do." Replaces `usergroup_id` and absorbs the handful of genuinely-useful Laratrust sub-privileges (`principal`, `leave_checker`, etc.) as permissions *on* a role, not a second role system bolted beside it.
- **Scope** — answers "which records can they act on" (school, assigned classes, own children). This is where GegoK12's scattered `school_id` checks, parent-child link checks, and teacher-assignment checks all consolidate.
- **One middleware**, parameterized by role/permission (`role:teacher`, `permission:approve-leave`) — not 13 near-identical classes.

### 3a. Scope enforcement — concrete design, backed by F18 and F20

This is the single most evidence-backed recommendation in this document. GegoK12's authorization *layer exists* (Gates, Policies, per-endpoint ownership checks) — the recurring defect is that it stops one level too shallow: it verifies tenant membership and then treats that as sufficient, when the record being accessed needs a narrower check.

**What was found, concretely:**
- **F18 (record-level scope, teacher side):** every teacher-facing Gate (`member`, `academic`, `homework`, `document`) reduces to `$user->school_id == $target->school_id`. `class_teacher_links` — the real, populated, correctly-used-elsewhere assignment table (teacher↔class↔subject↔year) — is never consulted at the authorization boundary. Result: any teacher can read any student's full record, and act on any homework/assignment, school-wide, including inside the `Approval/*` controllers meant to gate teacher-on-teacher actions.
- **F20 (record-level scope, parent/student side):** `Api\AttendanceController::index($student_id)` scopes by `school_id` only and never checks `$student_id` against the caller's own `student_parent_links` rows. Any parent or student can pull any other student's attendance in the same school. The fix isn't hypothetical — `Api\ChildrenController::showChildren()`, in the same controller directory, already does this correctly: it intersects the requested ID against the caller's own relationship data before querying anything.

**The pattern underneath both:** tenant scope (`school_id` match) and relationship/assignment scope (does this specific user↔record edge exist) are two different checks, and GegoK12 only reliably performs the first. SchoolOS must make the second one structurally impossible to skip, not just conventionally expected.

**Update from the API-map pass (F21, F22): there's a third, worse tier.** F18/F20 at least perform tenant scoping and stop there. The API map found endpoints — `TeacherLeaveApplication`'s entire read/write/approve surface (F21), and `Api\Teacher\AssignmentController`/`HomeworkController`'s `show`/`update`/`edit` (F22) — that perform **no scoping check at all**, not even tenant. F22 is especially telling: `HomeworkController::destroy` on the exact same resource correctly calls `Gate::allows('homework', ...)`, proving the check was known to belong there and simply wasn't applied to its sibling methods. This means the defect isn't "the wrong check is used" — it's that per-method manual application is unreliable even for the developers' own known-correct pattern. That's the strongest argument in this document for §3a's mandatory-not-optional framing: a check that has to be remembered per method will eventually be skipped on one of them, regardless of whether the check itself is well-designed.

**Severity tiers observed, low to high:**
1. **Correct** — `ChildrenController::showChildren()`, `Api\Teacher\AttendanceController::index()` (`class_teacher_id = Auth::id()`): tenant + relationship both enforced.
2. **Tenant-only** — F18 (teacher Gates), F20 (attendance IDOR): school match enforced, relationship match silently absent.
3. **Unscoped** — F21 (`TeacherLeaveApplication`), F22 (`Assignment`/`Homework` show/update): no check of any kind, including cross-tenant.

SchoolOS's `ScopeService` (below) is designed to make tier 3 structurally unreachable — if `tenantScope` and `relationshipScope` are enforced by a shared mechanism rather than per-controller code, "someone forgot to add any check" stops being a possible failure mode.

**Design:**
```
ScopeService
  ├── tenantScope(User)              -> Builder constraint: school_id = user.school_id
  │                                      (single global scope, superadmin-exempt per F3's fix)
  └── relationshipScope(User, Model) -> bool | Builder constraint
        ├── student/parent: intersects target against student_parent_links
        │     (self, or one of the caller's linked children — ChildrenController::showChildren
        │      is the reference implementation; F20's fix generalizes it)
        └── teacher: intersects target's (class, subject, year) against class_teacher_links
              for the requesting teacher (F18's fix)
```
- Every endpoint that resolves a specific record by ID (`$student_id`, `$homework`, `$assignment`, etc.) calls **both** `tenantScope` and `relationshipScope` — never tenant scope alone. This should be enforced at the Policy/Form-Request layer, not left to individual controller authors to remember (the same "don't rely on each call site to remember" lesson as F9).
- A lint/test check (extending the Section 42 route-resolution test already required for F1/F11) should assert that every "fetch one [record] by ID" endpoint has a `relationshipScope` check registered — not just that a Gate/Policy exists, since F18 shows a Gate can exist, run on every request, and still only check tenant.
- `ChildrenController::showChildren()`'s intersect-before-query shape is the template for the `relationshipScope` implementation — SchoolOS should standardize on this pattern rather than reinvent it, since it's already proven correct in the audited codebase.
- **If SchoolOS keeps specialized subclasses per role at all** (`TeacherUser`, `StudentUser`, etc.), each one gets a global Eloquent scope enforced once at the class level — not left to individual query-scope methods to remember, which is exactly how F9 happened.

## 4. Routing — external middleware wiring, explicit per-prefix ownership

**What GegoK12 does (confirmed):** all middleware/namespace/prefix wiring lives in one `RouteServiceProvider`, which is actually a clean pattern — but two prefixes (`/accountant`) are shared by two route files gated by *different* middleware (F12), and error-suppressed includes (F11) can hide a missing route file at boot.

**SchoolOS design:**
- Keep the centralized route-provider pattern — it correctly separates "what routes exist" from "what protects them."
- One URL prefix = one authorization boundary, enforced by a lint/test check, not convention.
- Never suppress errors around route/file loading — a missing route file must fail loudly at boot (direct fix for F1 + F11).
- Decide explicitly whether every role gets a web dashboard. GegoK12 silently has none for parents (F10) — that may be a fine product decision (parents are mobile-first), but it should be a decision, not a gap discovered during an audit.

**Super Admin route shape:** platform operations live under a separate `/super-admin` authorization boundary. A superadmin has no implicit tenant context (`school_id = NULL` per `16` D5), so school-affecting actions select their school via route model binding (`/super-admin/schools/{school}/...`) and never by accepting `school_id` as form input. This is the product-surface version of F3's fix: one central tenant exemption, plus explicit target-school selection where a write needs a tenant.

## 5. Attendance — event-sourced, not boolean

**What GegoK12 does (confirmed):** `attendances.status` is a literal boolean column, no unique constraint on `(user_id, date, session)`, no correction/audit history beyond soft-deletes (F13, `05-database-map.md` §3).

**SchoolOS design:**
```
AttendanceRecord (append-only events)
  ├── unique(user_id, date, session)  -- enforced at schema level
  ├── status: enum(present, absent, late, excused)
  ├── recorded_by, recorded_at
  └── corrections: has-many AttendanceCorrection
        ├── previous_status, new_status
        ├── corrected_by, corrected_at, reason
```
This directly satisfies the handoff's original Section 5/47 point 5, now backed by a concrete schema-level gap found in the actual product.

## 6. Data sensitivity separation

**What GegoK12 does (confirmed):** medical/health fields (allergies, medications, height/weight) live directly inside the general yearly academic-enrollment table, `student_academics` — one table, one access-control surface for two very different sensitivity classes (F14).

**SchoolOS design:** split into `StudentEnrollment` (academic: roll number, class, year, status) and `StudentHealthProfile` (medical fields), with independently configurable access policy on the latter — e.g. restricted to school nurse/admin roles by default, not every staff member who can view a class roster.

## 7. Naming discipline

**What GegoK12 does (confirmed):** a table named `student_history` is actually a content read-receipt tracker, unrelated to enrollment history (F15). A middleware named/aliased around "privilege" (`privilegeconditions` / `MustBePrivilege`) is actually an onboarding-completeness gate, unrelated to authorization (`02-authentication-map.md` §5e).

**SchoolOS design:** no rule beyond discipline here — name things for what they store/do, and treat "this name is technically accurate but will mislead the next engineer" as a real code-review objection, not a nitpick. Cheap to fix now, expensive to discover mid-incident later.

## 8. What NOT to change

Worth stating explicitly, since a rebuild risks over-correcting: GegoK12's actual domain modeling in the areas reviewed is reasonable —
- The tenant FK chain (§1).
- The 4-way `standards_link` join for representing a class-in-a-year (§2 of `05-database-map.md`) — normalized, defensible, just needs the class-teacher constraint enforced structurally instead of only in a relation definition.
- Explicit `student_parent_links` (§5 of `05-database-map.md`) — this is exactly the explicit parent-child relationship the original handoff wanted; SchoolOS should keep this shape almost as-is.

---

## 9. Traceability table (finding → design decision)

| Finding | SchoolOS response |
|---|---|
| F1, F11 — silent/loud include failures | Loud-fail route loading, no `@` suppression |
| F3 — scattered superadmin bypass | Single structural tenant-scope mechanism with one central exemption |
| F4 — client-supplied usergroup in OTP flow | Role never accepted as auth input |
| F5, F6 — inconsistent field lookups, crash risk | One identity-resolution function, typed failures |
| F8 — 13 duplicate role middleware | One parameterized role/permission middleware |
| F9 — unenforced subclass typing | Global scope per specialized class, not per-method |
| F18 — teacher Gates check tenant only, not class/subject assignment | §3a: mandatory `relationshipScope` check (assignment-table-backed), not tenant scope alone |
| F20 — attendance IDOR, no parent/student ownership check | §3a: `relationshipScope` generalizing `ChildrenController::showChildren()`'s intersect pattern |
| F21 — leave-application surface has no scoping at all (worse than F20) | §3a tier-3 (unscoped): `tenantScope` + `relationshipScope` both mandatory, never opt-in per method |
| F22 — assignment/homework show/update unscoped while sibling destroy is checked | §3a: shared enforcement mechanism, not per-method developer discipline |
| F12 — shared prefix, different authorization | One prefix = one authorization boundary |
| F13 — no attendance dedup constraint | Schema-level unique constraint |
| F14 — health data in enrollment table | Separate table, separate access policy |
| F15, §5e of `02` — misleading names | Naming review as a real code-review gate |

---

**This document is a draft synthesis, not final.** It's built from the domain slices actually audited (auth, roles, routing, and the core tenant/academic/attendance schema) — it does not yet cover finance/payroll, library, messaging, or HR, which remain unreviewed. Recommend treating this as the skeleton and extending it as `06`–`10` (domain-specific maps) get built, rather than starting implementation from this alone.
