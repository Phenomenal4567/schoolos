# 16 — SchoolOS Decisions Register (Phase 1 blockers, resolved)

**Status:** CONFIRMED. Every decision below was flagged as blocking in the Architecture & Specification Consistency Audit (§24, Missing Decisions Register); the audit's own recommendation was adopted as-is and confirmed by product sign-off for all five items plus the four §4/§18–20/§24 product calls (parent dashboard, exams/timetable core-vs-addon, staff-attendance biometric scope). This document is the single source of truth for these decisions going forward — `13-schoolos-database-schema-v2.md` and the Phase 1 scaffolding both implement what's decided here. All four of the audit's Final Verdict blocking items are now closed:
1. Schema fix (`subjects`, `academic_terms`, `audit_logs`, plus reserved `staff_attendance_records`) — `13-schoolos-database-schema-v2.md`.
2. The five Missing Decisions (D1–D5) — below, confirmed.
3. Master Plan subordinated to `14`'s phase plan — see subordination notice at the top of `SchoolOS — Master Product & Implementation Plan.md`.
4. Parent-dashboard / exams-core-vs-addon / staff-attendance-biometric-scope — below, confirmed.

---

## D1 — Multi-school user membership

**Decision:** A user belongs to exactly one school. `users.school_id` remains a single, `NOT NULL` foreign key (no join table).

**Rationale:** Nothing in the audited product-vision documents established a real requirement for staff working across multiple schools. If this need surfaces later, it's a genuine breaking-schema change (a `school_memberships` join table replacing the direct FK) — deliberately deferred rather than speculatively built now.

**Superadmin exception:** platform-level Super Admins are modeled with `school_id = NULL` (see D5) — this is the only exception, and it's structural, not a multi-membership mechanism.

## D2 — School suspension behavior

**Decision:** `schools.status = 'suspended'` means:
- All login attempts for users of that school fail with a typed `SchoolSuspended` auth failure (not a generic invalid-credentials message).
- No data is deleted or hidden from platform Super Admin views — suspension is read-preserving at the platform layer.
- Existing API tokens/sessions are invalidated on suspension (not left to expire naturally).
- Suspension is reversible (`active` ↔ `suspended`), and both transitions are written to `audit_logs`.

**Rationale:** Reasonable default balancing "stop active use immediately" against "don't destroy a suspended school's data" — this is a genuine product policy choice, not derived from anything in the audited documents, so it's recorded here explicitly rather than left implicit in code.

## D3 — `class_teacher_id` invariant mechanism

**Decision:** Enforced at the write path, not a database trigger. A single repository method (`ClassSectionRepository::assignClassTeacher()`) is the only code path permitted to write `class_sections.class_teacher_id`, and it verifies the target user has `role_id` = Teacher before writing.

**Rationale:** Adopted directly from `14-schoolos-implementation-plan.md` §1's own recommendation — a trigger duplicates logic the repository layer should own and is invisible to anyone reading the application code, which is a smaller version of the exact "authorization logic hidden from the obvious place" problem the whole audit is about.

## D4 — Roles/permissions: global or per-school

**Decision:** `roles` and `permissions` are global platform vocabulary, not per-school. No `school_id` column on either table. `role_permissions` is also global.

**Rationale:** Keeps Phase 1 simple and matches every document's implicit assumption. If a school-specific custom-permission need emerges later, it's additive (a `school_role_overrides` table) rather than a breaking change to the core two tables.

## D5 — Superadmin `school_id` handling

**Decision:** Platform Super Admin users have `users.school_id = NULL`. `users.school_id` is nullable **only** for `role = 'super_admin'`; enforced at the application layer (`AuthenticationService`/`ScopeService`), not a conditional database constraint. `ScopeService.tenantScope()` treats a null `school_id` on the acting user as the single, centrally-defined exemption from tenant scoping (direct structural fix for F3 — no per-call-site `usergroup_id == 1` checks).

**Rationale:** A nullable FK for exactly one role, checked in one place, is simpler and more auditable than a placeholder "platform school" row that every report and query would then need to know to exclude.

## D6 — Phase 5 (Communication) code found partially built ahead of process

**Decision:** A substantial draft of Phase 5 — `Announcement` model/migration/repository/four portal controllers, Laravel's `notifications` table, both attendance/announcement fan-out listeners, and `content_read_receipts` (with `'announcement'` already added to its `entity_type` enum) — was found already present in the repository when `18-schoolos-communication-domain-map.md`'s pass began, timestamped before the current Phase 4 files and citing `14 §5` as its design ref in every file's doc comment. This is kept, not reverted: `18` §2 evaluated it directly against the scoping model that pass derived independently (rather than assuming correctness from its existence) and found it matches — `Announcement`'s `class_section_id` dispatches through `ScopeService`'s existing generic branch, notification inboxes are session-identity-scoped with no by-ID route parameter (closing F27's `notifications($id)` IDOR by construction), and both fan-outs dispatch after their writes commit, matching `12 §5`'s decouple-from-transaction guidance.

**Status update (closing the gap `18` §8 opened):** every item `18` §8 listed as still open has since been built and verified in the codebase directly, not merely asserted by a doc comment claiming it exists:
- `NotifyAudienceOfAnnouncement` (against `AnnouncementPublished`) and `NotifyParentsOfAbsence` (against `AttendanceMarked`, plus its `handleCorrection()` method against `AttendanceCorrected` — see D8 below) are registered with explicit `Event::listen()` calls in `AppServiceProvider::boot()`.
- Routes exist in `routes/web.php` for all four `Announcement` controllers (Admin store-only; Teacher index/show/store/markRead; Parent and Student index/show/markRead) and all three `NotificationController` classes (id-less `index`, resolved from session).
- `Feedback` is built: model, migration, `FeedbackRepository::create()` with the write-time ownership check (`assertOwnsStudent()`) closing F27's spoofing gap, and routes on both Parent and Student portals.
- The `content_read_receipts` write path is built: `ContentReadReceiptRepository::markRead()` checks `relationshipScope()` visibility (via `AnnouncementRepository::findVisibleTo()` for the `'announcement'` type, a generic scoped-query check for other wired types) before writing, and rejects entity types with no visibility check wired (`'image'`/`'video'`/`'homework'` remain reserved, unimplemented enum values, matching `13` §6's original scope).
- `tests/Feature/Phase5TestGateTest.php` exists and its six tests map one-to-one to `18` §7's gate table (audience-scoped visibility, session-scoped notification inbox, feedback spoofing rejection, fan-out audience-exactness plus non-blocking-on-failure, read-receipt scope rejection, and listeners actually firing on real dispatched events — not just when invoked directly).

Per this register's own decision-logging purpose: this status update records that the phase closed out through the normal process (build the open items, then gate on tests) rather than being declared done by fiat.

**Test gate executed (closing the last open item above):** `php artisan test --filter=Phase5TestGateTest` was run against a real environment and passed — 6/6 tests, 51 assertions. Phase 5 is confirmed closed, not just asserted closed by this register.

**Rationale:** Discarding correct, on-design work because it arrived out of sequence would waste it for no safety benefit; treating it as already-shipped because it exists would let untested, unwired code stand in for a phase this project's own process (`14 §8`) requires a domain-map pass and a test gate to close. Recording it here, rather than leaving `16` silent on the gap between "Phase 4 done" and "Phase 5 domain-map pass," keeps this register's own stated purpose — a single source of truth for scope decisions — intact; the alternative was a future reader reconstructing what happened from file timestamps and doc-comment cross-references, which is exactly what `18` §2 had to do once, and would have had to do again here if this status update weren't recorded.

## D7 — School-wide announcement audience excludes platform/operational roles

**Decision:** A `'school'`-audience `Announcement` notifies `teacher`, `parent`, and `student` roles only. `super_admin` and `school_admin` are excluded as a platform/authoring-role convention (an admin who posts a school-wide announcement doesn't need to be notified of their own platform's content stream). Any other operational roles this schema may add later (`accountant`, `librarian`, `receptionist`, general `staff`) are excluded by the same `whereIn('key', ['teacher', 'parent', 'student'])` allowlist in `AnnouncementAudienceResolver::schoolAudienceIds()` — i.e., new roles are excluded by default until explicitly added to that list, not included by default until explicitly excluded.

**Rationale:** `18` §8 flagged this as a default that was "written outside the normal decision process" and needed an explicit register line rather than standing as a silent side effect of whoever wrote `AnnouncementAudienceResolver` first. Confirmed here as the intended behavior: school-wide announcements are framed as school-community content (matching `12`'s framing of `Announcement` as a parent/teacher/student-facing feature), not general platform broadcast, so operational back-office roles are excluded by design, not oversight. If a future school configuration genuinely needs e.g. librarians included in school-wide announcements, that's an additive change to the allowlist, not a reason to invert the default.

## D8 — `AttendanceCorrected` also notifies parents when the correction changes status to absent

**Decision:** When an `AttendanceCorrected` event's new status is `'absent'` and the previous status was not already `'absent'`, `NotifyParentsOfAbsence::handleCorrection()` sends the same `AttendanceAbsenceNotification` a first-time absent mark would trigger. A correction that changes status *away from* absent, or that corrects an already-absent row for an unrelated reason (e.g. fixing a note field), does not re-notify.

**Rationale:** `18` §8 named this as a deliberate non-decision left open by `AttendanceMarked`'s and `AttendanceCorrected`'s own doc comments — a correction *becoming* absent is functionally the same fact a parent needs to know as a first-time absent mark, so withholding the notification just because the record was corrected rather than originally entered would create a silent gap in the exact notification path `09` §5/§6 confirmed was worth preserving from GegoK12. The previous-status guard exists specifically so an already-notified parent isn't notified a second time for an unrelated correction to a row that was already absent.

## D9 — Full phase-gate test run (Phases 1, 4, 5) confirmed green

**Status:** `php artisan test` was run against a real PHP/Composer environment for the first time since this register and the `17`/`18` domain-map passes were written under sandbox conditions with no environment available. `Phase1TestGateTest`, `Phase4TestGateTest`, and `Phase5TestGateTest` all passed, along with the full suite (repository tests, controller tests across Admin/Teacher/Parent/Student portals, `AttendanceConcurrencyTest`, `AttendanceScopeTest`, `ScopeServiceTest`, `AuthenticationServiceTest`).

**Significance:** every prior "environment check" caveat in `16` and `18` (code exists and looks correct, but hasn't actually been run) is now resolved for Phases 1, 4, and 5. This is the first point in the project where "built" and "verified" are the same claim for all five completed phases, not just Phase 5 in isolation.

## D10 — Track 6a (School Calendar) built and verified

**Status:** Built per `19-discovery-hierarchy-gap-closure-plan.md` §3 and `20-phase-6-8-execution-prompt.md` §1: the `calendar_events` table (school-scoped, academic-year-scoped, optionally term-scoped, per `19` §3's schema verbatim), `CalendarEvent` model, `CalendarEventRepository` (school_admin-only `create()`/`update()`/`delete()`, plus tenant-scoped `visibleTo()`/`findVisibleTo()` for the read side), Admin/ParentPortal/StudentPortal controllers, routes (every by-ID route carrying `scope.checked`, per `Phase1TestGateTest`'s route-table lint), and Blade views for both portals.

No new Role row for "principal" — confirmed against this register's own precedent (`17-schoolos-academic-domain-map.md` §4, restated in `LessonPlan`'s and `ScopeService`'s doc comments) that `school_admin` is the SchoolOS stand-in for GegoK12's principal role, so `20` §1's "school-admin/principal role only" gates on the existing `role:school_admin` middleware, nothing additive.

No new scope mechanism: `calendar_events` carries no `class_section_id`/`student_id`, so `ScopeService::relationshipScope()` is never invoked for this resource — visibility is tenant scope (`ScopeService::tenantScope()`) plus a `visible_to_parents` flag only, matching `19` §3's "simplest gap in this document" framing.

**Test gate executed:** `php artisan test --filter=Phase6aTestGateTest` was run against a real PHP/Composer environment and passed — 3/3 tests, 20 assertions, covering exactly `20` §1's three named gate items: (a) tenant scoping, (b) parent/student cross-tenant isolation, (c) `visible_to_parents = false` events hidden from parent/student views.

**Rationale:** Recorded here, in the same "built and verified" format D9 uses, per this register's own binding rule (`19` §0 / `01` §5) that a phase closes on a real test run, not on a doc comment or a chat claim that the code looks correct. Track 6a is the first Phase 6–8 item closed under `20`'s execution plan.

## D11 — Discount/scholarship application order relative to part-payments

**Decision:** Discounts and scholarships are applied at fee-assessment time, before any payment is recorded. A `fee_assessment`'s `amount_due` is computed once as `base_amount − discount_amount − scholarship_amount`, and that discounted figure is the fixed target part-payments pay down. `amount_remaining` is always `amount_due − sum(payments)` — never a function of when a discount was granted relative to a payment.

**Rationale:** `19` §9 flagged this as genuinely product-level (open question 1) since it directly affects how "amount remaining" is computed and displayed to parents in the fee portal (discovery §11). Fixing the discount at assessment time keeps `amount_due` a single stable number for the life of the assessment — parents see one total from day one, not a total that can shift after they've already made payments. **Consequence for schema and process:** if a discount or scholarship must be granted *after* an assessment already has payments recorded against it, that's handled as a **new/adjusted `fee_assessment`**, not a retroactive mutation of `discount_amount`/`scholarship_amount` on the original — the write path for that adjustment is a build detail for `22`, not a schema field.

## D12 — Debt roll-over at year-end

**Decision:** Automatic. At academic-year rollover, any `fee_assessment` with `amount_remaining > 0` in the closing session has that balance automatically carried into a new fee assessment in the opening session, tagged as rolled-over debt. No admin action is required to trigger it.

**Rationale:** `19` §9 flagged this as open question 2, citing discovery §11's "Previous Sessions → rolled-over balance" as evidence parents are expected to see this reflected without a manual step. Automatic rollover matches that expectation and avoids a silent gap where a balance simply stops being visible if an admin forgets to trigger it manually. **Consequence for schema:** the rollover process needs a traceable link from the new session's rolled-over assessment back to the originating session's assessment (so `13.3`'s "previous-session debt" and "amount rolled over into next session" cash-flow figures can be computed, not just displayed as an opaque number) — detailed in `22`.

## D13 — Payment processor confirmation

**Decision:** Paystack is confirmed as the payment processor for online payments. Build directly against it — no processor-agnostic abstraction layer is required for Phase 6d's initial build.

**Rationale:** `19` §9 flagged this as open question 3 specifically because payment-processor integrations are the kind of external fact that can go stale between discovery and build — confirmed current as of this decision, closing that risk for this track. If a second processor is needed later, that's an additive change to the `payments` table's `processor` field (already enumerable, not hardcoded to a single value — see `22`), not a schema rewrite.

## D14 — Track 6d (Fees, Payments, Finance) built and verified

**Status:** Built per `19-discovery-hierarchy-gap-closure-plan.md` §9 and `20-phase-6-8-execution-prompt.md` §5's kickoff prompt, against the architecture (`21-schoolos-finance-architecture.md`) and schema (`22-schoolos-finance-schema.md`) docs and decisions D11–D13: `fee_categories`, `fee_assessments`, `discounts`/`scholarships`, `payments`, `expenses` tables; `FeeCategory`/`FeeAssessment`/`Discount`/`Scholarship`/`Payment`/`Expense` models; `FeeAssessmentRepository`, `PaymentRepository`, `FeeRolloverRepository`, `ScholarshipRepository`, `ExpenseRepository`; Admin/ParentPortal/StudentPortal controllers plus a Paystack webhook controller; every fee/payment route resolving a single record by ID carries `scope.checked`, per `21` §5's extension of the Phase 1 scope-enforcement lesson.

**Test gate executed:** `php artisan test` (full suite, not just `Phase6dTestGateTest` in isolation) run against a real PHP/Composer environment — 375 passed, 1103 assertions, 0 failures. `Phase6dTestGateTest`'s seven rows (D11's amount-due immutability, D12's rollover idempotency, D13's Paystack-confirmation-before-write, tenant/relationship scope on every fee/payment endpoint) all pass, alongside the fuller `FeeAssessmentRepositoryTest`/`FeeRolloverRepositoryTest`/`PaymentRepositoryTest`/`PaystackWebhookControllerTest` and the Admin/ParentPortal/StudentPortal controller test files those rows point to.

**Two real defects were found and fixed before this run went green, both worth recording so the pattern is caught earlier next time:**
1. Nearly every finance-domain test file was saved without proper PascalCase (e.g. `Feecontrollertest.php`, some with a stray trailing space before `.php`), so PHPUnit's default `*Test.php`-suffix directory discovery silently skipped them on a case-sensitive filesystem — they only ever ran on the case-insensitive Windows dev environment they were authored on. Renamed all fourteen affected files to match their (correctly-cased) class names; no code changes.
2. `ParentPortal\FeeController::index()`/`show()` were typed `: Response` but returned `view(...)` on the non-JSON branch — `Illuminate\View\View` isn't a `Response` instance, so PHP's strict return-type check threw a `TypeError` before Laravel's router could convert it, surfacing as a 500 on every real (non-JSON) parent fee-portal request. Retyped both to `View|Response`. A related test bug (`FeeRolloverRepositoryTest` passing a description string as `assertDatabaseCount()`'s third argument, which Laravel treats as a connection name) masked as an unrelated "database connection not configured" error and was fixed alongside it.

**Rationale:** Recorded here, in the same "built and verified" format D9/D10 use, per this register's own binding rule (`19` §0 / `01` §5) that a phase closes on a real test run, not on a doc comment or a chat claim that the code looks correct. Track 6d — the largest single gap `19` identified and the one flagged as its own mini-project with the longest lead time — is now closed. Per `20` §8's summary execution order, this unblocks 7c's fee-dependency question (admission-time fee acknowledgment can now depend on the real fee system rather than a placeholder checkbox).

## D15 — Promotion rule grammar: single aggregate threshold sufficient for v1

**Decision:** A single aggregate-threshold rule (discovery §9.1's example: "aggregate ≤ X promoted," implemented as `criteria.min_aggregate`) is sufficient for v1 — no richer rule grammar (per-subject thresholds, attendance-based eligibility) is required at this time.

**Rationale:** `19` §8 flagged this as needing a small round of school interviews before schema. `promotion_rules.criteria` is still stored as JSON, not a flat column, specifically so a richer grammar can be added later without a second migration if this decision is revisited — see the `2026_09_02_000001` migration's own doc comment. This decision does not resolve the separate open item `19`/`20` §6 flagged about CBT exam delivery (`23-schoolos-exam-domain-map.md` §2/§5) — that remains unconfirmed and is not addressed here.

## D16 — Admission-time fee acknowledgment ties to the real `fee_categories` table

**Decision:** Admission-time fee acknowledgment ties to the real `fee_categories` table (school-scoped, already built per D14), not a placeholder checkbox — an applicant acknowledges the specific fee categories the school has defined (tuition/medical/uniform/exam/other), by category id, at submission time.

**Rationale:** `19` §10 and `20` §6 both gated Track 7c on whether admission-time fee acknowledgment depends on the full fee system existing or ships as a placeholder checkbox pending real fee integration. `16` D14 confirms Track 6d (Fees/Payments/Finance) is now built and test-gated, so 6d closed before 7c started — the "ship as checkbox pending real fee integration" fallback `19` §10 offered is no longer the cheaper path. Building against the real `fee_categories` table from the start avoids a second migration later to convert checkbox-acknowledgment rows into category references.

## D17 — Track 7 remediation pass: file-casing fix actually applied, Track 7c built (test gate not yet run)

**Status:** `24-schoolos-track-7-remediation-prompt.md` was picked up as a working prompt for three items. Findings and work done on each, in that document's order:

1. **§1 (re-verify `23` against the current codebase):** Re-derived independently, not accepted on the strength of `23`'s own existence. `exam_marks` still carries no delivery-mode/CBT columns, `TeacherPortal\ExamController::recordMark()` is still the only write path to `exam_marks`, and no proprietor-remark, PDF-export, or attendance-aggregate fields have been added since (the only other reader of `exam_marks` is `ExportFileGenerator`'s CSV export, not a PDF renderer). `23` §2–§4 remain current.

2. **§2 (file-casing fix on Track 7b) — a real defect, not just an unverified one:** `24` §2's own table describes the nine promotion-related files and two Blade views as already renamed/authored ("Fixed to" column, past tense). Direct inspection of the actual `schoolos.zip` codebase found this was **not true** — every file in that table still existed under its original broken name (`Promotionrule.php`, `Promotioncontroller.php`, the trailing-space and misspelled variants, etc.), and none of the correctly-cased target files existed. `routes/web.php` already referenced the correct class names throughout (`use App\Http\Controllers\Admin\PromotionController as AdminPromotionController;` and so on), confirming this was purely a filesystem-casing gap, not a routes/logic gap. All nine files were renamed to match their class names in this pass; the two existing Blade views (`parent/promotions/Index.blade.php`, `student/promotions/Show.blade.php`) were renamed to lowercase; the two genuinely-missing views (`parent/promotions/show.blade.php`, `student/promotions/index.blade.php`) were authored fresh, adapted from their sibling portal's view. A broad `app/`-wide scan (matching every file's declared class name against its filename) found no further instances of this pattern outside the promotion track.

3. **§3 (Track 7c — Admission/Enrollment):** Built per `19` §10 and discovery §12, using D16 above: `admission_applications` table/model, `AdmissionApplicationRepository` (`submit()`/`markUnderReview()`/`reject()`/`withdraw()`/`accept()`), `App\Exceptions\Admission\MissingDecisionReasonFailure`, `Public\AdmissionApplicationController` (guest-middleware, `schools.short_code`-scoped) and `Admin\AdmissionApplicationController`, routes (every by-id admin action carries `scope.checked`), Blade views for the public form and the admin index/show, and `tests/Feature/Phase7cTestGateTest.php` with one test per `24` §3's five gate items.

**What this status update does *not* claim:** unlike D9/D10/D14/D15's "built and verified" entries, **no `php artisan test` run has been executed against this pass** — no PHP/Composer runtime with network access to `repo.packagist.org` was available in the environment this remediation happened in (the same gap `24` §2 itself flagged, for a different underlying reason: environment network policy rather than a missing PHP binary). Every file above passes `php -l` (syntax-valid), and `24` §2's file-casing fix was verified by direct filesystem inspection and cross-reference against `routes/web.php`'s existing `use` statements — but neither of those is a substitute for a real test run. Per this register's own binding rule (`19` §0 / `01` §5), Track 7c is **built, not yet verified**, and Track 7b's file-casing fix is **corrected, not yet re-verified by test**. Both require a real `php artisan test` run (`Phase7cTestGateTest` and `Phase7bTestGateTest` respectively, then the full suite) before either can be recorded as closed in the D9/D10/D14/D15 sense.

**First real `Phase7cTestGateTest` run, one defect found and fixed:** run against a real PHP/Composer environment outside this pass's own sandbox, 4/5 tests passed on the first run; `test_accept_creates_exactly_one_student_user_and_one_enrollment` failed with a `NOT NULL constraint failed: users.role_id` `QueryException`. Cause: `AdmissionApplicationRepository::accept()` resolves the `student` Role by key (`Role::where('key', 'student')->value('id')`) rather than creating one — correct per D4 (roles are global platform vocabulary, seeded once by `RoleSeeder` in every real environment) and consistent with how every other repository in this codebase treats roles. The failing test's own fixtures never created a `student` Role row before calling `accept()` — every other accept()-adjacent fixture in the suite gets one as a side effect of `makeRoleUser('student', ...)`, but this test creates the student user *through* `accept()` itself, so nothing upstream of the call had a reason to touch that role. Fixed by adding an explicit `$this->makeRole('student')` to that one test, matching the same "ensure the role row exists" pattern every other fixture already relies on — no repository or application code changed, this was a test-fixture gap, not a product defect. `Phase7bTestGateTest` and the file-casing fix from `24` §2 have not yet been run/confirmed as of this note.

**Rationale:** Recording the specific defect and its real cause (test fixture, not production code) here — rather than either silently fixing it and staying quiet, or vaguely noting "a test failed and was fixed" — keeps this entry auditable the same way D14's own "two real defects were found and fixed" paragraph is: a future reader can tell from this register alone that the fix was narrow and where it landed, without having to reconstruct that from a diff.

**Second real defect found by the same test run — `Phase1TestGateTest`'s row 8 route-table lint:** the same full-suite run that surfaced the `student` Role fixture gap above also failed `test_every_show_by_id_route_has_a_registered_scope_check` against `apply/{shortCode}` (`Public\AdmissionApplicationController@create`). Cause: that lint is a mechanical URI-shape check — any `App\Http\Controllers` route whose URI contains a route parameter is required to carry `scope.checked`, with no built-in exemption for guest/unauthenticated routes (see `Phase1TestGateTest`'s own doc comment on row 8). The original design reasoned by analogy to `PaystackWebhookController`'s exemption, but that route is exempt because it has *no* URI parameter at all (`/webhooks/paystack`), not because of any guest-route carve-out — a reasoning error caught only by the real test run, not by the earlier static read. Adding `scope.checked` to satisfy the lint would have been false advertising: `MarkScopeChecked`'s own doc comment defines the marker as asserting the controller calls `ScopeService::tenantScope()`/`relationshipScope()`, which a guest controller with no authenticated actor cannot do. Fixed by redesigning the public route to carry the school's `short_code` as a `?school=` query parameter instead of a URI segment (`/apply?school=ACME` instead of `/apply/ACME`) — genuinely not a single-record-by-ID route in the shape row 8 checks for, not a workaround around it. `Public\AdmissionApplicationController`, its route registration, the public Blade form, and `Phase7cTestGateTest`'s two HTTP-level tests were all updated to match.

**Rationale:** Recording this here, honestly incomplete rather than rounded up to "verified," is the entire point this register and `24` itself exist to enforce — a status update that claimed a test-gate close without a test run would be exactly the failure this document chain (`19` §0 → `24`'s own binding rule → this entry) was written to stop happening again.

---

## Schema and MVP-scope corrections adopted alongside these decisions

- `subjects`, `academic_terms`, and `audit_logs` tables added to the canonical schema (audit §12) — see updated `13-schoolos-database-schema.md`.
- Master Plan's Phase-1/MVP list (§34, §46) is subordinated to `14-schoolos-implementation-plan.md`'s phase sequencing. Finance, exams/results, and biometric staff attendance are **not** in Phase 1.
- Parent web dashboard: **build it**, scoped to attendance + announcements + profile only in its first version.
- Exams/Timetable: **core**, not an addon/plugin boundary.
- Staff attendance: schema reserves a `method` enum for QR/Selfie/Face/Fingerprint/GPS, but only QR and Selfie ship without a further privacy/retention design pass.
- Student vs. parent API surface: **dedicated namespaces**, not shared — `ScopeService.relationshipScope` gets a self-only variant for students, distinct from the self-or-linked-children variant for parents.
- Phase 5 (Communication): **built and gated**, not merely drafted — see D6's status update above. `Feedback` (parent/student-authored, ownership-checked) and the `content_read_receipts` write path (visibility-checked via `relationshipScope()`) are both implemented, not deferred placeholders.

These decisions are binding for Phase 1 scaffolding (below) and should only be revisited with an explicit reason, not silently overridden by a later phase's convenience.

## D18 — `19`'s discovery-hierarchy inventory table corrected; D17's promotion file-casing claim only partially true, now actually fixed

**Status:** Two follow-ups, found together when `19-discovery-hierarchy-gap-closure-plan.md` §2's inventory table was checked against the live `schoolos.zip` codebase rather than against prior planning-doc prose:

1. **`19` §2's table was stale.** It still marked §1.3 (Calendar), §2.2–2.3 (Student ID/QR), §7 (Scheme of Work/e-textbooks), §9 (Promotion), §10 (Fees/Payments), §11 (Parent Fee Portal), §12 (Admission), and §15 (Backup) as not built — all of which D10, D14, D15, D16, and D17 above already record as built (several test-gated). The table had simply never been updated as those tracks closed. Corrected directly in `19` §2, with a dated update note explaining what changed and why, rather than silently rewritten — matching this register's own standard of leaving a visible trail rather than erasing the earlier (incorrect) state. Two gaps remain genuinely open against the full discovery hierarchy: §8's CBT-flow/PDF-result/proprietor-remark trio (re-confirmed, not just carried over — see `23` and D17 item 1), and §14's WhatsApp integration, which stays a deliberate decision-gate per `20` §7, not an oversight.

2. **D17 item 2's "all nine files renamed" claim was not fully accurate.** Direct filesystem inspection of `schoolos.zip` found two of the nine still present under their broken names: `app/Http/Controllers/Admin/Promotioncontroller .php` (stray trailing space) and `app/Http/Controllers/ParentPortal/Promotioncontrolle.php` (misspelled/truncated). Both were byte-identical duplicates of the correctly-named `PromotionController.php` files already sitting alongside them — so this was dead code, not a routing or logic defect (PSR-4 autoloading, keyed off `App\` → `app/` with class-name-matched filenames, only ever resolved the correctly-cased files; `composer.json`'s `autoload.psr-4` confirms no classmap fallback that would have picked up the stray files another way). Both files deleted; re-scanned `app/` for the same stray-space/misspelling pattern app-wide and found no further instances.

**Rationale:** Recorded as its own entry, not folded into D17, because it corrects D17 rather than extends it — D17 claimed a closed state that a direct re-check showed wasn't fully true, and this register's own binding rule (`19` §0 / `01` §5) is that a status only counts as closed once verified against the actual artifact, not a prior doc's say-so. That standard applies to this register's own past entries as much as to `19`'s prose.

## D19 - Audit item 8 and quick fix item 2 built and verified

**Status:** Built per `25-audit-gap-closure-execution-prompt.md` Group 6 and Quick Fix item 2. Admins now have a lesson-document upload path (`Admin\LessonPlanController::create()`/`store()`, `/admin/lesson-plans/create`, `/admin/lesson-plans`) that stores an uploaded document on the local disk, creates a school/class/subject-scoped `lesson_plans` row, and marks the admin-authored material approved immediately so the existing teacher-submission `approve()`/`reject()` review workflow remains unchanged. `lesson_plans.document_path` was added to schema/model storage for the uploaded document.

Quick Fix item 2 is corrected by removing outstanding-balance data from the student fee portal. `StudentPortal\FeeController` no longer computes or passes `amount_remaining`, the student fee views no longer render amount due, amount remaining, owed text, or payment history, and the parent fee portal remains unchanged with its balance/payment visibility intact.

**Test gate executed:** Focused run `php artisan test tests\Feature\Admin\LessonPlanControllerTest.php tests\Feature\StudentPortal\FeeControllerTest.php tests\Feature\ParentPortal\FeeControllerTest.php` passed - 23 tests, 70 assertions. Full-suite run `php artisan test` passed - 413 tests, 1275 assertions, 0 failures.

**Rationale:** Recorded in the same built-and-verified format as D9/D10/D14: these audit items close on executable tests and a full-suite run, not on code inspection alone.

## D20 - Super Admin platform portal built and verified

**Status:** Built as the first platform-operations slice for the already-modeled `super_admin` role. The role previously existed only as seed data plus the centrally-defined `ScopeService::tenantScope()` exemption from D5; there was no post-login destination, no navigation entry, no `SuperAdmin` controller namespace, and no platform UI for selecting a target school. That gap is now closed for core school operations.

Implemented:
- `App\Http\Controllers\SuperAdmin\DashboardController`, `SchoolController`, `SchoolAdminController`, and `AuditLogController`.
- `/super-admin/dashboard`, `/super-admin/schools`, `/super-admin/schools/{school}`, `/super-admin/audit-logs`, plus create/update/status/admin-creation write paths.
- Login routing for `super_admin` to `super-admin.dashboard`.
- Navigation entries for Platform, Schools, and Audit logs.
- Blade views under `resources/views/super-admin`.
- Audit logging for `school.created`, `school.updated`, and `school_admin.created`; school suspension/reactivation continues to use `AuthenticationService::setSchoolStatus()` and its existing `school.suspended`/`school.reactivated` audit rows.

**Test gate executed:** focused run `php artisan test tests\Feature\SuperAdmin\SuperAdminPortalTest.php tests\Feature\Phase1TestGateTest.php` passed - 16 tests, 117 assertions. Follow-up auth/navigation run passed - 10 tests, 20 assertions. Full-suite run `php artisan test` passed - 421 tests, 1304 assertions, 0 failures.

**Rationale:** This is the concrete implementation of D5's platform-superadmin model at the product surface level: `super_admin` remains schoolless (`school_id = NULL`) and globally scoped, but school-affecting writes now require an explicit target school in the route (`/super-admin/schools/{school}/...`) rather than widening the school-admin `/admin` routes. That keeps Ground Rule 0 intact: school-scoped writes derive their school from either the authenticated school admin's session or the superadmin-selected school route model, never a client-submitted `school_id` field.

## D21 - School profile baseline built and verified

**Status:** Built against the discovery hierarchy's school-profile baseline and the Master Product Plan's school-profile fields. `schools` now stores `initials`, `logo_path`, `location`, `google_maps_url`, and `school_type`; `School` mass assignment allows those fields; superadmins can create/update them from the platform school forms; school admins can update their own school profile from `/admin/school-profile`; and both paths write audit log rows that include the profile fields before/after the change. Logo uploads are stored on the public disk and validated as image file MIME types without requiring PHP's GD extension in tests.

**Test gate executed:** focused run `php artisan test tests\Feature\Admin\SchoolProfileControllerTest.php tests\Feature\SuperAdmin\SuperAdminPortalTest.php` passed - 11 tests, 39 assertions. Full-suite run `php artisan test` passed - 424 tests, 1318 assertions, 0 failures.

**Rationale:** This closes the concrete school-identity fields that were present in the product/discovery materials but not yet represented in the live school-management forms. The write paths keep the same school-source discipline as D20: school admins derive the target school from their authenticated session, while superadmins select a school through the platform school-management surface, never by a submitted `school_id`.

## D22 - Parent receipt upload built and verified

**Status:** Built for discovery hierarchy section 10.2's "Upload payment receipts where applicable" path. The existing parent payment route now accepts a `receipt_upload` mode after resolving the target `fee_assessment` through the same parent tenant/relationship scope as Paystack payments. Uploaded JPG/PNG/PDF receipts are stored on the public disk under `payment-receipts`, and `PaymentRepository::submitManualReceipt()` records a pending manual payment row with `receipt_upload_path`. Pending parent-uploaded receipts do not reduce `amount_remaining`; admin-confirmed manual payments remain the path that pays down the balance.

**Test gate executed:** focused run `php artisan test tests\Feature\ParentPortal\PaymentControllerTest.php tests\Feature\PaymentRepositoryTest.php tests\Feature\ParentPortal\FeeControllerTest.php` passed - 26 tests, 74 assertions. Full-suite run `php artisan test` passed - 427 tests, 1335 assertions, 0 failures.

**Rationale:** This closes the receipt-upload half of flexible/manual parent payments without weakening the finance trust boundary: a parent can submit evidence, but the ledger effect still requires either Paystack verification or an admin-recorded manual payment.
