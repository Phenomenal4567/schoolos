# SchoolOS — Architecture & Specification Consistency Audit

**Scope of this audit:** all 17 documents in the provided pack — `01`–`15` (the GegoK12 reverse-engineering series, including `12`/`13`/`14` design docs), `GegoK12_SchoolOS_Engineering_Handoff.md`, and `SchoolOS — Master Product & Implementation Plan.md`.

**Method:** every document was read in full and cross-referenced. Findings are attributed to their source document/finding number (`F1`–`F31`) throughout. Per the audit brief's own rules, nothing below is stated as fact unless it traces to a specific document; where the documents are silent, this audit says so rather than filling the gap.

---

## 1. Executive Summary

The GegoK12 reverse-engineering work (`01`–`11`, `15`) is unusually rigorous: every finding traces to real source code, not upstream marketing copy, and the audit trail (`11-security-findings.md`, F1–F31) is internally consistent and non-contradictory. The SchoolOS design documents built from it (`12` architecture, `13` schema) are equally disciplined — every design decision cites the specific finding it responds to, and nothing is justified by generic "best practice" alone.

The problem is not the audit work. The problem is that **two other documents in this pack — the original `GegoK12_SchoolOS_Engineering_Handoff.md` and the separately-authored `SchoolOS — Master Product & Implementation Plan.md` — were written from product vision and prior discovery interviews, not from the audit**, and they specify a materially larger, differently-shaped MVP than `12`/`13`/`14` actually support today. Reconciling these three lineages — audit-grounded design, the original handoff's aspirational architecture, and the product plan's feature vision — is the main work this document does.

**Bottom line:** the audited core (auth, tenant/role/scope, attendance) is implementation-ready. The product-level MVP definition is not — it currently assumes finance, biometric staff attendance, and exam/result modules as Phase-1 features that have zero schema, zero domain-map audit, and in the reference system's case (exams/timetable, `15` §1) are **confirmed broken on every install**. This audit's final verdict, decisions, and phase plan are in §22, §18–20, and §26.

---

## 2. What We Already Have

- A complete, source-verified map of GegoK12's authentication (`02`), role/permission system (`03`), routing (`04`), core schema (`05`), and student/teacher/parent/attendance domains (`06`–`09`).
- A full controller-body audit of the entire `Api/*` and `Api/Teacher/*` surface (`10`), plus the academic sub-domain (subjects/timetable/lessons/assignments/exams, `15`).
- A running, non-renumbered security findings log with 31 confirmed findings (`11`), several with concrete severity tiers.
- A first-draft SchoolOS architecture (`12`) and logical database schema (`13`), both fully traceable to specific findings.
- A phased implementation plan (`14`) that already reorders the original handoff's phases based on what the audit actually found (Scope enforcement pulled forward into Phase 1).
- Two vision-level documents — the original handoff (48 sections) and the Master Product & Implementation Plan (48 sections) — that predate or run parallel to the audit and were never reconciled against it.

## 3. What Is Correct

- **Tenant schema shape.** `school_id` FK on every domain table, confirmed sound at the schema level in GegoK12 (`05` §1) and preserved unchanged in `13` §1.
- **Explicit parent-child linking (`student_parent_links`).** Confirmed as the load-bearing, correctly-used mechanism (`08` §2–3), kept almost as-is in `13` §3.
- **Explicit teacher-class-subject assignment (`class_teacher_links`/`class_teacher_assignments`).** A real, populated, correctly-used-elsewhere table in GegoK12 (`07` §2) — the audit's single most important finding is not that this table is wrong, but that authorization code never queries it (F18).
- **Year-versioned student enrollment.** `student_academics` in GegoK12 already produces a natural history-by-year shape (`05` §4); `13` §3 keeps this via `student_enrollments` with a schema-level `UNIQUE(student_id, academic_year_id)`.
- **Centralized attendance write path.** Unlike login, GegoK12's three attendance-taking controllers all funnel through one shared trait method (`09` §1) — a genuine positive pattern worth keeping structurally, even though the write path itself has real gaps (§9 below).
- **The Role + Scope conceptual split (`12` §3).** This is well-evidenced (F8, F9, F18, F20–F22) and is not contradicted by either vision document — the Master Product Plan's own "User → School → Role → Permissions → Resources" model (Master Plan §5) and the original handoff's five-layer authorization stack (Handoff §16) both independently arrive at the same shape. This is a rare case of three independently-written documents agreeing.

## 4. What Conflicts

See the full Contradiction Register (§23). Summarized:

1. **MVP scope size.** Master Plan §34/§46 puts Finance, configurable/biometric staff attendance, and exams/results in Phase 1 "Must Have." `14-schoolos-implementation-plan.md` explicitly defers Finance to Phases 6–8 ("not scoped by this plan") and scopes Phase 1 to foundation + auth + scope only.
2. **Parent web access.** GegoK12 has no parent web routes at all (F10) and this is left as an explicit open decision in `14` §2/§8. The original handoff assumes a `routes/parent.php` file exists in its recommended structure (Handoff §18) and lists Parent as a dashboard role (Handoff §19). The Master Plan assumes a full "Parent & Student Portal" (Master Plan §8) as an MVP feature (§46). Two of three documents assume it exists; only the audit-grounded plan leaves it open.
3. **Staff/teacher attendance method.** The Master Plan devotes five sections (§10–§15) to configurable biometric methods (selfie, live face verification with liveness detection, fingerprint, GPS, QR). No other document in the pack discusses staff/teacher self-attendance at all — `13`'s attendance schema (§5) is scoped to *student* attendance only, and the audit brief itself (§10 of the audit instructions) cautions against introducing biometric/surveillance-heavy functionality without explicit justification from the documents.
4. **Exams/Timetable: core or addon?** `15` §1 found GegoK12 treats Exam and Timetable as separately-installed addon modules (`Gegok12\Exam\*`, `Gegok12\Timetable\*` namespaces) — and that the three controllers meant to consume them are broken on every install regardless of configuration (wrong namespace, not a missing dependency). `14` §4/§8 flags this as an open decision. The Master Plan assumes exams/results are core, Phase-1 functionality with no mention of a plugin boundary.
5. **Role list granularity.** GegoK12 has 13 `usergroup_id` values (`03` §1). The Master Plan's "Initial roles" list (§5) has 8, introduces **Principal** as a top-level role (GegoK12 only has this as a Laratrust sub-privilege *within* Teacher, `03` §2/§7 §3) and a generic **Staff**, and omits Librarian/Receptionist/Stock Keeper/Alumni/Non-teaching (which appear elsewhere in the same document's module list, §20 area, as implied roles).
6. **Terminology: "Section" vs "Arm."** `05`, `06`, `13`, and the original handoff all use "Section." The Master Plan exclusively uses "Arm" (a regional/Nigerian-schools term for the same concept) and never uses "Section."
7. **Attendance session naming.** GegoK12's schema and write path use `forenoon`/`afternoon` (`05` §3, `09` §2). `13` §5's SchoolOS schema uses `morning`/`afternoon`. Minor, but worth normalizing before implementation.

## 5. What Is Missing

- **A `subjects` table.** `13` §3 references `subject_id` as a foreign key on `class_teacher_assignments` but never defines a `subjects` table anywhere in the document. This is a genuine schema gap, not a documentation oversight — nothing else in `12`/`13` defines it either.
- **A `terms`/`academic_terms` table.** Referenced conceptually in the original handoff (§13, §20) and the Master Plan (§4, §20), but `13` only has `academic_years` — no term/semester substructure exists in the SchoolOS schema.
- **An `audit_logs` table.** Required at the principle level repeatedly (Handoff §24, Master Plan §31, and implicitly by `14`'s ground rules and F3/F18's remediation), but no such table appears anywhere in `13-schoolos-database-schema.md`. This is the clearest case in the whole pack of a requirement stated in prose three separate times and never once schema'd.
- **A staff/teacher attendance schema.** `13` §5's `attendance_records` table has a `student_id` FK, not a polymorphic or generic `subject_id`/`user_id` — there is no schema path for the *staff* attendance the Master Plan spends six sections designing.
- **Platform/subscription schema.** Master Plan §3 requires Super Admin to control "Subscriptions," "Plans," and "School activation/suspension." `13`'s `schools` table has a `status enum(active, suspended)` (§1) but no subscription/plan model exists anywhere.
- **Finance, library, transport, HR/payroll schemas.** Explicitly and correctly out of scope per `14` §6 and `13`'s closing note — flagged here only so the gap isn't silently assumed filled by the Master Plan's extensive prose description of these modules (Master Plan §23–25 for finance alone).
- **API architecture detail.** The audit brief (§11) asks about versioning, pagination, filtering, rate limiting, background jobs. None of the provided documents specify these beyond one narrow recommendation (rate-limit OTP/SMS-triggering endpoints, F16). **Not specified in the provided documentation** — flagged as an open item, not answered by inference.
- **Multi-school user membership.** Every document assumes one user belongs to exactly one school (`users.school_id` singular FK, unchanged in `13` §2). Nothing in the pack addresses whether a teacher or admin can legitimately work across multiple schools in one account. **Not specified in the provided documentation.**
- **Branch/campus support.** Listed as a bare bullet under Foundation in the original handoff (§20: "Branch/campus support if required") and never mentioned again anywhere else in the pack. **Not specified** beyond that single bullet.
- **Documents/files domain.** Mentioned only as passing bullets ("Student documents," Handoff §20/Master Plan §6) with no controller review, no schema, no access-policy discussion anywhere.

## 6. What Should Be Removed

- **GegoK12's `usergroup_id` as a bare integer**, the parallel Laratrust `role_user`/`permission_user`/`permission_role` tables, and the specialized `StudentUser`/`TeacherUser`/`ParentUser` subclasses — already correctly excluded by `13` §2 (F8, F9). No change recommended; noted here for completeness of the audit trail.
- **`users.ref_id`** — GegoK12's second, largely-dead, asymmetrically-implemented parent-child linkage mechanism (`08` §3). Already correctly excluded from `13` §3. Confirmed: no equivalent column should exist in SchoolOS's `users` table.
- **The 13 hand-written `MustBe*` middleware classes** — already correctly replaced by one parameterized role/permission middleware in `12` §3 (F8). No change recommended.
- **GegoK12's class-session-granularity attendance duplicate guard** — superseded by `13` §5's per-`(student_id, date, session)` unique constraint (F13). No change recommended.
- **Biometric attendance methods, as a Phase-1/MVP requirement.** Not something to remove from the product vision entirely, but should be removed from any "MVP" or "Phase 1" label — see §4 item 3 and §19 below.

---

## 7. Multi-Tenancy Decision

**What is the tenant?** The school (`schools` table). Confirmed consistently across every document — GegoK12's schema (`05` §1), `12` §1, `13` §1, the original handoff (§17), and the Master Plan (§30) all agree on this without exception. This is not an open question.

**What entities belong to a school?** Every domain table reviewed in the audit carries (or, per `13`, must carry) a `NOT NULL school_id`: users, classes/sections, enrollments, attendance, parent-child links, teacher assignments, health profiles. `13` §1 additionally closes a real gap GegoK12 had — `student_parent_links.school_id` and `student_history.school_id` were nullable in the reference implementation (`05` §1); SchoolOS makes `school_id` `NOT NULL` everywhere, no exceptions.

**What entities are global (not school-owned)?** `roles` and `permissions` as defined in `13` §2 are global lookup tables (a `role_id` foreign key on `users` ties a specific user to a school; the role definitions themselves are shared vocabulary across the platform). This is an implicit design choice in `13`, not explicitly stated as a decision anywhere in the pack — worth confirming explicitly rather than leaving as an inference (see Missing Decisions Register, §24).

**How is school ownership represented?** A direct `school_id` foreign key on every tenant-scoped row (`13` throughout). Not a separate ownership/ACL table — a simple FK, which `05` §1 confirmed is already GegoK12's (mostly sound) approach.

**How is school context established during authentication?** Via the authenticated user's own `school_id`, resolved once inside `AuthenticationService.authenticate()` (`12` §2) — never from client input. This directly closes F4 (client-supplied `usergroup` in the reset flow) and the broader pattern F4 represents.

**How is tenant access enforced?** `ScopeService.tenantScope(User)` (`12` §3a, `13` §3) — a single, structural mechanism, not per-controller conditionals. This is the direct fix for F3 (GegoK12's scattered `usergroup_id == 1` superadmin bypass checks).

**How are cross-school queries prevented?** By making `tenantScope` + `relationshipScope` both mandatory on every record-resolving endpoint, enforced by a lint/test rule (`12` §3a, `14`'s Phase 1 test gate) rather than developer discipline — this is the direct structural response to F18, F20, F21, F22, and the further findings F23–F31 that generalized the same pattern across nearly every controller in the API surface.

**Can a user belong to multiple schools?** **Not specified in the provided documentation.** Every schema and design document assumes a single `school_id` per user. If SchoolOS needs multi-school staff (e.g., a district-level administrator or a teacher who works at two campuses), this needs to be decided before Phase 1's `users` table is finalized — retrofitting a many-to-many school membership onto a single-FK design later is a breaking schema change. Logged as a blocking Missing Decision (§24).

**Can a platform administrator access multiple schools?** Yes, by design (superadmin bypass, F3). **Implementation update:** `16` D5 resolved the mechanism as `users.school_id = NULL` for `role='super_admin'` only, and `16` D20 records the built platform surface: `/super-admin` routes for dashboard, school management, school-admin creation, and audit-log review. School-affecting superadmin writes name the target school in the route instead of widening the school-admin `/admin` group or accepting a submitted `school_id`.

**What happens when a user's school membership changes?** **Not specified in the provided documentation.** No document discusses a user transferring between schools (relevant for, e.g., a teacher changing employer schools, or a student transferring — student transfer is mentioned at the product-vision level in Master Plan §6/§22 but never connected to what happens to the `school_id`-scoped `users` row).

**What happens when a school is suspended?** `13` §1 defines `schools.status enum(active, suspended)` as an explicit state (an improvement over GegoK12's boolean), but **no document specifies suspension's actual runtime behavior** — does it block login entirely, make data read-only, hide the school from cross-school reports, or something else? Logged as a Missing Decision (§24).

**Enforcement across layers:** `13`'s schema-level `NOT NULL` constraints (database layer) + `ScopeService` (service layer, enforced at Policy/Form-Request layer per `12` §3a) + the lint/test rule requiring every ID-resolving endpoint to register a scope check (API layer) together cover database, service, and API. **UI-layer enforcement is not discussed anywhere in the pack** — per Handoff §19 and the audit brief's own principle, authorization should never live only in hidden UI elements, and this should be stated explicitly as a UI-layer requirement in the final architecture, not left implicit.

---

## 8. Authentication Decision

**Confirmed GegoK12 behavior:** at least three separate, differently-shaped API login code paths for parent/teacher credentials (`02` §2), a web login trait with four validators that don't agree on which field they're checking (F5, F6), and a registration-number login path that is very likely a hard crash in the checked-out copy (F5).

**SchoolOS requirement (already well-specified in `12` §2, unchanged by this audit):**

```
AuthenticationService
  ├── resolveIdentifier(input) -> {type: email|registration_number|mobile, value}
  ├── authenticate(identifier, credential) -> User | AuthFailure
  └── issueSession(User) -> WebSession | ApiToken
```

- Exactly one resolution function; every downstream check uses the resolved identifier, never a hardcoded field (direct fix for F5/F6).
- Web and mobile/API call the same `authenticate()` — no parallel per-role login controllers.
- "User not found" is always a typed failure, never a null-dereference (direct fix for F5, F16's `\Error`-not-`\Exception` catch gap).
- Role/usergroup is never accepted as authentication input (direct fix for F4).

**This document adds one clarification the audit didn't fully resolve:** F4's downgraded severity assessment noted that GegoK12's `usergroup` parameter in the OTP/reset flow may serve a legitimate purpose — disambiguating one phone number shared across multiple role-accounts (e.g., a staff member who is also a registered parent). If SchoolOS needs to support this same real-world case, `12` §2 already specifies the right answer (resolve server-side from an authenticated lookup, never a bare client parameter) but the *product requirement itself* — does SchoolOS need to support one person holding two role-accounts on one phone number? — is not confirmed anywhere in the documentation. **Not specified**, flagged for §24.

**Rate limiting** (F16: no rate limiting visible on any OTP/reset endpoint, enabling both crash-probing and SMS-bombing) should be a named, explicit requirement in the final architecture — currently it exists only as a one-line action item buried in a finding, not as a stated `AuthenticationService` requirement.

## 9. Authorization Decision

**Confirmed GegoK12 behavior:** five parallel/overlapping mechanisms — `usergroup_id` (System A), Laratrust roles (System B), `permission_user` (System C, apparently unused), `Group`/`GroupMember` (a fourth, unrelated grouping concept), and 13 hand-written `MustBe*` middleware classes (a fifth surface) — none of which compose into a single "what can this user do" answer (`03` §5).

**SchoolOS design (already specified, `12` §3/§3a, `13` §2/§3):** collapse to two concepts —

- **Role** — a real lookup table (`roles`/`permissions`/`role_permissions`, `13` §2), replacing the 13 bare `usergroup_id` integers and absorbing GegoK12's few genuinely useful Laratrust sub-privileges (`principal`, `leave_checker`) as permissions on a role, not a bolted-on third system.
- **Scope** — `ScopeService.tenantScope()` + `relationshipScope()`, the direct structural fix for F18/F20/F21/F22 (tenant-only or fully-unscoped record access) and, per the further `10`/`15` findings, the same pattern repeated across at least 15 distinct controllers (F20's generalization, plus F23–F31).

**This audit adds one refinement not fully captured in `12`/`13`:** `15` §4 found a *fourth* legitimately correct authorization pattern in GegoK12 — role-based scope *widening* (`Api\Teacher\LessonPlanController::index()`'s `hasRole('principal')` branch, which lifts the teacher-only relationship restriction for principals). `12` §3a's `ScopeService.relationshipScope` design does not yet explicitly model this as a parameter. **Recommendation:** `relationshipScope` should accept a role-aware widening parameter from the start, since F30 shows the *exact same controller* that has this pattern correctly implemented in `index()` fails to reuse it in `approve()`/`reject()` — the two methods where a principal's broader visibility actually needs to translate into broader *authorization*, not just broader *listing*.

**Distinguishing authentication / authorization / tenant isolation**, per the audit brief's own framing:

```
Authentication   = Who are you?              -> AuthenticationService
Authorization    = What are you allowed to do? -> Role + Permission
Tenant isolation = Which school's data?       -> ScopeService.tenantScope
Relationship scope = Which specific records within that school? -> ScopeService.relationshipScope
```

This four-way split (rather than the three-way split the audit brief poses) is necessary because F18/F20/F21/F22 collectively prove that "tenant isolation" and "which specific records" are two different, independently-failing checks in the reference implementation — collapsing them back into one concept in SchoolOS would reintroduce exactly the defect class this whole audit exists to close.

## 10. Role & Permission Model

Reconciling the three role lists in the pack (GegoK12's 13 `usergroup_id`s, the Master Plan's 8 "initial roles," and the original handoff's implied set):

| Role | Present in GegoK12 (`03` §1) | Present in Master Plan §5 | Recommended SchoolOS role |
|---|---|---|---|
| Platform/Super Admin | Yes (Site Admin, ID 1) | Yes | Yes — platform-level, not school-scoped |
| School Admin | Yes (ID 3) | Yes | Yes |
| School Sub-Admin | Yes (ID 4) | No (not listed) | Recommend: **permission on School Admin role**, not a separate role — same reasoning as collapsing Laratrust's `principal` |
| Principal | No (Laratrust sub-role *within* Teacher, `03` §2/§7 §3) | Yes, listed as a top-level role | **Contradiction — see Contradiction Register #5.** Recommend: permission on Teacher role (`approve_lesson_plan`, `approve_assignment`, `approve_homework`, `approve_leave`), consistent with `07` §3's finding that this is the one part of GegoK12's authorization sprawl that's conceptually sound already |
| Teacher | Yes (ID 5) | Yes | Yes |
| Student | Yes (ID 6) | Yes | Yes |
| Parent | Yes (ID 7) | Yes | Yes |
| Librarian | Yes (ID 8) | No (implied by module list only) | Yes, if Library module is in scope — deferred with the module (`14` §6) |
| Alumni | Yes (ID 9) | No | Open — GegoK12 role exists but no domain map covers it; not clearly needed for MVP |
| Receptionist | Yes (ID 10) | No (implied) | Yes, if Reception module in scope — deferred |
| Accountant/Bursar | Yes (ID 11) | Yes ("Accountant/Bursar") | Yes, if Finance module in scope — deferred per §4/§19 |
| Stock Keeper | Yes (ID 12) — **confirmed scaffolding-only, F17: no actual implementation exists to reference** | No | Recommend: build from scratch if Inventory module is ever in scope; no GegoK12 behavior to port |
| Non-Teaching Staff | Yes (ID 13) | Implied by generic "Staff" | Yes, generic Staff role, permissions TBD |

**Recommendation:** adopt a **role table seeded with 8–10 roles for Phase 1–2** (Super Admin, School Admin, Teacher, Student, Parent, plus Accountant/Librarian/Receptionist/Staff seeded but inert until their modules ship), and model Principal/leave-approval/lesson-plan-approval purely as **permissions attached to the Teacher role**, not a separate role — this directly resolves Contradiction #5 in favor of the audit-grounded reading (`03` §6, `07` §3), since the Master Plan's "Principal as a role" framing has no grounding in what the reference system actually implements and Laratrust's own design already treats it as a sub-privilege.

**Resource-level authorization beyond RBAC:** per `12` §3a, every finding from F18 onward shows role-based permissions alone are insufficient — a Teacher role permission like `student.view` is necessary but not sufficient; the record itself must additionally pass `relationshipScope` (is this specific student in a class this specific teacher is assigned to). This applies to: student records (F18), homework/assignments (F18, F22), leave applications (F21), grading (F29), lesson-plan approval (F30/F31), notifications (F27), search results (F28), and tasks (F25). Effectively **every domain reviewed in this audit** needed resource-level scope beyond role — this is not a niche exception, it's the norm for this product.

## 11. Database Consistency Findings

**Missing tables (confirmed by comparing `13` against its own referenced foreign keys and against the domain documents):**
- `subjects` — referenced (`subject_id` FK) but never defined. **Must be added before Phase 2's `class_teacher_assignments` table can actually be created.**
- `academic_terms`/`terms` — referenced conceptually in two vision documents, no table in `13`.
- `audit_logs` — required at the principle level in three separate documents, no table in `13`.
- A staff/teacher attendance table, distinct from `attendance_records` (which is student-only in `13` §5).
- `subscriptions`/`plans` (platform layer) — required by Master Plan §3, not modeled in `13`.

**Unnecessary tables / duplicate concepts (already correctly excluded, confirmed no re-introduction needed):** the Laratrust junction tables, `usergroup_id`, `users.ref_id` — see §6 above.

**Nullable/non-nullable problems:** resolved — `13` §1 correctly makes `school_id` `NOT NULL` everywhere, closing GegoK12's inconsistency (`05` §1).

**Incorrect cardinality:** none found in `13`'s existing tables. `student_enrollments`'s `UNIQUE(student_id, academic_year_id)` and `class_teacher_assignments`'s `UNIQUE(class_section_id, subject_id, teacher_id, academic_year_id)` are both correctly specified.

**Unclear ownership:** the `class_teacher_id` invariant on `class_sections` (must reference a `role='teacher'` user) is explicitly flagged as unresolved in `13` §3 itself — this is not a new finding, it's already correctly logged as an open item for `14` to decide (trigger vs. write-path invariant, per `14` §1).

**Audit requirements:** every table in `13` has `recorded_by`/`corrected_by`-style columns where a specific finding demanded it (attendance), but there is no general-purpose audit log covering the broader set of actions the vision documents require (user creation, role changes, fee changes, config changes — Master Plan §31). This is the same gap as the missing `audit_logs` table above, restated as a database-consistency finding rather than a domain-completeness one.

## 12. Canonical Database Model

The canonical model is `13-schoolos-database-schema.md` as written, **plus** the following additions this audit identifies as necessary before Phase 1–2 can be implemented as currently scoped:

```
schools (unchanged, 13 §1)
  + subscription_plan_id  FK -> plans, nullable   -- NEW, if platform subscription
                                                     -- billing is in scope (Master Plan §3);
                                                     -- nullable so self-hosted/no-billing
                                                     -- deployments aren't forced to have one

roles, permissions, role_permissions (unchanged, 13 §2)

users (unchanged, 13 §2)

academic_years (unchanged, 13 §3)

academic_terms                                     -- NEW
  id                  PK
  school_id           FK -> schools, NOT NULL
  academic_year_id    FK -> academic_years, NOT NULL
  name                not null            -- 'Term 1', 'Semester 1', etc.
  start_date, end_date

standards, sections, class_sections (unchanged, 13 §3)

subjects                                            -- NEW
  id                  PK
  school_id           FK -> schools, NOT NULL
  name                not null
  code                nullable

class_teacher_assignments (unchanged, 13 §3 — now correctly has a
  subjects table for its subject_id FK to resolve against)

student_enrollments, student_parent_links (unchanged, 13 §3)

student_health_profiles (unchanged, 13 §4)

attendance_records, attendance_corrections (unchanged, 13 §5 —
  student-scoped only; see staff_attendance_records below)

staff_attendance_records                            -- NEW
  id                  PK
  school_id           FK -> schools, NOT NULL
  user_id             FK -> users, NOT NULL          -- any staff role, not student-specific
  date                date, NOT NULL
  check_in, check_out timestamp, nullable
  method              enum(manual, qr, selfie, face, fingerprint, gps)
  verification_result enum(verified, unverified, pending, rejected), nullable
  status              enum(present, late, absent, early_departure, excused)
  UNIQUE (user_id, date)

content_read_receipts (unchanged, 13 §6)

audit_logs                                          -- NEW
  id                  PK
  school_id           FK -> schools, nullable        -- nullable only for platform-level
                                                       -- (superadmin) actions
  actor_id            FK -> users, NOT NULL
  action              not null                        -- 'user.created', 'attendance.corrected', ...
  entity_type, entity_id
  before_state, after_state   -- structured diff, format TBD in 14
  created_at          timestamp, NOT NULL
```

Everything else — finance, library, transport, HR/payroll, communication, exams/timetable/lessons — remains **explicitly out of this canonical model**, consistent with `13`'s own closing note and `14` §6, pending their own domain-map passes.

## 13. Domain-by-Domain Assessment

| Domain | Status | Basis |
|---|---|---|
| School management | Mostly complete | `13` §1; suspension *behavior* unspecified |
| Authentication | Complete | `12` §2, extensively evidenced |
| Authorization (Role/Scope) | Mostly complete | `12` §3/§3a, `13` §2/§3; role-widening parameter (§9 above) not yet formalized |
| Students | Mostly complete | `13` §3, F14's health-data split applied |
| Teachers | Mostly complete (class assignment) / **Missing** (own attendance) | `13` §3 vs. no staff attendance schema |
| Parents | Contradictory (web access) / Mostly complete (relationship model) | See Contradiction #2 |
| Non-teaching staff (Librarian/Receptionist/Accountant/Stock Keeper) | Partially specified | Roles named, no domain map, no schema — correctly deferred |
| Classes/Sections | Complete | `13` §3 |
| Subjects | **Missing** | Referenced, never defined (§11 above) |
| Academic sessions/terms | Partially specified | `academic_years` exists; terms do not |
| Attendance (student) | Complete | Best-specified domain in the pack — `13` §5, `12` §5, `09` |
| Attendance (staff) | Partially specified / **Should be postponed past MVP** | Vision-heavy (Master Plan §10–17), zero schema, zero domain-map audit of any reference implementation |
| Assessments/Exams/Results/Report cards | Contradictory | Reference implementation confirmed broken on every install (`15` §1); Master Plan assumes Phase-1 MVP status with no schema anywhere |
| Promotion | Missing | One paragraph in Master Plan §22, nothing else |
| Timetable | Contradictory / Should be postponed | Same broken-namespace finding as Exams (`15` §1) |
| Finance | Should be postponed | `14` explicitly defers; Master Plan treats as Phase-1 MVP with no audit grounding |
| Communication/Notifications | Partially specified | One confirmed-good pattern to reuse (`09` §5); no schema yet, correctly deferred to `14` Phase 5 |
| Documents/Files | Missing | Passing mentions only |
| Reporting | Should be postponed | Vision-level only (Master Plan §33) |
| Audit logs | **Missing at schema level despite being a stated requirement in 3 documents** | See §11 |
| System administration (platform/subscription) | Partially specified | `schools.status` exists; subscription/plan model does not |

## 14. Attendance Architecture

**Student attendance** is the best-evidenced, most implementation-ready part of the entire document set. `13` §5's design directly and completely addresses F13 (no unique constraint, class-level-not-per-student duplicate guard with a real TOCTOU race, no correction capability at all):

- `UNIQUE(student_id, date, session)` at the schema level — makes the race structurally impossible rather than "reduced," and is a strictly finer granularity than GegoK12's class-level guard, which is *why* GegoK12 could never safely build a correction flow (`09` §4/§6) and SchoolOS can.
- `status` is a real enum (`present, absent, late, excused`), not GegoK12's literal boolean (`05` §3, `09` §2).
- `attendance_corrections` as a proper sub-entity — GegoK12 has zero correction capability anywhere in three fully-reviewed controllers (`09` §4), so this is new functionality, not ported behavior.
- The audit's own recommendation to decouple parent-notification dispatch from the attendance-write transaction (`09` §5/§6) — GegoK12 fires `SinglePushEvent`/`SingleNotificationEvent` inline inside the same write loop, worth avoiding via a proper `AttendanceRecorded` domain event.

**Staff/teacher attendance is a different situation entirely.** The Master Plan's five-method design (§10–§15 — selfie, live face verification with liveness detection, fingerprint hardware integration, GPS, rotating QR) is thoughtful about *not* mandating one universal hardware requirement across schools, which is a reasonable product instinct (Master Plan §2's "configure the school" principle). But:

- **No reference implementation exists anywhere in the GegoK12 audit to learn from or react to** — none of `02`–`15` discuss GegoK12 staff/teacher self-check-in at all. Every SchoolOS design decision in this pack is supposed to trace to a specific finding; this module traces to none.
- **No schema exists** — `13` has no staff attendance table (§11/§12 above close this gap structurally, but the *design* — which methods, what verification data is retained, retention policy for biometric templates — is unspecified).
- **The audit brief itself instructs caution here**: "Do not introduce biometric or surveillance-heavy functionality unless the documents explicitly justify it," and "do not assume every school has the same hardware or infrastructure." The Master Plan's Face Verification design does show some of this awareness — it explicitly recommends retaining "the attendance event and verification result, rather than unnecessarily storing every attendance selfie" (Master Plan §12) — which is a good instinct, but it is the *only* privacy/retention consideration mentioned anywhere in six sections of biometric design, and it isn't backed by any data-protection/consent framework.

**Recommendation:** treat staff attendance as its own module, config-driven (`method` enum on `staff_attendance_records`, per §12's proposed schema), and explicitly **out of MVP** (see §18–19). When it is built, Selfie Upload and QR Code (Master Plan §11, §13) are the two lowest-risk methods to ship first — no biometric template storage, no liveness-detection false-negative UX problem, no hardware dependency. Live Face Verification and Fingerprint should require an explicit data-retention and consent design pass before implementation, not be treated as "just another config option" alongside QR.

## 15. API/Backend Architecture

The pack's evidence here is narrower than for auth/scope/attendance. What is confirmed:

- **Sanctum token auth for API/mobile, session auth for web, both running through the same middleware stack** (`02` §6, F7) — GegoK12's own choice to make `StartSession` global rather than `web`-group-scoped is what makes Sanctum's stateful-request handling work at all; this is a real, if under-documented, architectural dependency worth carrying forward deliberately (not accidentally, the way GegoK12 has it) if SchoolOS keeps a similar hybrid web+API auth model.
- **Controller-body-level authorization, not a route-middleware-level one** (`10` §1) — both `Api/*` subtrees sit under one `api` middleware group with no further per-subtree role separation; every one of F20–F31's findings exists because scoping is a per-controller-body concern in GegoK12. This is the strongest argument in the whole document set for SchoolOS enforcing `tenantScope`/`relationshipScope` at a shared framework layer (Policy/Form-Request) rather than leaving it, as GegoK12 does, to individual method bodies.
- **Write endpoints need the same scope enforcement as reads, and — per F25, F29, F31 — are if anything *more* likely to be left unscoped in the reference implementation**, not less. Delete and bulk-mutation endpoints (F25's `Api\TaskController::changestatus`, F31's bulk approval endpoint) are explicitly named as needing the same mandatory check.

**Not specified in the provided documentation:** API versioning strategy, pagination/filtering/sorting conventions, background job architecture, and rate limiting beyond the one OTP-specific recommendation (F16). This audit does not fabricate answers here — these need their own design pass before Phase 1's API surface is built, since `14`'s Phase 1 explicitly includes both web and API controllers calling the same `AuthenticationService`/`ScopeService` from day one.

## 16. Security Architecture

The findings log (`11`, F1–F31) is comprehensive and requires no re-litigation — it is the single most complete artifact in the pack. Structured as the audit brief requests:

| Problem | Risk | SchoolOS solution |
|---|---|---|
| Tenant-only scoping treated as sufficient (F18, F20) | Cross-record disclosure within a school (any teacher sees any student; any parent sees any child's attendance) | Mandatory `relationshipScope`, not just `tenantScope`, on every record-resolving endpoint (`12` §3a) |
| Zero scoping of any kind on several controllers (F21, F22, F24–F28) | Cross-*tenant* disclosure and mutation — worse than IDOR, effectively no access control | Both `tenantScope` and `relationshipScope` structurally mandatory, enforced by a lint/test rule so "someone forgot the check" stops being a reachable failure mode |
| Identity-spoofing on writes (`store` methods accepting a caller-supplied student/target ID) — Homework, Assignment, Leave, Feedback (F20 write case, F27) | A caller can submit content *as* another person | `relationshipScope` must gate mutations, not just subsequent reads |
| Forged academic records (F29 — grading with zero scope) | A teacher (any school) can set grades on any student's submission | Grading endpoints require both `tenantScope` and a `Teacherlink`/`class_teacher_assignments`-based `relationshipScope` before any write |
| Workflow-integrity bypass on approvals (F30, F31 — five independent controllers, same gap) | Any teacher can approve/reject any lesson plan, homework, or assignment school-wide or cross-school, triggering real notification side effects | Approval endpoints need role check + scope check enforced structurally, not per-controller — this is the most systemically repeated gap in the entire audit (5-for-5 across independently-implemented controllers) |
| Cross-tenant user enumeration by name substring (F28) | Force-multiplies every other ID-guessing finding by supplying a target list | Any search-by-name endpoint must be at minimum `tenantScope`d |
| Uncaught `\Error` vs. `catch(Exception)` mismatch (F16) | Unauthenticated crash + no rate limiting = SMS-bombing / probing vector | Catch `\Throwable`, not `\Exception`; rate-limit any OTP/SMS-triggering endpoint |
| Broken addon-guard pattern on the highest-traffic controllers (`15` §1) | Entire exam/marks/timetable parent-facing surface is dead on every install | If SchoolOS keeps any plugin/addon boundary at all, guard-completeness needs a CI check, not developer trust — `class_exists()` guards existed correctly in 4 places and were still skipped in the 3 places that mattered most |
| Route/controller integrity failures (F1, F11) | Missing classes fail loudly in logs (low risk) or silently via suppressed includes (real risk) | CI check: every route resolves to an existing controller method; no `@`-suppressed route includes |

**Not over-engineered:** consistent with the audit brief's Rule 12, this table deliberately does not invent new categories beyond what F1–F31 actually found — no speculative threat modeling for attack classes the reference implementation gives no evidence of (e.g., no SQL injection findings appear anywhere in `11`, so none are invented here).

## 17. Routing Architecture

**Confirmed GegoK12 pattern (`04`):** all middleware/namespace/prefix wiring centralized in one `RouteServiceProvider` — a genuinely good pattern (`12` §4 keeps it) undermined by two concrete defects: `/accountant` shared by two route files with different middleware (F12), and an error-suppressed include that could silently hide a missing route file (F11, contrast with F1's loud failure on the unsuppressed `admin.php` include).

**SchoolOS design (already specified, `12` §4):**
- Keep the centralized provider pattern.
- One URL prefix = one authorization boundary, lint/test-enforced.
- No suppressed route-file includes, ever.
- The routing model **derives from** authorization/tenant context rather than being the primary security mechanism — this is explicitly required by the audit brief (§13) and is consistent with `12`'s design, since `ScopeService` checks happen regardless of which route/prefix reached the controller.

**Open, not yet resolved anywhere:** whether SchoolOS follows the original handoff's flat per-role file structure (Handoff §18: `admin.php`, `teacher.php`, `parent.php`, `student.php`...) or a domain-first structure (`routes/web/`, `routes/api/`, `routes/modules/`) the same section offers as an alternative "if the application becomes large." Both are presented as options in the same document without a recommendation. **Recommendation:** start with the flat per-role structure for Phase 1–3 (lower complexity, matches GegoK12's actual — if flawed in details — organizing principle, which `04` confirms is otherwise sound) and revisit domain-first only if/when the route count from Finance/Library/Transport (Phases 6–8) makes the flat structure unwieldy. This is a recommendation, not something the documents themselves decide.

## 18. MVP Scope

Reconciling `14-schoolos-implementation-plan.md`'s audit-grounded phases against the Master Plan's vision-driven "Phase 1 — Must Have" (§34) and "First SchoolOS MVP" (§46), in favor of the audit-grounded plan where they conflict (per the audit brief's Rule 2: don't assume GegoK12's — or an unaudited vision document's — content is correct just because it's written down):

**MVP (recommended):**
- Platform foundation: `schools`, `users`, `roles`, `permissions`, `role_permissions` (`13` §1–2)
- `AuthenticationService` + `ScopeService`, both structurally enforced from day one (`12` §2/§3a) — non-negotiable per `14`'s own Ground Rules
- School setup: academic years, terms *(new — see §11)*, standards/sections/class_sections, subjects *(new — see §11)*
- Users: students, teachers, parents, school admins — core CRUD, correctly scoped
- Student enrollment (year-versioned) and parent-child linking
- Class-teacher-subject assignment
- Student attendance — full event-sourced design per `13` §5 (this is the single most implementation-ready domain in the entire pack)
- Route/controller CI integrity check (F1/F11's direct fix)

**Explicitly NOT MVP**, despite Master Plan §46 listing them as MVP:
- **Finance** (fees, payments, receipts) — zero schema, zero domain-map audit of any reference implementation; `14` correctly defers this
- **Staff/teacher attendance with biometric methods** — zero schema, zero reference implementation to audit, real privacy-design gap (§14 above)
- **Exams/results** — confirmed broken in the one reference implementation available (`15` §1), zero SchoolOS schema
- **Parent portal, full feature set** (fees, results) — the *underlying decision* to build a parent web surface at all is recommended (§4 item 2, §24), but its scope should start with attendance/announcements/profile only, not fees/results, since fees/results have no schema yet either

## 19. Phase 2 Scope

- Attendance corrections workflow (schema already supports it, per `13` §5)
- Academic domain: subjects (now schema'd), timetable, lesson plans, assignments, exams — **contingent on resolving the core-vs-addon decision (§4 item 4, §24)** before schema work starts, per `14` §4's own explicit gate
- Communication/notifications, modeled as a proper domain event (`AttendanceRecorded`, etc.) rather than inline dispatch (`09` §5/§6's lesson)
- Parent web dashboard, if the decision in §24 is "yes" — attendance + announcements + profile scope only
- Staff/teacher attendance, QR and Selfie methods only (lowest-risk methods per §14)
- `audit_logs` table and the logging middleware/service to populate it (currently missing at every layer — schema, service design, and even a stated column-level spec)

## 20. Phase 3 Scope

- Finance/fees module — full domain-map pass required first (none exists)
- Staff/teacher attendance: Face Verification, Fingerprint, GPS methods, gated on the data-retention/consent design flagged in §14
- Library, Transport, HR/Payroll — `14` §6 correctly identifies these need their own `05`-style migration review and `06`-style domain-map pass before any schema or plan can responsibly be written; nothing in this audit changes that conclusion
- Reporting/analytics dashboards (Master Plan §33) — vision-level only, no domain-map evidence anywhere
- Promotion workflow (one paragraph of spec exists, Master Plan §22)
- Subscription/plan/billing model for the platform layer

**Future/Optional (not to distract from core, per the audit brief's own instruction to challenge unnecessary scope):** certificates, alumni management, quizzes, online classes/virtual classroom, "AI-assisted academic/admin features" (Handoff §20) — all mentioned exactly once in the pack, none evidenced by any audited reference behavior.

## 21. Implementation Dependencies

Unchanged from `14-schoolos-implementation-plan.md`'s own dependency ordering, which this audit finds sound and does not revise:

```
Foundation (schema + AuthenticationService + ScopeService, test-gated)
  ↓
Student/teacher/parent core (enrollment, class-teacher-subject assignment)
  ↓
Attendance (student — schema-ready; staff — deferred, see §19/§20)
  ↓
Academic (subjects/timetable/lessons/assignments/exams) — blocked on the
  core-vs-addon decision (§4 item 4) and its own domain-map pass, which
  `15-academic-domain-map.md` now substantially provides for the controller
  layer; schema work still needs to happen
  ↓
Communication
  ↓
Finance, operational modules (Phases 6-8, unchanged — `14` §6)
```

**One addition this audit makes to `14`'s dependency graph:** `audit_logs` should be built in Phase 1, not left implicit — it has no home anywhere in the current phase plan despite being required at the principle level in three documents (§11, §13). Recommend inserting it into Phase 1's Foundation build list alongside the other core tables, since it's small, has no dependencies on later-phase domains, and every subsequent phase's "correction"/"approval"/"override" workflows (attendance corrections, homework approval, etc.) will want to write to it from the moment those workflows exist.

## 22. Recommended First Vertical Slice

**Recommended slice:** *A teacher marks attendance for their own assigned class-section; a parent linked to one student in that class views that student's attendance record and is blocked from viewing an unrelated student's.*

**Why this is the strongest architectural test available, not just an attractive first feature:**

- Exercises `AuthenticationService` for two different roles (teacher, parent) through the same code path.
- Exercises `ScopeService.tenantScope` (both users must be in the same school as the record).
- Exercises `ScopeService.relationshipScope` **on both sides of the same table** — the teacher-side check (is this teacher assigned to this class-section, via `class_teacher_assignments`, F18's fix) and the parent-side check (is this student one of the caller's linked children, via `student_parent_links`, F20's fix) are the two most evidence-backed, highest-severity findings in the entire audit (F18, F20, generalized by F21/F22/F23–F31 into a pattern spanning ~15 controllers).
- Exercises the attendance schema's actual selling point — the `UNIQUE(student_id, date, session)` constraint and the correction sub-entity — which is the single most implementation-ready piece of schema in the whole pack (`13` §5).
- Exercises real business logic end-to-end (write, read, cross-role authorization) rather than producing an attractive but architecturally-shallow dashboard screen.
- Directly, mechanically re-runs the exact test gate `14`'s Phase 1 already specifies (the table in `14` §1) — this slice *is* that test gate, executed as a working feature rather than left as an abstract requirement.

This is a stronger choice than, say, "school admin creates a school and a teacher" (also plausible as a first slice) because record-level relationship scoping — not basic CRUD or basic tenant isolation — is where GegoK12 actually failed, repeatedly, across nearly every domain reviewed. A vertical slice that doesn't exercise `relationshipScope` on both a teacher-side and parent-side query would validate less of what this audit actually found broken.

---

## 23. Contradiction Register

| ID | Document A | Document B | Conflict | Recommended Decision | Priority |
|----|---|---|---|---|---|
| C1 | `14-schoolos-implementation-plan.md` (Phases 6–8, Finance explicitly deferred) | Master Plan §34/§46 (Finance in Phase 1/MVP) | Whether Finance is an MVP feature | Defer Finance past MVP — no schema or reference-implementation audit exists for it; `14`'s evidence-grounded scoping wins | Blocking |
| C2 | GegoK12 (F10 — no parent web routes; `04` §2) + `14` §2/§8 (left open) | Original handoff §18/§19 (assumes `routes/parent.php`, Parent dashboard) + Master Plan §8/§46 (Parent Portal as MVP) | Whether SchoolOS builds a parent web dashboard at all | **Build it** — two of three documents assume it exists, and it's a legitimate product need independent of what GegoK12 happened to ship; scope should start narrow (attendance/announcements/profile only, not fees/results) per §18 | Blocking (affects Phase 2 route work per `14` §2/§8) |
| C3 | Every other document in the pack (silent on staff/teacher self-attendance biometrics) + audit brief's own caution against unjustified biometric functionality | Master Plan §10–§17 (five staff attendance methods incl. Live Face Verification, Fingerprint) | Whether biometric staff attendance is Phase-1/MVP scope | Defer past MVP; ship QR/Selfie only first, with Face/Fingerprint gated on a privacy-design pass that doesn't exist yet | Blocking for staff-attendance schema, not for MVP itself (MVP doesn't need this module) |
| C4 | `15` §1 (Exam/Timetable confirmed addon-namespaced, broken on every install) + `14` §4/§8 (left as open decision) | Master Plan §21/§46 (Exams/Results assumed core, Phase-1 MVP) | Whether Exams/Timetable are core SchoolOS features or an optional plugin boundary | Build as core, not a plugin — GegoK12's own addon-guard pattern was itself violated in the highest-traffic controllers (`15` §1), showing the plugin boundary is an extra failure surface, not a safety net | Blocking for Phase 4 schema work |
| C5 | GegoK12 `03` §1 (13 usergroup roles) + `03` §2/`07` §3 (Principal is a Teacher sub-privilege, not a role) | Master Plan §5 ("Principal" listed as a top-level initial role) | Whether Principal is a distinct role or a Teacher permission | Model as a **permission on Teacher** (`approve_*`), consistent with what the audited codebase actually implements and with `12`/`13`'s own Role+Permission design | Non-blocking (schema is role-key-agnostic; affects seed data and Phase-1 role list only) |
| C6 | `05`, `06`, `13`, original handoff (all use "Section") | Master Plan (exclusively uses "Arm") | Terminology for a class subdivision | Canonical term: **Section** — see Terminology Dictionary (§27) | Low, non-blocking |
| C7 | GegoK12 schema/write-path (`forenoon`/`afternoon`, `05` §3, `09` §2) | `13` §5 (`morning`/`afternoon`) | Attendance session naming | Keep `13`'s `morning`/`afternoon` — clearer to a non-regional audience; no functional difference | Low, non-blocking |

## 24. Missing Decisions Register

| Decision | Why It Matters | Recommended Decision | Blocking? |
|---|---|---|---|
| Can a user belong to multiple schools? | `users.school_id` is a single FK throughout every design document; retrofitting multi-school membership later is a breaking schema change | Not specified in the provided documentation — needs an explicit product decision before Phase 1's `users` table is finalized | **Yes** |
| What happens when a school is suspended? | `schools.status` has a `suspended` state but no defined runtime behavior | Not specified — recommend: block login, preserve data read-only for admin export, hide from cross-school aggregate reports (a reasonable default, but genuinely a product decision, not inferred from any document) | Yes, before Phase 1 auth ships |
| `class_teacher_id` invariant mechanism (trigger vs. write-path) | `13` §3 leaves this explicitly open | `14` §1 already recommends write-path/repository invariant over a DB trigger, for the same "don't hide logic" reasoning driving this whole audit — adopt that recommendation | Yes, before Phase 2 needs `class_sections` populated (already flagged in `14`) |
| Do students share the parent `Api/*` surface or get a dedicated namespace? | Raised in `06`, `08`, `10`, still unconfirmed against real route files; affects whether `ScopeService` needs a student-specific self-only `relationshipScope` variant | Recommend: dedicated namespace, self-only scope variant — a shared surface amplifies the exact IDOR risk class (F20) this whole audit is about | Yes, before Phase 2 student enrollment endpoints (already flagged in `14` §8) |
| Are `roles`/`permissions` global platform vocabulary, or can a school define custom roles? | `13` §2 implicitly treats them as global but never states this | Not specified — recommend confirming explicitly; if schools need custom roles later, that's a materially different schema (a `school_id`-scoped `roles` table) | Yes, before Phase 1 schema is finalized |
| Does SchoolOS need one phone number to map to multiple role-accounts (the legitimate use F4 speculated `usergroup_id` might serve)? | Affects whether `AuthenticationService.resolveIdentifier` needs a disambiguation step at all | Not specified in the provided documentation | Non-blocking for Phase 1 (mobile-only edge case), revisit before mobile OTP flow ships |
| Branch/campus support | One bullet in the original handoff, never elaborated | Not specified — treat as out of scope until a real requirement surfaces | Non-blocking |
| Flat vs. domain-first route file structure | Both offered as options in the same document (Handoff §18) with no recommendation | Recommend flat per-role structure for Phase 1–3 (§17 above) | Non-blocking, revisit at Phase 6+ |
| ~~Exact platform-superadmin `school_id` handling mechanism~~ | `13` §2 originally stated the bypass as query-layer but left the user's own `school_id` value open | Resolved in `16` D5: `super_admin` users have `school_id = NULL`; implemented in `ScopeService::tenantScope()` and built out as `/super-admin` platform routes in `16` D20 | Closed |
| `audit_logs` schema, retention, and what actions must be logged | Required at the principle level three times, never schema'd | See §12's proposed table as a starting point — needs product sign-off on exactly which actions are mandatory-logged | Yes, recommend inserting into Phase 1 (§21) |

---

## 25. Final SchoolOS Architecture

```
┌─────────────────────────────────────────────────────────────┐
│                        PLATFORM LAYER                        │
│  Super Admin · Schools · (Subscriptions/Plans — gap, §11)    │
│  Platform-wide audit_logs (school_id nullable)                │
└─────────────────────────────────────────────────────────────┘
                              │
                    school_id boundary
                              │
┌─────────────────────────────────────────────────────────────┐
│                         SCHOOL LAYER                          │
│                                                                 │
│  AuthenticationService                                        │
│    resolveIdentifier → authenticate → issueSession            │
│    (one path, web + API — F5/F6/F4's direct fix)              │
│                              │                                 │
│  ScopeService                                                  │
│    tenantScope(User)              — school_id match            │
│    relationshipScope(User, Model) — class_teacher_assignments  │
│                                      or student_parent_links,   │
│                                      role-widening aware (§9)   │
│    (mandatory on every ID-resolving endpoint — F18/F20-F31)    │
│                              │                                 │
│  ┌──────────────┬──────────────┬──────────────┬─────────────┐ │
│  │  Foundation  │   Academic   │  Attendance  │Communication│ │
│  │  users/roles │  subjects/   │  student:    │  (Phase 5)  │ │
│  │  standards/  │  timetable/  │  ready (13§5)│             │ │
│  │  sections    │  lessons —   │  staff: gap  │             │ │
│  │              │  core-vs-    │  (§14)       │             │ │
│  │              │  addon open  │              │             │ │
│  └──────────────┴──────────────┴──────────────┴─────────────┘ │
│                                                                 │
│  Deferred (own domain-map pass required first):                │
│    Finance · Library · Transport · HR/Payroll                  │
│                                                                 │
│  Route layer: centralized provider, one prefix = one           │
│  authorization boundary, no suppressed includes (F1/F11/F12)   │
│                                                                 │
│  audit_logs — populated by ScopeService/write-path hooks,      │
│  not bolted on per-controller                                  │
└─────────────────────────────────────────────────────────────┘
```

## 26. Final Implementation Order

Unchanged in substance from `14-schoolos-implementation-plan.md`, with this audit's two additions (`subjects`/`terms` into Phase 1 schema, `audit_logs` into Phase 1) folded in, and the MVP boundary corrected per §18:

1. **Phase 1 — Foundation.** Schema (including `subjects`, `terms`, `audit_logs` — new per this audit) + `AuthenticationService` + `ScopeService`, test-gated per `14`'s existing table. The original blocking Missing Decisions (§24) are resolved in `16`; D20 also adds the first verified Super Admin platform portal slice for school operations and audit-log review.
2. **Phase 2 — Student/teacher/parent core.** Enrollment, class-teacher-subject assignment, parent-child linking. Resolve: parent web dashboard decision (recommend yes, scoped narrow), student-vs-parent API namespace decision (recommend dedicated).
3. **Phase 3 — Attendance (student).** Schema-ready, highest-confidence phase in the whole plan.
4. **Phase 4 — Academic (subjects/timetable/lessons/assignments/exams).** Blocked on the core-vs-addon decision (recommend: core). Needs schema work `13` doesn't yet cover — `15`'s controller-level domain map is a strong starting point but is not itself a schema.
5. **Phase 5 — Communication.**
6. **Phases 6–8 — Finance, Library, Transport, HR/Payroll, staff attendance beyond QR/Selfie.** Each needs its own domain-map pass first, per `14` §6, unchanged.

Cross-cutting from Phase 1 onward, unchanged: route/controller CI integrity check (F1/F11's direct fix).

## 27. Canonical Terminology

| Term | Definition | Resolves |
|---|---|---|
| **School** | The tenant. Root of all school-owned data. | Consistent across all documents |
| **Academic Year** | A school's yearly cycle (`academic_years`). | Consistent |
| **Term** | A subdivision of an academic year (`academic_terms` — new, §11). | Referenced in prose in 2 documents, never schema'd until now |
| **Standard** | Grade/level (e.g. "Grade 5"). | GegoK12's term, kept |
| **Section** | A subdivision within a standard (e.g. "A"/"B"). | **Canonical over "Arm"** — see C6 |
| **Class Section** | The combination of standard + section + academic year (`class_sections`) — GegoK12 called this `standards_link`. | Renamed per `12` §7's naming-discipline principle |
| **Subject** | An academic subject (`subjects` — new, §11). | Referenced everywhere, never schema'd until now |
| **Teacher** | Staff member who teaches. | Consistent |
| **Staff** | Any non-teacher, non-student/parent user (accountant, librarian, receptionist, etc.). | Master Plan's generic catch-all; recommend keeping as an umbrella term for roles without their own dedicated dashboard yet |
| **Student** | Consistent | — |
| **Parent/Guardian** | Consistent — SchoolOS should use "Parent/Guardian" in user-facing copy per the original handoff §15's own framing (not every parent-role user is a biological parent) | — |
| **Role** | What a user can do — replaces GegoK12's `usergroup_id`. | `12`/`13` |
| **Permission** | A specific allowed action, attached to a role. Absorbs GegoK12's Laratrust sub-privileges (`principal`, `leave_checker`) and the Master Plan's "Principal" role (C5). | `12`/`13` |
| **School Membership / Scope** | Which school's records a user can access (`tenantScope`) and which specific records within that school (`relationshipScope`). | `12` §3a |
| **Attendance Record** | A single student's presence/absence event for one date/session (`attendance_records`). | `13` §5 |
| **Attendance Correction** | An explicit, audited change to a previously-recorded attendance status. | New capability, `13` §5 |
| **Assessment / Exam** | Not yet schema'd; core-vs-addon decision pending (C4). | Open |
| **Result / Report Card** | Not yet schema'd; depends on Assessment/Exam decision. | Open |

## 28. Risks

- **The biggest risk is not technical — it's scope creep from the two unaudited vision documents being handed to an engineering team alongside the audit-grounded design without this reconciliation having happened first.** If `14`'s Phase 1 and the Master Plan's "First SchoolOS MVP" (§46) are both given to a team as "the plan," the team will reasonably build the larger one, re-introducing exactly the pattern the audit brief's Rule 10 warns about (praising/preserving prior work instead of critically checking it).
- **The `subjects`/`terms`/`audit_logs` gaps are silent** — nothing in `13` flags them as missing, because they were never in scope for the findings that drove `13`'s authorship (F1–F22 at the time `13` was written). A team implementing Phase 1 strictly from `13` as currently written would hit a broken foreign key reference (`subject_id` with no `subjects` table) partway through migration writing, not before.
- **The core-vs-addon decision for Exams/Timetable is genuinely consequential and currently unmade.** Building Exams as core when SchoolOS later needs a plugin boundary (or vice versa) is expensive to reverse. `15`'s finding that GegoK12's *own* addon boundary was violated in its highest-traffic controllers is strong evidence against replicating a plugin architecture at all, but this audit's recommendation (§4 item 4) should get explicit product sign-off, not just architectural sign-off, before Phase 4 schema work starts.
- **Biometric staff attendance carries real regulatory/privacy exposure** (biometric data is specially regulated in many jurisdictions) that no document in this pack addresses. Building Face Verification or Fingerprint attendance without a data-protection review is a risk independent of anything found in the GegoK12 audit itself.
- **The approval-workflow authorization gap (F30/F31) was found independently in five separate controllers, all sharing the identical root cause.** This is the strongest evidence in the whole pack that a *convention* ("remember to check the role") will not hold up under real development pressure — any part of SchoolOS's design that still relies on a developer remembering to add a check, rather than a structural mechanism that makes omitting it impossible, should be treated as a residual risk equivalent to F30/F31 until proven otherwise by the Phase 1 lint/test rule (`12` §3a, `14` §7).

---

## Final Verdict

### READY AFTER SPECIFIC FIXES

**Not** ready for implementation outright, and **not** in a state requiring a fundamental redesign either. Specifically:

**What's ready now:** the audit-grounded core — `AuthenticationService`, `ScopeService`, the Role/Permission collapse, the student attendance schema, and the foundational tenant/identity/scope tables in `13` §1–§3 — is well-evidenced, internally consistent, and directly traceable to 31 confirmed findings. An engineering team could start Phase 1 migrations and services from this material today, **with the fixes below applied first.**

**What must be fixed before implementation starts (blocking):**
1. Add `subjects`, `academic_terms`, and `audit_logs` tables to the canonical schema (§12) — `13` as currently written has a dangling foreign key reference and three unfulfilled cross-document requirements.
2. ~~Resolve the five blocking Missing Decisions in §24 (multi-school membership, suspension behavior, `class_teacher_id` invariant mechanism, global-vs-per-school roles, superadmin `school_id` handling).~~ Closed by `16` D1-D5; the superadmin product surface is additionally built and verified in `16` D20.
3. Explicitly subordinate the Master Plan's MVP/Phase-1 feature list (§34/§46) to `14`'s audit-grounded phase plan, or reconcile them — as written, they are two different documents both claiming to define "the MVP," and handing both to an engineering team unreconciled is itself the risk flagged in §28.
4. Make the parent-web-dashboard, exams/timetable-core-vs-addon, and staff-attendance-biometric-scope decisions explicit product decisions (this audit's recommendations are in §4, §18–20, §24) rather than leaving them as gaps a developer discovers mid-implementation.

Once those four items are closed, the answer becomes **READY FOR IMPLEMENTATION** for Phases 1–3 as scoped in §26. Phases 4 and beyond remain contingent on domain-map passes that either partially exist (`15`, for Academic) or don't yet exist at all (Finance, Library, Transport, HR).
