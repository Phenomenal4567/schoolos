# 19 — Discovery Hierarchy Gap-Closure Plan

**Status:** Superseded for most rows below — see the update note directly under §2's table. Originally written as a draft for product/engineering sign-off, because `school_management_system_discovery_hierarchy.md` is the genesis document for this whole project — every subsequent artifact (`01` through `18`, and the codebase in `schoolos.zip`) is a descendant of it, either directly (the Master Product & Implementation Plan was written from it) or indirectly (the GegoK12 audit and `14`'s phase sequencing exist to make its features *safe* to build). Phases 1–5 are built and test-gated (`16` D9). This document is the map back to the genesis doc for everything Phases 1–5 deliberately left out, so those sections don't quietly stay "discovery notes" forever.

This is not a new discovery pass. It adds no new features beyond what `school_management_system_discovery_hierarchy.md` already specified. Its job is narrower: for each discovery-hierarchy section not yet built, state what exists today, what's missing, what schema/service work it implies, and what decision (if any) needs product sign-off before build — in the same traceable, cite-your-source style `12`–`18` already established, so this document can be checked against the codebase the same way those were.

---

## 1. How to read this document

For each gap:
- **Discovery source** — the exact section(s) of `school_management_system_discovery_hierarchy.md` it comes from.
- **Current state** — confirmed by grep/migration check against `schoolos.zip`, not assumed.
- **What's missing** — concretely, not "everything."
- **Build implies** — schema/service-level shape, following `12`/`13`'s existing conventions (tenant `school_id` on every table, write-path invariants over triggers per `16` D3, no client-supplied `school_id` per `14` Ground Rule 0).
- **Decision needed** — flagged only where the discovery doc itself left the question open (it says so explicitly in several places — see §9 below), or where the GegoK12 audit found a shape worth avoiding.
- **Suggested phase** — mapped onto `14-schoolos-implementation-plan.md`'s existing Phase 6–8 slots, since `14` §6 already reserved this exact scope ("Explicitly deferred, not scoped by this plan... need their own `05`-style migration review and `06`-style domain-map pass").

Every gap below still requires its own short domain-map pass before schema is written — this document identifies the gaps and their shape, it does not skip the process `14` §6 and `01` §5 both require.

---

## 2. Inventory: discovery hierarchy vs. current build

| Discovery §  | Feature area | Status in `schoolos.zip` |
|---|---|---|
| §1 School Setup & Administration | School profile, academic structure, calendar | ✅ School profile + academic structure built (Phase 1–2). ✅ School Calendar/Agenda (§1.3) built — `calendar_events` table, Admin/StudentPortal/ParentPortal controllers |
| §2 Student Management | Registration, records | ✅ Built (Phase 2) |
| §2.2–2.3 | Student ID + ID card (QR) | ✅ Built — `QrTokenService`, `Admin\IdCardController` |
| §3 Parent & Student Portal | Profile, attendance, results, fees, calendar, announcements | ✅ Profile/attendance/announcements/calendar/fees built. ❌ Results-download-as-PDF still not built (see §8 row) |
| §4 Student Attendance | Daily, class, subject attendance | ✅ Built (Phase 3) |
| §5 Staff/Teacher Attendance | Configurable methods (QR, geolocation, face, fingerprint) | 🟡 Schema reserved (`staff_attendance_records.method` enum), **not shipped** — only QR/Selfie planned, pending privacy/retention pass (`16` D-register close-out) |
| §6 Teacher & Staff Management | Enrollment, profile, dashboard | ✅ Built (Phase 2 core; dashboard views Phase 2/4) |
| §7 Academic Management | Scheme of work, lessons, e-textbooks | ✅ Built — `SchemeOfWork` model/controllers (own object, distinct from lesson plans) and `LearningMaterial` model/controllers (incl. `ContentReadReceipt`) across Admin/Teacher/Student/Parent portals |
| §8 Examination & Results | CBT vs written, result processing/delivery | 🟡 Exams/marks built (Phase 4). ❌ CBT-specific flow, PDF result generation, proprietor remark field still not built — re-confirmed independently in `23-schoolos-exam-domain-map.md` and `16` D17 item 1 |
| §9 Student Promotion | Automatic + manual promotion | ✅ Built and test-gated — `16` D14/D15/D17 item 2 |
| §10 School Fees & Payments | Fee categories, online payment, discounts, scholarships | ✅ Built and test-gated — `16` D14 (`FeeCategory`/`FeeAssessment`/`Discount`/`Scholarship`/`Payment`/`Expense`, Paystack integration + webhook) |
| §11 Parent Fee Portal | Balance, history, notifications | ✅ Built alongside §10 — `ParentPortal\FeeController` (see `16` D14's file-typing fix note) |
| §12 Enrollment/Admission | Digital admission workflow | ✅ Built and test-gated — `16` D16/D17 item 3 (`AdmissionApplication`, public application form + admin decision controller, `Phase7cTestGateTest`) |
| §13 School Finance/Admin Dashboard | Revenue, expenses, cash flow | ✅ Built — `Expense` model, `FinanceReportingService`, `Admin\FinanceDashboardController` |
| §14 Communication | School↔parent/teacher, WhatsApp | ✅ Announcements/notifications/feedback built (Phase 5). ❌ No WhatsApp integration — still genuinely absent as of this update, confirmed by direct grep; remains a decision-only item per §7 below |
| §15 Backup & Data Management | Auto/manual/downloadable backup | ✅ Built — `ExportJob` model, `ProcessExportJob`, scheduled-export and expired-export-pruning console commands |
| §16 Security & Access | Login formats | ✅ Built, generalized beyond the discovery doc's tentative formats (`AuthenticationService`) |
| §17 Role-Based Access Control | Roles list | ✅ Built (`16` D4) |

**Update, [this date] — table above corrected after direct re-verification against `schoolos.zip`, not `12`–`18`'s prose:** The original version of this table (quoted in full in `16` D17's own audit trail) was stale — §1.3, §2.2–2.3, §7, §9–§13, and §15 had all shipped since this document was first drafted, principally via the Track 6d fee/finance build (`16` D14), the promotion track (`16` D15), and the Track 7 remediation pass (`16` D17), none of which had been reflected back into this table. Re-verified this pass via `grep -ril` for `calendar_event|Promotion|Scholarship|Paystack|WhatsApp|backup|QrCode|LearningMaterial|SchemeOfWork|Expense|AdmissionApplication` against `app/` and `database/` in the actual `schoolos.zip`, not against planning-doc prose. Only two genuine gaps remain against the full discovery hierarchy: §8's CBT/PDF/proprietor-remark trio, and §14's WhatsApp integration (the latter is a deliberate decision-gate, not an oversight — see §7 below). This table should be treated as the current source of truth; where it disagrees with earlier prose in this document (§3 onward), the table wins.

**Also fixed in this pass (code hygiene, not a discovery gap):** `16` D17 item 2 claimed all nine promotion-track files with casing/naming defects had been renamed to match their class names. Direct re-inspection of `schoolos.zip` found two had not: `app/Http/Controllers/Admin/Promotioncontroller .php` (stray trailing space, wrong case) and `app/Http/Controllers/ParentPortal/Promotioncontrolle.php` (misspelled/truncated) — both byte-identical duplicates of the correctly-named `PromotionController.php` already sitting alongside them in the same directories. Since PSR-4 autoloading only ever resolved the correctly-named files, these were dead files, not a functional bug — but they contradicted D17's "fixed" claim and were removed. See `16` D18.

---

## 3. Gap: School Calendar / Agenda (discovery §1.3)

**Current state:** No `calendar_events` table, no controller surface in either audit corpus.

**What's missing:** Resumption date, term dates, mid-term break, exam periods, activities, holidays, closing date — a single school-scoped, session/term-scoped list of dated events, visible read-only to parents/students.

**Build implies:**
```
calendar_events
  id                PK
  school_id         FK, not null
  academic_year_id  FK, not null
  academic_term_id  FK, nullable   -- some events (resumption/closing) span the whole session
  title             not null
  event_type        enum(term_date, mid_term_break, exam_period, activity, holiday, closing_date, other)
  start_date        not null
  end_date          nullable       -- single-day events omit this
  visible_to_parents boolean, default true
```
Visibility to parents/students is read-only and tenant-scoped through the existing `ScopeService.tenantScope()` — no new scope mechanism needed, this is the simplest gap in this document.

**Decision needed:** None — this is a straightforward extension of the existing `AcademicYear`/`AcademicTerm` model. Low risk, self-contained.

**Suggested phase:** Phase 4 extension (Academic) or an early Phase 6 item — it has no dependency on Finance/Fees and could ship ahead of the rest of this document if sequencing flexibility is wanted.

---

## 4. Gap: Student & Staff ID Cards (discovery §2.2, §2.3, §5 "Staff ID Card")

**Current state:** Not built, and — unlike every other gap in this document — not mentioned anywhere in `01`–`18`. It appears to have been dropped during the Master Plan → audit reconciliation rather than deliberately deferred.

**What's missing:**
- Deterministic student ID generation (discovery's own example: `SCH+ADE+SS2+001` — school initials + first 3 letters of surname + class + sequence number).
- A `staff_id` equivalent for teachers/staff.
- ID card rendering (photo, ID, name, class/position, QR code) as a printable/downloadable asset.
- The QR code itself has no defined payload yet — discovery §2.3 lists three *candidate* uses (attendance, identification, verification) without picking one.

**Build implies:**
```
users.student_id      nullable, unique per school   -- generated at enrollment, not at registration
users.staff_id        nullable, unique per school   -- generated at staff enrollment
```
ID card rendering is a presentation-layer concern (PDF/image generation from existing `User`/`ClassSection` data), not a new domain table, once the ID fields exist.

**Decision needed:** What does the QR code encode, and does it double as the §5 staff-attendance QR method's payload? If yes, this gap and the Staff Attendance gap (§5 below) share one QR-verification service rather than two independent ones — worth deciding together rather than building the ID card first and retrofitting attendance onto it later.

**Suggested phase:** Phase 6, after the QR-payload decision above, and after Staff Attendance (§5 below) if the two are unified.

---

## 5. Gap: Staff Attendance — ship beyond QR/Selfie (discovery §5)

**Current state:** `staff_attendance_records.method` enum reserved in schema per `16` D-register close-out note, explicitly **not shipped**. Discovery §5's own insight — "do not force one attendance method on every school" — is already respected structurally (the enum is per-school configurable), just not built out beyond the two lowest-risk methods.

**What's missing:** Geolocation verification, camera/liveness verification, face verification — everything beyond QR and Selfie.

**Build implies:** A `staff_attendance_config` row per school selecting one or more enabled methods from the enum, plus method-specific verification services. Face/liveness and geolocation both carry data-retention and biometric-privacy obligations the QR/Selfie methods don't — this is exactly why `16`'s close-out note gated them behind "a further privacy/retention design pass," not behind engineering effort alone.

**Decision needed:** Biometric data retention policy (how long face-verification images are kept, who can access them, deletion on staff exit) needs product/legal sign-off before *any* face or fingerprint method ships — this was the original blocking reason and remains unresolved. Geolocation has a lighter version of the same question (precision stored, retention window).

**Suggested phase:** Phase 6, gated on the retention-policy decision above. Do not build face/fingerprint methods without it — this is the one gap in this document with a real compliance risk if skipped.

---

## 6. Gap: Scheme of Work + E-Textbooks (discovery §7.1, §7.3)

**Current state:** `LessonPlan` (Phase 4) covers §7.2 (lesson notes, topic-taught marking). Scheme of work as a distinct admin-uploaded, term/class/subject-scoped object is not built; e-textbooks/digital learning materials are not built.

**What's missing:** A `scheme_of_work` table (admin-authored, teacher-read) distinct from `lesson_plans` (teacher-authored, tracks actual delivery against the scheme). A `learning_materials` table for e-textbooks and other resources, school-scoped, class/subject-scoped, visible to students per `ScopeService`'s existing relationship-scope pattern.

**Build implies:**
```
scheme_of_work
  id                PK
  school_id         FK, not null
  academic_term_id  FK, not null
  class_section_id  FK, not null
  subject_id        FK, not null
  content           file/text
  uploaded_by       FK -> users (admin/principal role)

learning_materials
  id                PK
  school_id         FK, not null
  class_section_id  FK, nullable   -- nullable if some materials are school-wide, not class-specific
  subject_id        FK, nullable
  title             not null
  file_path         not null
  material_type     enum(textbook, notes, other)
```
Both reuse the existing `content_read_receipts` mechanism (`13` §6, built in Phase 5) for tracking whether students/parents have opened them — no new read-tracking mechanism needed.

**Decision needed:** None structural. Confirm whether `learning_materials` visibility should route through `AnnouncementRepository::findVisibleTo()`'s existing pattern or needs its own — likely the former, since `content_read_receipts` already generalizes across entity types per `13` §6.

**Suggested phase:** Phase 6.

---

## 7. Gap: Examination delivery specifics (discovery §8.1, §8.3)

**Current state:** `exams`/`exam_marks` built (Phase 4) per `13`'s schema. Confirmed built: scores, marks storage. **Not confirmed** from the reviewed corpus: CBT-mode delivery (vs. written-mode data entry), PDF result generation, proprietor/proprietress remark field, "number of times school opened / student attended" aggregate fields discovery §8.2 lists alongside scores.

**What's missing:** This gap needs its own short domain-map pass against the actual `Exam`/`ExamMark` models before scoping — the discovery doc's CBT-vs-written distinction (§8.1) is a delivery-mode question the existing schema may or may not already accommodate; this document doesn't have enough evidence from `12`–`18` to say either way, and shouldn't guess.

**Decision needed:** Whether CBT delivery (a timed, auto-graded question-and-answer flow) is in scope for Phase 6/7 at all, or whether Phase 4's `exams` build was intentionally scoped to marks-entry only (teacher enters scores from an offline exam) with CBT deferred indefinitely. This is a real product-scope call, not an engineering detail — flagging it here rather than assuming either answer.

**Suggested phase:** Domain-map pass first (unscheduled), then Phase 7 pending that pass's findings.

---

## 8. Gap: Student Promotion (discovery §9)

**Current state:** Not built. No `promotion_rules` or promotion-decision table in the reviewed schema.

**What's missing:** School-defined promotion criteria (discovery's example: "aggregate ≤ X promoted"), automatic identification of eligible students at year-end, and an admin override path for manual promotion (discovery §9.2 explicitly requires human override to remain available).

**Build implies:**
```
promotion_rules
  id                PK
  school_id         FK, not null
  academic_year_id  FK, not null
  standard_id       FK, not null   -- rule can vary per class/standard
  criteria          structured (e.g. min_aggregate, max_failed_subjects) -- shape needs its own short pass, discovery doc gives one example only, not a full rule grammar

promotions
  id                    PK
  student_enrollment_id FK, not null
  from_class_section_id FK, not null
  to_class_section_id   FK, nullable   -- nullable = held back
  method                enum(automatic, manual)
  decided_by            FK -> users, nullable   -- null for automatic, set for manual override
  reason                nullable        -- required when method = manual, per discovery §9.2's "exceptional cases" framing
```
This depends on Phase 4's exam/marks data existing first (promotion criteria evaluate aggregate scores), so it cannot precede that domain-map pass (§7 above) even though promotion itself is conceptually simpler.

**Decision needed:** The discovery doc gives one example criterion (aggregate threshold) but doesn't specify whether schools need a richer rule grammar (e.g., per-subject pass thresholds, attendance-based eligibility) or whether a single-threshold rule is sufficient for Phase 1 of this feature. Needs a small round of school interviews before schema, consistent with the discovery doc's own closing caution about not building on assumptions from a small sample.

**Suggested phase:** Phase 7, after §7's exam domain-map pass.

---

## 9. Gap: Fees, Payments, Finance (discovery §10, §11, §13)

**Current state:** Not built at all. This is the largest single gap in this document and the one `16`'s corrections section most explicitly deferred ("Finance, exams/results, and biometric staff attendance are **not** in Phase 1").

**What's missing:** Everything — fee categories, flexible/part payment, Paystack integration, discounts, scholarships, the parent-facing fee portal (current session + previous sessions + notifications), and the admin finance dashboard (revenue, expenses, cash flow, transaction history).

**Build implies (high level only — this needs its own `12`/`13`-style architecture and schema pass, not a sketch here):**
```
fee_categories       -- school-defined: tuition, medical, uniform, exam, other
fee_assessments       -- a fee_category applied to a student for a term/session, with amount owed
payments              -- Paystack transaction records + manual/receipt-upload payments, linked to a fee_assessment
discounts             -- individual or percentage/fixed, applied to a fee_assessment
scholarships          -- full/partial/specific-exemption, applied at the student-enrollment level
expenses              -- staff payments, operational, other — admin-facing, no parent visibility
```
This is explicitly the kind of domain the GegoK12 audit's Scope-enforcement lesson (F18/F20/F21/F22, `12` §3a) applies to hardest — a parent must never be able to view or pay against another student's fee assessment, and the same "every controller action that resolves a single record by ID has a registered scope check" test-gate pattern from Phase 1 must extend here before this phase's own test gate closes.

**Decision needed (several, genuinely product-level):**
1. Does discount/scholarship logic apply before or after part-payments are recorded (affects how "amount remaining" is computed and displayed)?
2. Debt roll-over across sessions (discovery §11 "Previous Sessions → rolled-over balance") — does this roll over automatically at year-end, or require an admin action?
3. Paystack is the only payment processor discovery interviews surfaced — confirm this is still current before building, since payment-processor integrations are exactly the kind of external fact that can go stale between discovery and build.

**Suggested phase:** Phase 6, but treat it as its own mini-project: architecture doc, schema doc, domain-map pass, and test gate, in the same shape `12`/`13`/`16` used for the rest of this build — not a single migration added to an existing phase.

---

## 10. Gap: Admission/Enrollment workflow (discovery §12)

**Current state:** `student_enrollments` write path exists (Phase 2) for placing an already-admitted student into a class for an academic year. The *pre-admission* application workflow (application form → parent/guardian info → medical info → fee acknowledgment → rules acknowledgment → document upload, discovery §12.1's 11 steps) is not built.

**What's missing:** An `admission_applications` table and workflow distinct from `student_enrollments` — a prospective student isn't a `User` yet, so this can't simply extend the existing enrollment write path.

**Build implies:**
```
admission_applications
  id                PK
  school_id         FK, not null
  status            enum(submitted, under_review, accepted, rejected, withdrawn)
  applicant_data     structured (name, DOB, parent/guardian info, medical info — pre-User)
  documents         array of file references
  submitted_at      timestamp
  decided_by        FK -> users, nullable
```
On acceptance, a new `admission_applications`→`User`+`student_enrollments` conversion path is the join point back into the existing Phase 2 schema — this conversion step is the part most worth a domain-map pass, since it's new territory (nothing in `06`–`18` designed a not-yet-a-user-becomes-a-user flow).

**Decision needed:** Whether steps 5–7 of discovery §12.1 (school fee, medical fee, uniform fee, acknowledged at application time) depend on §10's fee system existing first, or whether admission can ship independently with fee acknowledgment as a simple checkbox pending real fee integration later.

**Suggested phase:** Phase 6 or 7 — sequencing depends on the fee-dependency decision above.

---

## 11. Gap: Backup & Data Management (discovery §15)

**Current state:** Not built. No backup-scheduling or export mechanism in the reviewed codebase.

**What's missing:** Per-term/per-session/custom-range backup, with automatic, manual, and downloadable options.

**Build implies:** This is infrastructure/ops work more than domain-model work — likely a scheduled export job (per school, per term/session) writing to durable storage, plus an admin-triggered manual export and download endpoint. Doesn't need new core domain tables; needs an `export_jobs` tracking table (status, requested_by, date_range, file_path, expires_at) and a background job runner.

**Decision needed:** Storage location/retention for exports (this holds "years of student records" per the discovery doc's own framing — same category of data-sensitivity question as §5's biometric retention decision above, worth resolving with the same reviewer).

**Suggested phase:** Phase 8 (matches `14` §6's "operational modules" framing) — lowest urgency of the gaps in this document since it has no parent/student/teacher-facing surface and doesn't block any other feature.

---

## 12. Gap: WhatsApp integration (discovery §14.1, §14.5)

**Current state:** Not built. Internal messaging/announcements built instead (Phase 5).

**Important distinction — this may not actually be a gap.** Discovery §14 frames "schools currently rely heavily on WhatsApp" as a *finding about current behavior*, not a firm feature request — the document's own phrasing is "would value integrated communication," which Phase 5's announcements/notifications system already delivers as the integrated alternative. Re-read discovery §14.4/§14.5 before treating WhatsApp-specific integration (e.g., sending notifications *through* WhatsApp's API rather than replacing it) as a requirement — it may be that Phase 5 already satisfies the underlying need and this line item should be closed as "addressed differently," not carried forward as still-open.

**Decision needed:** Confirm with schools whether in-app notifications (Phase 5, shipped) are an acceptable replacement for WhatsApp, or whether they specifically want push-to-WhatsApp delivery of the same notifications (a Twilio/WhatsApp Business API integration, additive to Phase 5, not a redesign of it).

**Suggested phase:** No build until the decision above is answered — don't build a WhatsApp API integration speculatively.

---

## 13. Consolidated phase mapping

| Phase | Content | Depends on |
|---|---|---|
| 6a | School Calendar (§3) — can ship any time, no dependency | — |
| 6b | ID cards + QR payload decision (§4) + Staff Attendance methods 2–4 (§5) | Retention-policy decision |
| 6c | Scheme of Work + E-Textbooks (§6) | — |
| 6d | Fees/Payments/Finance (§9) — own mini-project | Its own architecture/schema pass |
| 7a | Exam domain-map pass, then CBT/PDF-result decision (§7) | Product scope decision |
| 7b | Student Promotion (§8) | Phase 7a |
| 7c | Admission workflow (§10) | Fee-dependency decision |
| 8 | Backup & Data Management (§11) | — |
| — | WhatsApp (§12) | Decision only — no build until confirmed |

This reuses `14-schoolos-implementation-plan.md`'s Phase 6–8 slots as originally reserved rather than inventing new phase numbers, and keeps the same rule `14` §6 already stated: no schema gets written for any of these without its own short domain-map pass first, in the style of `06`–`09`.

---

## 14. What this document deliberately does not do

It does not re-open Phases 1–5, which are built and gated (`16` D9). It does not invent features beyond what `school_management_system_discovery_hierarchy.md` specified. It does not resolve the product-level decisions it flags — those need the same kind of sign-off `16`'s D1–D9 got, not an engineering guess. And it does not skip straight to schema for the larger gaps (Fees/Finance, Exams/CBT, Admission) — those get their own domain-map and architecture pass exactly as `14` §6 requires for scope this size, consistent with the whole audit's founding principle: design against confirmed requirements, not assumptions.
