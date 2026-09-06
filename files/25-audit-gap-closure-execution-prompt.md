# 25 — Audit Gap-Closure Execution Prompt

**Purpose:** Working prompt/brief for whoever (engineer, PM, or AI assistant) picks up the 9 outstanding items from `school_management_system_audit_report.md`. Paste the relevant group below into a session when starting that piece of work. Written in the same spirit as `24-schoolos-track-7-remediation-prompt.md`'s ground rule: nothing here closes on a doc comment or a chat claim that the code looks correct — it closes on a real `php artisan test` run and a real read of the file that's supposed to exist.

**Source of truth for scope:** `school_management_system_audit_report.md`, cross-checked directly against `schoolos.zip` before this document was written — confirmed no `Admin\TimetableController`, no `Admin\ExamController`, no `TeacherPortal\CalendarEventController`, and that `ExamMark` still stores only flat `marks_obtained`/`max_marks` with no component/weighting/status fields. Fees, Promotion, and Admission — all gaps in the earlier `19`/`24` docs — are confirmed already built in the current `schoolos.zip` and correctly do **not** appear in this audit's gap list.

**Housekeeping found in passing, not part of the audit's 9 items:** `app/Http/Controllers/Admin/Promotioncontroller .php` (trailing space in filename) is a dead leftover from the file-casing defect `24` §2 already fixed once — `PromotionController.php` exists correctly alongside it. Delete the stray file before starting; run `composer dump-autoload -o` after and confirm no warnings.

---

## 0. Before starting anything

Read, in order:
1. `school_management_system_audit_report.md` — the full findings this prompt is built from.
2. `13-schoolos-database-schema-v2.md` and `23-schoolos-exam-domain-map.md` — existing schema/domain conventions for the exams surface Group 1 extends.
3. `app/Models/AttendanceCorrection.php` and `app/Models/AuditLog.php` — the existing audit-trail pattern Group 1's edit trail should mirror, not reinvent.
4. `16-schoolos-decisions-register.md` — check for any standing decision (tenant scoping, `school_id` never client-supplied, etc.) that bears on new controllers before writing one.

Every group below still needs a real test run before being called done — static/grep-only verification is exactly the failure mode `24` §2 documented and had to remediate once already.

---

## 1. Group 1 — Exam / Result Governance (audit items 4, 5, 6, 7, 10)

**Why grouped:** items 4 (CA/Exam weighting), 5 (admin+teacher edit with audit trail), 6/7 (review-before-publish, edit-after-publish), and 10 (result PDF export) all read and write the same `ExamMark` surface through a still-nonexistent `Admin\ExamController`. Building them as one schema + controller pass avoids migrating the same table twice.

**Current state (confirmed directly, not inferred):**
- `exams`/`exam_marks` have no `exam_type`, no weight/percentage fields, no aggregation logic (audit item 4).
- No `Admin\ExamController` exists at all — administrators cannot currently view or edit results (audit items 5, 6, 7).
- `ExamMarkRepository::record()` uses `updateOrCreate()` with no audit logging, unlike `AttendanceCorrection`'s existing pattern (audit item 5).
- `ExamMark` has no status field (draft/reviewed/published) — marks go straight from teacher entry to parent/student visibility (audit items 6, 7).
- No PDF/download functionality anywhere in the student or parent exam controllers/views; `ExportJobController` only produces CSV backups, not student-facing result PDFs (audit item 10).

**Build implies:**
```
exam_components         -- school-configurable: name (CA, Exam, ...), weight_percent, sums to 100 per school
exam_marks.exam_component_id   FK, not null
exam_marks.status        enum(draft, reviewed, published), default draft
exam_result_audit_logs   -- or extend the existing AuditLog model: who, what changed, previous value, new value, when
```
Aggregation (CA 40 + Exam 60 = 100 total) is computed from `exam_components` per school, not hardcoded — the discovery hierarchy's own example numbers are illustrative, the weighting must be school-configurable per the audit's explicit requirement.

**Workflow to build:**
1. Teacher enters marks against an `exam_component` → `status = draft`.
2. Admin reviews via `Admin\ExamController` → can edit (writes an audit-log row: who/what/previous/new/when, mirroring `AttendanceCorrection`) → `status = reviewed`.
3. Admin publishes → `status = published`.
4. Parent/student portals only ever read `published` rows — this is the visibility gate that currently doesn't exist.
5. Student/parent can download the published result as a PDF, scoped exactly like every other by-id action in this codebase (never a client-supplied `school_id`/`student_id`; resolve from the authenticated session).

**Test gate, one test per item:** (a) a school's `exam_components` weights are validated to sum to 100 before being saved; (b) a teacher-entered mark defaults to `draft` and is invisible to the parent/student portal; (c) an admin edit writes exactly one audit-log row with correct previous/new values; (d) `publish()` on a non-`reviewed` result is rejected; (e) parent/student PDF download only succeeds for `published` results belonging to their own linked student — test the cross-tenant/cross-student rejection explicitly, the same way `24` §3 test (b) does for admission.

**Suggested order within the group:** schema (`exam_components` + `status`) → audit logging on the existing edit path → `Admin\ExamController` (review/edit/publish) → PDF export last, since it depends on `published` status existing.

---

## 2. Group 2 — Admin Timetable Read View (audit item 3)

**Current state:** No `Admin\TimetableController`. The routes file's own comment already documents the admin surface as "write-path-focused, not a full read UI," naming timetable as one of the resources without an admin read view.

**Build implies:** A read-only `Admin\TimetableController@index`/`@show` over the existing timetable data (whatever `TeacherPortal\TimetableController` already reads from), scoped to the admin's own school. No new tables — this is a missing read surface over an existing write path, not a missing domain.

**Test gate:** admin can view all class timetables for their school; admin cannot view another school's timetables (standard tenant-scope test, same pattern as every other by-id admin action in this codebase).

---

## 3. Group 3 — Account Creation Forms (audit items 15, 16)

**Why grouped:** teacher/staff enrollment and parent enrollment are the same shape — create a `User` + a role-specific profile row — and were both flagged as missing for the identical reason (only `UserSeeder.php` / `Admin\ParentLinkController`'s link-only path currently exist).

**3a. Teacher & Staff Enrollment (item 15)**
Current state: no route/controller creates teacher/staff users from the admin interface; staff accounts only exist via the seeder.
Build: `Admin\StaffController` (or `Admin\TeacherEnrollmentController`, match existing naming convention) with a `create()`/`store()` form producing a `User` (role = teacher/staff) + associated staff profile, reusing `IdentifierService::generateStaffId()` (already built, per the audit's "already built" section — do not duplicate it).

**3b. Parent Enrollment (item 16)**
Current state: `Admin\ParentLinkController` only links an *existing* parent account to an *existing* student — no creation path.
Build: extend the admin parent flow (or a new `Admin\ParentEnrollmentController`) to (1) create/register a parent `User`, (2) capture parent details, (3) link to one or more students — steps 1–2 are the genuinely new part; step 3 reuses `ParentLinkController`'s existing linking logic rather than duplicating it.

**Test gate (both):** created accounts get the correct role and school scope; the parent-enrollment path can link to multiple students in one flow; duplicate-account attempts (same email/mobile) are rejected with a clear error rather than a silent duplicate `User` row.

---

## 4. Group 4 — Teacher Profile Page (audit item 17)

**Current state:** No teacher profile route or view anywhere in the codebase — the only profile-shaped implementation found is `StudentHealthProfile`, which is unrelated.

**Build implies:** `TeacherPortal\ProfileController@show`/`@update` — display and (where appropriate) edit the teacher's own profile fields. Sequenced after Group 3 since it's the natural continuation of the teacher-identity surface just built there, though it has no hard technical dependency on it.

**Test gate:** a teacher can view and update their own profile; a teacher cannot view or update another teacher's profile (by-id scope check, same pattern as elsewhere).

---

## 5. Group 5 — Teacher Calendar (audit item 14)

**Current state:** Working calendar implementations exist for Admin, Parent, and Student. No `TeacherPortal\CalendarEventController` and no `/teacher/*` calendar route exist.

**Build implies:** Mirror the existing Parent/Student calendar controller shape (read-only, tenant/term-scoped via the already-built `calendar_events` table and `ScopeService.tenantScope()`) under the teacher portal — no new schema, this is the smallest item in the list.

**Test gate:** teacher can view their school's calendar events; teacher cannot view another school's events.

---

## 6. Group 6 — Admin Lesson Document Upload (audit item 8)

**Current state:** `Admin\LessonPlanController` only provides `approve()`/`reject()` — a review workflow for teacher-submitted materials, not an authoring path for admins.

**Build implies:** Add `store()` (and `create()` for the form) to `Admin\LessonPlanController`, reusing whatever file-upload/validation logic `TeacherPortal\LessonPlanController::store()` already uses (audit confirms this teacher-facing path already works — mirror it, don't reinvent).

**Test gate:** admin-uploaded lesson documents appear correctly scoped to their school/class; the existing `approve()`/`reject()` review workflow is unaffected by the new upload path.

---

## 7. Quick Fix — Item 2 (Student Should Not See Fee Owing)

**Current state:** `StudentPortal\FeeController` deliberately exposes `amount_remaining` to students, with a code comment framing this as intentional ("any future student-facing fee view") — but the discovery document's fee-portal requirement was for parents, not students.

**Fix:** Remove or lock down the fee-owing view in `StudentPortal\FeeController` so students cannot see outstanding balances; confirm `ParentPortal`'s equivalent fee view is unaffected and still shows the correct data to parents.

**Test gate:** student-facing fee endpoint no longer returns `amount_remaining` (or the route/view is removed entirely, whichever the product decision lands on); parent fee portal test coverage still passes unchanged.

**Sequencing note:** this is independent of every other group and low-risk — it can be done first as a quick win, last as cleanup, or interleaved wherever convenient. It does not need to block or wait on Group 1 despite both touching "fees/results" adjacent surfaces.

---

## 8. Recommended overall sequence

| Order | Group | Depends on |
|---|---|---|
| 1 | Housekeeping — delete stray `Promotioncontroller .php`, `composer dump-autoload -o` | — |
| 2 | Group 1 — Exam/Result governance (items 4, 5, 6, 7, 10) | — |
| 3 | Group 2 — Admin timetable read view (item 3) | — |
| 4 | Group 3 — Teacher/staff + parent enrollment (items 15, 16) | — |
| 5 | Group 4 — Teacher profile page (item 17) | Group 3 (sequencing convenience, not a hard dependency) |
| 6 | Group 5 — Teacher calendar (item 14) | — |
| 7 | Group 6 — Admin lesson document upload (item 8) | — |
| 8 | Quick fix — item 2 (student fee visibility) | — (can move anywhere in the sequence) |

Group 1 is ordered first among the build groups because it is the largest, most central, and the only one requiring a real schema/migration pass; everything after it is either a missing read-view, a missing write-form, or a scope fix over data that already exists. Once each group's test gate passes on a real `php artisan test` run, add a closing entry to `16-schoolos-decisions-register.md` in the existing D9/D10/D14 "built and verified" format — citing actual test output, not a description of what the code should do, per `24`'s ground rule.
