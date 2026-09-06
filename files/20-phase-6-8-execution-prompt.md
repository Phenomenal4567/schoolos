# 20 — Phase 6–8 Execution Prompt

**Purpose:** This is a working prompt/brief for whoever (engineer, PM, or AI assistant) picks up Phase 6–8 work next. It encodes the dependency order and process rules from `19-discovery-hierarchy-gap-closure-plan.md` §13 and the conversation that produced it, so execution doesn't require re-deriving sequencing from scratch each time. Paste the relevant section below into a session when starting work on a given track.

**Ground rule, carried from `14` §0 and `01` §5, binding for everything below:** No schema gets written for any Phase 6–8 item without its own short domain-map pass first, in the style of `06`–`09`. No item starts build before its listed dependency (decision or prior pass) is actually closed — not assumed, not "probably fine." Every phase closes the same way Phases 1–5 did: decision (if any) → domain-map pass (if any) → build → test gate → mark closed in a `16`-style decisions-register entry, not by declaration.

---

## 0. Before starting anything

Read, in order:
1. `school_management_system_discovery_hierarchy.md` — the genesis document, for the section relevant to the track you're picking up.
2. `19-discovery-hierarchy-gap-closure-plan.md` — the specific numbered gap section for that track.
3. `16-schoolos-decisions-register.md` — for the *shape* of how decisions get recorded (D1–D9), since any new decision closed under this plan should be added here, not left in a chat log.
4. `14-schoolos-implementation-plan.md` §6 — for the standing rule this whole document operationalizes.

Do not start building from memory of the conversation that produced this plan. Re-derive from the docs each time — that's the whole point of the traceability chain `01`–`19` already established.

---

## 1. Track: 6a — School Calendar (no dependency — start immediately)

**Prompt:**
> Build the School Calendar feature per `19-discovery-hierarchy-gap-closure-plan.md` §3 and discovery doc §1.3. Add the `calendar_events` table as specified there (school-scoped, academic-year-scoped, optionally term-scoped). Wire read-only visibility to parent/student portals through the existing `ScopeService.tenantScope()` — no new scope mechanism needed. Admin-side CRUD is school-admin/principal role only. Write a test gate covering: (a) events are tenant-scoped correctly, (b) a parent/student can only see events for their own school, (c) `visible_to_parents = false` events don't appear in parent/student views. Add a short entry to `16-schoolos-decisions-register.md` marking this phase closed once the gate passes, following the D9-style "built and verified" pattern.

**No domain-map pass required** — 19.md already scoped this fully; it's additive to existing `AcademicYear`/`AcademicTerm` models.

---

## 2. Track: 8 — Backup & Data Management (no dependency — can start anytime)

**Prompt:**
> Build Backup & Data Management per `19-discovery-hierarchy-gap-closure-plan.md` §11 and discovery doc §15. This is infrastructure/ops work: an `export_jobs` tracking table (status, requested_by, school_id, date_range, file_path, expires_at) plus a background job for per-term/per-session/custom-range export, and an admin-triggered manual export + download endpoint. Before writing the job, resolve the one open decision: storage location and retention window for exports (flag to product/legal — same reviewer as the biometric-retention decision in track 6b below, since both are data-sensitivity calls). Do not build the export job until that's answered. Test gate: exports are correctly school-scoped (no cross-tenant export possible), expired exports are actually deleted per the retention window, manual and automatic triggers both produce identical output.

**No domain-map pass required** for the export mechanism itself — but the retention decision blocks the actual job logic, so scaffolding (table, endpoint stub) can start now; the working export logic waits on that answer.

---

## 3. Track: 6c — Scheme of Work + E-Textbooks (no dependency — can run in parallel with 6b)

**Prompt:**
> Build per `19-discovery-hierarchy-gap-closure-plan.md` §6 and discovery doc §7.1/§7.3. Add `scheme_of_work` (admin-authored, term/class/subject-scoped) and `learning_materials` (school-scoped, class/subject-scoped, nullable for school-wide materials) tables as specified there. Reuse the existing `content_read_receipts` mechanism (`13` §6) for read-tracking — do not build a new tracking mechanism. Confirm with `AnnouncementRepository::findVisibleTo()` whether `learning_materials` visibility routes through the same pattern before deciding it needs its own. Test gate: teachers can see the scheme for their assigned classes only (via existing `ScopeService` teacher branch); students/parents see materials scoped to their class only.

**No domain-map pass required** — reuses existing scoping and read-receipt patterns exactly.

---

## 4. Track: 6b — ID Cards + Staff Attendance (methods 2–4) — blocked on two decisions

**Do not start build.** Two decisions must close first:

**Decision prompt A — QR payload:**
> Decide what the student/staff ID card QR code encodes (discovery §2.3 lists three candidate uses — attendance, identification, verification — without picking one), and whether it's the same payload/verification service the staff-attendance QR method (discovery §5) uses. Record the decision in `16-schoolos-decisions-register.md` as a new D-numbered entry, same format as D1–D9, before any ID-card or QR-attendance code is written.

**Decision prompt B — biometric retention:**
> Before building geolocation, camera/liveness, or face verification for staff attendance (discovery §5), get product/legal sign-off on: how long face-verification images are retained, who can access them, and deletion-on-staff-exit behavior. This was the original blocking reason `16`'s close-out note gave for shipping only QR/Selfie in the current build — it's still unresolved. Record as a new D-numbered entry in `16` once answered. Do not write geolocation/face/fingerprint code before this closes; QR and Selfie already shipped and are unaffected.

**Once both decisions are recorded**, build prompt:
> With QR-payload decision [D-number] and retention decision [D-number] closed, build: (1) `users.student_id`/`users.staff_id` generation at enrollment per discovery §2.2's format, (2) ID card rendering (presentation-layer, from existing `User`/`ClassSection` data — no new domain table beyond the ID fields), (3) the remaining staff-attendance methods per the retention policy decided above. Test gate per `19` §4/§5's build-implies sections.

---

## 5. Track: 6d — Fees, Payments, Finance — own mini-project, longest lead time, start planning now

**This is not a build prompt yet.** Per `19` §9, this needs its own `12`/`13`-style architecture and schema pass before any migration is written — treat it as a project inside the project.

**Kickoff prompt:**
> Start the architecture pass for Fees/Payments/Finance per `19-discovery-hierarchy-gap-closure-plan.md` §9 and discovery doc §10/§11/§13. Produce two documents in the existing style: a `21-schoolos-finance-architecture.md` (design decisions, following `12`'s "cite the specific finding/requirement" convention) and a `22-schoolos-finance-schema.md` (following `13`'s table-by-table format). Before schema, resolve the three decisions `19` §9 flags: (1) discount/scholarship application order relative to part-payments, (2) automatic vs. admin-triggered debt roll-over at year-end, (3) confirm Paystack is still the intended processor. Apply the Phase 1 scope-enforcement lesson explicitly — a parent must never access another student's fee assessment; extend the "every ID-resolving controller action has a registered scope check" test pattern (`14` Phase 1 gate) to every new fee/payment endpoint from the start, not retrofitted after a finding.

Only after those two documents exist and the three decisions are recorded in `16` should build/schema work start on this track.

---

## 6. Track: 7a → 7b → 7c — Exams → Promotion → Admission (sequential chain)

**7a is first and is a domain-map pass, not a build:**

> Run a domain-map pass on the existing `Exam`/`ExamMark` models per `19-discovery-hierarchy-gap-closure-plan.md` §7 and discovery doc §8.1/§8.3, in the style of `06`–`09`. Confirm what's actually implemented vs. assumed: CBT-mode delivery vs. written/marks-entry-only, PDF result generation, proprietor/proprietress remark field, attendance-aggregate fields on results. Produce a short `23-schoolos-exam-domain-map.md` stating confirmed-vs-open findings, the same evidence-based format as `06`–`10`. Do not guess at CBT scope — if the finding is "marks-entry only was the intentional Phase 4 scope," record that as confirmed and flag CBT as a separate, explicitly-scoped decision for product, not an assumed future build.

**7b (Promotion) waits on 7a's findings**, specifically the CBT/scope decision, since promotion criteria evaluate the marks data 7a will have just mapped:

> Build Student Promotion per `19` §8 and discovery doc §9, using the confirmed marks-data shape from `23-schoolos-exam-domain-map.md`. Before schema: run a small round of school interviews (per `19` §8's decision-needed note) to confirm whether a single aggregate-threshold rule is sufficient or a richer rule grammar (per-subject thresholds, attendance-based eligibility) is needed — discovery doc gives one example only, not a full spec. Build `promotion_rules` and `promotions` tables per `19` §8's schema sketch, preserving the manual-override path discovery §9.2 requires. Test gate: automatic promotion correctly applies the school's rule; manual override always records `decided_by` and a reason; a promotion never happens without one of the two paths being explicit in the record.

**7c (Admission) waits on 6d's fee-dependency decision:**

> Build the admission/enrollment application workflow per `19` §10 and discovery doc §12, once track 6d has answered whether admission-time fee acknowledgment depends on the full fee system existing or can ship as a placeholder checkbox. Build `admission_applications` as specified in `19` §10, plus the conversion path from an accepted application into a `User` + `student_enrollments` row — this conversion step is new territory (nothing in `06`–`18` designed a not-yet-a-user-becomes-a-user flow) and deserves its own short domain-map pass before the conversion logic is written, even though the surrounding CRUD doesn't need one.

---

## 7. Track: WhatsApp — decision only, no build prompt yet

**Prompt (decision, not build):**
> Confirm with schools whether Phase 5's in-app notifications (announcements, `content_read_receipts`) are an acceptable replacement for WhatsApp-based communication, or whether they specifically want notifications pushed *through* WhatsApp (a Twilio/WhatsApp Business API integration, additive to Phase 5). Record the answer in `16-schoolos-decisions-register.md` as a new D-numbered entry regardless of outcome — "confirmed not needed, Phase 5 suffices" is as valid a closure as "build the integration," and either way it stops this line item from silently lingering as an open discovery note. Do not write any WhatsApp API integration code before this decision is recorded.

---

## 8. Summary execution order

```
Now, in parallel:
  6a (Calendar)         — build directly
  8  (Backup) scaffold  — build table/endpoint stub; export logic waits on retention decision
  6c (Scheme/E-Textbooks) — build directly
  6b decisions (QR payload, biometric retention) — resolve, don't build yet
  6d kickoff — start architecture + schema docs (21, 22), resolve 3 decisions
  WhatsApp — resolve decision only

Once 6b decisions close:
  6b build (ID cards + remaining staff-attendance methods)

Once 6d docs (21, 22) + decisions close:
  6d build (Fees/Payments/Finance)
  → unblocks 7c's fee-dependency question

Once 7a (exam domain-map pass) completes:
  7b build (Promotion)

Once 6d closes AND 7a/7b's exam-shape findings are in:
  7c build (Admission)
```

Every closed item gets a `16`-style register entry. Nothing in this document authorizes skipping that — the register is what lets the next person (or the next execution of this prompt) trust "closed" without re-reading the whole conversation history.
