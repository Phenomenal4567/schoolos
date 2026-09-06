# 21 — SchoolOS Finance Architecture (Fees, Payments, Finance)

**Status:** Draft, first pass. Produced per `19-discovery-hierarchy-gap-closure-plan.md` §9 and `20-phase-6-8-execution-prompt.md` §5's kickoff prompt, in the citation style `12-schoolos-architecture.md` established — every design decision below cites the specific discovery-doc section or `16` decision it responds to. The three decisions `19` §9 flagged as blocking (application order, roll-over trigger, processor confirmation) are now resolved as `16` D11–D13; this document builds on those, not around them. Schema follows in `22-schoolos-finance-schema.md`.

Per `19` §9's framing, this track is its own mini-project — architecture, schema, domain-map pass, and test gate, the same shape `12`/`13`/`16` used for the rest of the build, not a migration bolted onto an existing phase.

---

## 1. Scope, per discovery §10/§11/§13

Three consumer-facing surfaces, one shared data model underneath:

- **10 — School Fees & Payments** (school-admin authoring side): fee categories, flexible/part payment, Paystack integration, discounts, scholarships.
- **11 — Parent Fee Portal** (parent read/pay side): current-session totals, previous-session history and rolled-over balance, notifications.
- **13 — School Finance / Admin Dashboard** (admin reporting side): revenue, expenses, cash flow, transaction history.

Nothing here is built yet (`19` §9 — "not built at all," the largest single gap in that document). This pass designs all three surfaces against one consistent schema rather than three independently-shaped ones, since §11's parent view and §13's admin view are both read projections over the same `fee_assessments`/`payments` data §10 writes.

## 2. Fee assessment lifecycle — the one write path for what a student owes

**Design:**
```
FeeAssessmentService
  ├── assess(schoolId, academicYearId, studentId, feeCategoryId, baseAmount, discount?, scholarship?, actor)
  │     -> FeeAssessment   -- computes amount_due per D11 at creation time, never after
  └── adjust(existingAssessmentId, newDiscount?, newScholarship?, actor)
        -> FeeAssessment   -- D11's escape hatch: creates a new adjustment record,
                              never mutates amount_due on an assessment that already
                              has payments recorded against it
```

- Per **D11**, `amount_due` is fixed once, at assessment time, as `base_amount − discount_amount − scholarship_amount`. `amount_remaining` is always `amount_due − sum(payments against this assessment)` — a pure computed value, never independently stored, so it can never drift out of sync with the payments that actually exist (the same "derive, don't duplicate" instinct behind `12`'s `resolveIdentifier` pattern).
- Discounts (individual/percentage/fixed, discovery §10.4) and scholarships (full/partial/specific-exemption, discovery §10.5) are both inputs to `assess()`, not separate mutable state layered on afterward. A scholarship applied at the student-enrollment level (discovery §10.5) still resolves to a per-assessment `scholarship_amount` at assessment time — the enrollment-level scholarship is the *policy* ("this student has a full scholarship"), the assessment-level amount is the *fact* ("this specific term's tuition assessment was zeroed by it").
- If a discount or scholarship is granted after payments already exist against an assessment, `adjust()` does not silently rewrite `amount_due` (which would retroactively change a parent-visible number they may have already partially paid against per a different total). It creates a linked adjustment assessment instead — full mechanics are a build-time detail, not an architecture-level one, but the constraint is binding now so `22`'s schema doesn't need a later breaking change to support it.

## 3. Payments — Paystack plus manual, one ledger

**Design:**
```
PaymentService
  ├── recordOnlinePayment(assessmentId, paystackReference, actor)     -- webhook/callback-driven
  ├── recordManualPayment(assessmentId, amount, receiptUpload, actor) -- admin-entered, discovery §10.2
  └── PaymentRepository::forAssessment(assessmentId)                 -- the one read path payments
                                                                          are ever queried through
```

- Per **D13**, this builds directly against Paystack — no processor-abstraction interface for its own sake. The `payments.processor` column (see `22`) is still an enum rather than an implicit assumption, so adding a second processor later is additive to that enum and to `recordOnlinePayment`'s webhook handling, not a schema rewrite.
- "Pay online, pay manually, part-payment, upload a receipt" (discovery §10.2) are four surface behaviors over **one** `payments` table — a manual payment and an online payment differ only in `processor` and whether a `receipt_upload_path` is populated, not in table or write path. This avoids the "same underlying resource, multiple divergent code paths" shape `12` §2 flagged as GegoK12's authentication defect (three login endpoints that didn't agree with each other) — the finance domain gets one write path per resource for the same reason.
- Part-payments are just multiple `payments` rows against the same `fee_assessment` — no separate "partial payment" concept or table. `amount_remaining`'s computation (§2) already handles any number of partial payments correctly by construction.
- Paystack webhook handling verifies the transaction against Paystack's API before writing a `payments` row (never trusts a client-supplied "payment succeeded" callback alone) — this is a direct application of the same trust boundary discipline `12` §2 applied to authentication ("role is never accepted as client input"): payment confirmation is never accepted as client input either.

## 4. Debt roll-over — automatic, traceable

Per **D12**, roll-over is automatic at year-end, not admin-triggered. Design:

```
FeeRolloverJob (runs at academic-year transition, alongside existing AcademicYear rollover machinery)
  for each fee_assessment where amount_remaining > 0 in the closing academic_year:
    create a new fee_assessment in the opening academic_year
      - fee_category_id = a reserved 'rolled_over_debt' category (school-scoped, system-seeded)
      - base_amount = the prior assessment's amount_remaining
      - discount_amount = 0, scholarship_amount = 0   -- a rolled-over balance is not re-discounted
      - rolled_over_from_assessment_id = the originating assessment's id
```

- The `rolled_over_from_assessment_id` link (schema in `22`) is what makes discovery §13.3's "previous-session debt" and "amount rolled over into next session" cash-flow figures computable by tracing the chain, not a separately-maintained running total that could drift from the assessments it summarizes.
- A rolled-over assessment is a normal `fee_assessment` in every other respect — it shows up in the parent portal's "Previous Sessions → outstanding debt" view (discovery §11) and pays down via the same `PaymentService` as any other assessment. No parallel "debt" concept.
- Rollover is idempotent per academic-year transition (same discipline `13`'s `IdentifierService` doc comment applies to student-ID generation) — running the job twice for the same year boundary must not double-create rollover assessments.

## 5. Scope enforcement — extending the Phase 1 lesson to every fee/payment endpoint

`19` §9 names this explicitly as the domain where `12` §3a's scope-enforcement lesson (F18/F20/F21/F22) applies hardest: a parent must never view or pay against another student's fee assessment. This is not a new mechanism — it's the existing `ScopeService.relationshipScope()` (student/parent branch, already proven correct via `ChildrenController::showChildren()`'s intersect-before-query shape, `12` §3a) applied to every fee/payment controller action that resolves a single record by ID, from the first commit of this track rather than retrofitted after a finding — the explicit instruction `20` §5's kickoff prompt carries forward from the Phase 1 gate.

Concretely, before this track's own test gate closes, the `14` Phase 1 "every ID-resolving controller action has a registered scope check" lint/test pattern (`12` §3a) must cover every new route that resolves a `fee_assessment_id` or `payment_id`:
- `Parent\FeeController::show($assessmentId)` — must intersect against the caller's `student_parent_links`, same shape as `AttendanceController::index($student_id)`'s F20 fix.
- `Parent\PaymentController::store($assessmentId)` — a **write**, not just a read; the same check applies, since a scope gap on the pay-against-an-assessment path is strictly worse than a read-only IDOR (a parent could pay down, or worse, upload a fraudulent receipt against, another family's balance).
- Any future student-facing fee view — self-only variant, same as the existing student/parent split in `16`'s MVP-scope corrections.

Admin-side and reporting endpoints (§7) scope by `tenantScope()` only, matching `12` §3a's design (an admin's authority is school-wide, not per-student), but never omit even tenant scope — that's tier-3 (`12` §3a's "unscoped," F21/F22) and structurally unreachable everywhere else in this codebase; finance shouldn't be the exception.

## 6. Notifications — reuse Phase 5's audience/inbox mechanism, don't rebuild it

Discovery §11's notification list (outstanding fees, overdue payments, payment confirmation, new fees, upcoming deadlines) is five *triggers* over the **existing** notification inbox mechanism `18-schoolos-communication-domain-map.md` and `16` D6 already built and gated for Phase 5 (`NotificationController`, `content_read_receipts`, session-identity-scoped inbox with no by-ID route parameter — closing F27's IDOR by construction).

- Each trigger is a new domain event (`FeeAssessed`, `PaymentOverdue`, `PaymentRecorded`, `PaymentDeadlineApproaching`) dispatched by `FeeAssessmentService`/`PaymentService`, consumed by a new listener (`NotifyParentOfFeeEvent`, mirroring `NotifyParentsOfAbsence`'s shape) that writes into the same notification/inbox tables Phase 5 already built.
- No new inbox, no new read-receipt mechanism, no new by-ID route — this is additive triggers onto proven infrastructure, the same reuse instinct `20` §3 (Track 6c) applied to `content_read_receipts` for `learning_materials`.
- `PaymentOverdue`/`PaymentDeadlineApproaching` are the two triggers that need a scheduled check (not just an event fired on a write) — a daily job scanning `fee_assessments` with `amount_remaining > 0` past their due date, or approaching it, dispatching the corresponding event once per assessment per threshold crossed (not once per day it stays overdue — an idempotency guard, same shape as §4's rollover job, prevents notification spam).

## 7. Admin finance dashboard — read-only projections, no new write paths

Discovery §13's four views (Revenue, Expenses, Cash Flow, Transactions) are reporting queries over `fee_assessments`/`payments` (§2–§4) plus one new table, `expenses` (staff payments, operational, other — admin-facing, no parent visibility per `19` §9's build-implies list).

```
FinanceReportingService
  ├── revenue(schoolId, dateRange)     -> aggregates payments.amount by status/category
  ├── expenses(schoolId, dateRange)    -> aggregates expenses.amount by category
  ├── cashFlow(schoolId, dateRange)    -> revenue − expenses, plus outstanding/rolled-over
  │                                        debt figures traced through §4's rollover chain
  └── transactions(schoolId, dateRange)-> unified payments + expenses history, paginated
```

- `expenses` is deliberately a simple, admin-only table — discovery §13.2 lists categories (staff payments, operational, other) but no discount/scholarship/roll-over complexity, so it doesn't need `fee_assessments`' lifecycle machinery. It's scoped by `tenantScope()` only (school-admin/accountant-role authority, no student-relationship dimension).
- Every `FinanceReportingService` method takes `schoolId` from the caller's own scoped context (never client input directly), matching `13`'s Ground Rule 0 language verbatim — this dashboard is exactly the kind of aggregate-across-everything view where a missing tenant filter would be catastrophic rather than merely a single-record IDOR.

## 8. Explicitly deferred out of this pass

- A `school_role_overrides`-style per-school custom fee category taxonomy beyond discovery §10.1's five listed categories — `fee_categories` ships school-scoped and admin-creatable (so schools already have naming flexibility) without a separate override mechanism; revisit only if a real need surfaces, same deferral discipline `16` D1/D4 used.
- Refunds — not named anywhere in discovery §10/§11/§13. Not designed here; if it surfaces, it's a new `refunds` table linked to `payments`, not a retrofit of `amount_remaining`'s computation.
- Multi-currency — discovery gives no signal this is needed; `payments.amount` and `fee_assessments.base_amount` are single-currency numeric columns in `22`, per-school currency configuration deferred until a real requirement appears.

---

Schema for every table named above — `fee_categories`, `fee_assessments`, `payments`, `expenses`, plus the rollover linkage in §4 — follows in `22-schoolos-finance-schema.md`.
