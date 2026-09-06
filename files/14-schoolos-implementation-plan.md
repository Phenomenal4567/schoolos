# 14 — SchoolOS Implementation Plan

**Status:** Draft, reconciled against `16-schoolos-decisions-register.md`'s confirmed decisions where they resolve an open question this plan originally left open (see §1 and §8 below) — sequencing and phase scope are otherwise unchanged from the original draft. Sequencing below is adapted from the original handoff's Phase 1–8 plan (§29), reordered where the audit's actual findings changed the risk picture — most significantly, Scope enforcement moves from an implicit part of "Foundation" into its own explicit, test-gated milestone inside Phase 1, because F18/F20/F21/F22 collectively show that leaving it implicit is exactly how GegoK12 got four independent instances of the same defect. Every phase below is scoped to what `12-schoolos-architecture.md` and `13-schoolos-database-schema.md` actually cover — payroll, library, transport, and HR are explicitly out of scope until they get their own domain-map pass, consistent with `13`'s closing note.

---

## 0. Ground rules carried forward unchanged

From the original handoff (§30), still binding:
- No client-supplied `school_id`, ever, at any layer — trust only the authenticated session/token.
- No authorization logic that exists only in the UI.
- No duplicated business logic between web and mobile/API — one `AuthenticationService`, one `ScopeService`, called from both.
- No feature work starts before the tables in `13` and the two services in `12` (§2 `AuthenticationService`, §3a `ScopeService`) are in place and tested.

## 1. Phase 1 — Foundation (blocks everything else)

**Build:**
- `schools`, `users`, `roles`, `permissions`, `role_permissions` (`13` §1–§2).
- `AuthenticationService` per `12` §2: one `resolveIdentifier`, one `authenticate`, one `issueSession` — called identically from web and API controllers. This directly retires F5 (registration-number crash), F6 (non-unique `name` lookup), and F4 (client-supplied `usergroup` in reset flow) by construction, since the schema no longer has a `name`-based lookup path and identifier resolution happens in exactly one place.
- `ScopeService` per `12` §3a: `tenantScope(User)` and `relationshipScope(User, Model)`, backed by `class_teacher_assignments` and `student_parent_links` (`13` §3). This is the direct fix for F18/F20/F21/F22.
- Super Admin platform surface for the D5 exemption: `/super-admin/dashboard`, school create/update/status management, explicit selected-school school-admin creation, and platform audit-log review. This is intentionally separate from the school-admin `/admin` group because a `super_admin` has no implicit current school; any school-affecting platform write must name the target school in the route rather than accepting a submitted `school_id`.
- **Resolved per `16` D3:** how `class_sections.class_teacher_id` gets guaranteed to reference a `role='teacher'` user. This plan's own recommendation — enforce it as a single invariant inside the write path that creates/updates a `class_sections` row (a repository method, not scattered controller code), not a database trigger — is exactly what D3 adopted. `ClassSectionRepository::assignClassTeacher()` (update) and `ClassSectionRepository::create()` (creation, added after Phase 2 build revealed there was otherwise no way to produce a `class_sections` row outside a test fixture) are the two write paths this covers, both routed through the same private role-check gate so the invariant lives in exactly one place regardless of which one is called.

**Test gate before Phase 2 starts (per handoff §42, sharpened with actual regression cases from this audit):**
| Test | Regresses |
|---|---|
| Login by email, mobile, and registration number all succeed via the same `authenticate()` call | F5 |
| A `(mobile_no, usergroup)` pair matching no user returns a typed failure, not a 500 | F16 |
| Login cannot be influenced by a value matching another user's display name | F6 |
| `usergroup`/`role` is never accepted as request input during authentication or reset | F4 |
| Superadmin cross-school access goes through one exemption path, not a per-query conditional | F3 |
| Superadmin school-affecting actions use an explicit route-selected school and are unavailable to `school_admin` users | F3, D5 |
| A teacher with no `class_teacher_assignments` row for a class cannot read/write that class's students, homework, or assignments | F18 |
| A parent/student cannot fetch another student's record via `relationshipScope`, for every resource type scoped this way | F20, F21 |
| Every controller action that resolves a single record by ID has a registered scope check — enforced by an automated test/lint over the route table, not manual review | F22 (the "destroy checks, show/update don't" pattern) |

That last row operationalizes `12` §3a's proposed lint/test check and `10` §5's recommendation — this is the single test most directly justified by the audit, since F22 showed manual per-method diligence already failed once in the reference codebase.

**Implementation update:** the Super Admin platform slice above is built and verified in `16` D20. The current shipped surface covers platform dashboard metrics, school listing/detail, school create/update, suspension/reactivation through `AuthenticationService::setSchoolStatus()`, school-admin account creation for a selected school, superadmin login redirect, superadmin navigation, and audit-log filtering.

## 2. Phase 2 — Student/teacher/parent core

**Build:**
- `academic_years`, `standards`, `sections`, `class_sections`, `class_teacher_assignments`, `student_enrollments`, `student_parent_links`, `student_health_profiles` (`13` §3–§4).
- Enrollment write path: creating a `student_enrollments` row for a new academic year, per the versioned-by-year shape `05-database-map.md` §4 already found worth keeping from GegoK12.
- Parent-child linking write path (admin-initiated, not self-service) writing to `student_parent_links` only — there is no second mechanism to keep in sync, unlike GegoK12's `ref_id` duplication (`08-parent-domain-map.md` §3), so there's nothing here that can drift out of sync with itself.
- `class_teacher_assignments` write path (`ClassTeacherAssignmentRepository::assign()`): this table is listed above as a Phase 2 build target and is what `ScopeService`'s teacher branch depends on (§1), but this plan's original draft never specified how it gets populated, the same gap the enrollment and parent-linking bullets above were written to close for their own tables. Same shape as those two: role check on the target teacher before writing, no second mechanism to keep in sync. Unlike enrollment's schema-level uniqueness violation, a duplicate `assign()` call for the same `(class_section, subject, teacher, academic_year)` tuple is idempotent rather than rejected, since re-assigning an already-true fact isn't a business-rule violation the way double enrollment is.

**Decision made, per F10 and resolved in `16`'s corrections section:** SchoolOS gives parents a web dashboard — GegoK12 had no parent web routes at all and `04-route-map.md` §2 flagged that as an undecided gap rather than a deliberate mobile/API-only design, so this plan treats it as a decision Phase 2 has to make rather than a silent carry-forward. Scoped to profile-only (list children, view a child's profile) in this first version; attendance and announcements views are deferred to Phase 3 and Phase 5 respectively, once those domains actually exist to show.

**Test gate:**
- One enrollment row per student per academic year enforced at the schema level (`UNIQUE(student_id, academic_year_id)`), not just application logic.
- `class_teacher_assignments` uniqueness constraint (`class_section_id, subject_id, teacher_id, academic_year_id`) prevents duplicate assignment rows.
- Parent-child scope tests from Phase 1's gate re-run against the now-populated real data shapes (empty-table tests aren't sufficient evidence the join works under real cardinality).

## 3. Phase 3 — Attendance

**Build:**
- `attendance_records` with the schema-level `UNIQUE(student_id, date, session)` and `attendance_records.status` as the four-value enum, not boolean (`13` §5).
- `attendance_corrections` table and the correction write path — GegoK12 has no correction capability at all (`09-attendance-map.md` §4), so this isn't porting existing behavior, it's new functionality the schema was specifically designed to make possible.
- Write path must be a real transaction around the uniqueness check, closing the TOCTOU race `09` §3 found in GegoK12's class-session-granularity guard (check-then-act, no lock, wrong granularity to catch the actual per-student duplicate).

**Test gate (per handoff §42's Attendance list, sharpened):**
| Test | Regresses |
|---|---|
| Two concurrent attendance submissions for the same `(student_id, date, session)` — one succeeds, one is rejected by the constraint, not a race | F13 |
| A correction updates status and writes an `attendance_corrections` row with previous/new status, corrector, reason | F13 (missing correction capability) |
| A parent/student cannot pull another student's attendance via any endpoint that takes a student identifier | F20 |
| Absence triggers a parent notification (GegoK12's real-time per-parent fan-out, confirmed a genuinely good pattern in `09` §5 — worth keeping) | — (positive pattern, not a regression) |

## 4. Phase 4 — Academic (subjects, timetable, lessons, assignments, exams)

**Build:** subjects, timetable, lesson plans, assignments, exams/marks — none of this has a dedicated schema section in `13` yet, since `12`/`13` scoped themselves to tenant/identity/scope/attendance/health. **Before writing schema for this phase, it needs its own short domain-map pass** (in the style of `06`–`09`) specifically checking every new endpoint against the `ScopeService` requirement established in Phase 1 — `10-api-map.md` §2–§3 already found the homework/assignment/leave surface (which lives partly in this domain) riddled with F20/F21/F22-shaped gaps, so assume new endpoints here need the same scrutiny, not less.

**Test gate:** every "fetch/act on a specific [homework/assignment/exam] by ID" endpoint passes the same automated scope-check-registered test from Phase 1, extended to cover the new resource types.

## 5. Phase 5 — Communication

**Build:** notifications, announcements, parent communication. GegoK12's real-time per-parent notification fan-out on attendance (`09` §5) is a template worth reusing here, not redesigning.

**Note:** `content_read_receipts` (`13` §6, GegoK12's `student_history` renamed) belongs to this phase's data model, not Phase 3's — it's a general read-receipt tracker for any posted content type, attendance included only incidentally.

## 6. Phases 6–8 — Finance, operational modules, mobile-specific work

**Explicitly deferred, not scoped by this plan.** `05-database-map.md` catalogued ~90 migrations for payroll, library, transport, and HR by filename only, never table-by-table — `13`'s schema doesn't cover them for the same reason. These phases need their own `05`-style migration review and `06`-style domain-map pass before a schema or implementation plan can responsibly be written, exactly as `01-project-map.md` §2 notes for the roadmap as a whole. Building ahead of that review risks repeating the exact mistake this whole audit exists to avoid — designing against assumptions instead of confirmed behavior.

Mobile-specific work (Phase 8 in the original handoff) isn't a separate phase in this plan at all: per Ground Rule 0 and `12` §2, web and mobile/API call the same `AuthenticationService` and `ScopeService` from the start of Phase 1, so there's no separate "make it mobile-ready" phase to schedule later — mobile-readiness is a property of Phase 1 being done correctly, not a bolt-on.

## 7. Cross-cutting: route/controller integrity (F1, F11)

Independent of phase, one CI check should exist from Phase 1 onward: every route resolves to an existing controller/method, and no route file include is error-suppressed. This is a direct, cheap fix for F1 (missing `AddonInstallExamController` broke `route:list` silently until logs were checked) and F11 (`@include` masking a missing file at boot) — GegoK12's actual failure mode was that this was discoverable only by running `route:list` and reading logs after the fact. SchoolOS should fail the build, not fail at request time.

## 8. Open items carried into implementation

- ~~`class_teacher_id` type-enforcement mechanism (§1 above) — decide trigger vs. write-path invariant before Phase 2.~~ Resolved: `16` D3, write-path invariant, implemented as `ClassSectionRepository::assignClassTeacher()`/`create()`.
- ~~Parent web dashboard — explicit yes/no decision (§2 above) — decide before Phase 2's route work.~~ Resolved: `16`'s corrections section — build it, scoped to profile-only in its first version (attendance/announcements deferred to Phase 3/5 once those domains exist). Implemented, including the cross-tenant scope check on the child-listing view.
- ~~Whether students share the parent-facing API surface or need a dedicated namespace — unresolved since `06-student-domain-map.md`, still unresolved through `08` and `10`.~~ Resolved: `16`'s corrections section — dedicated namespace, not shared. Implemented as `ScopeService::studentRelationshipScope()`'s self-only variant, distinct from `parentRelationshipScope()`'s self-or-linked-children variant.
- ~~Superadmin product surface for D5's schoolless platform role.~~ Resolved: `16` D20. Implemented as a separate `/super-admin` route group, not by widening the school-admin `/admin` group.
- Phase 4's academic domain needs its own short domain-map pass before schema work, per §4 above — this is new scope discovered during planning, not deferred from an earlier document.

---

**Traceability:** every phase above cites the specific finding(s) it closes rather than describing generic "good architecture" — consistent with `12` and `13`'s existing traceability tables. Phase 1's test gate is the most heavily evidenced section of this entire roadmap: four independent findings (F18, F20, F21, F22) across teacher-side and parent/student-side, read and write paths, all trace to the same missing check, and Phase 1 is where that check becomes structurally mandatory rather than a convention.
