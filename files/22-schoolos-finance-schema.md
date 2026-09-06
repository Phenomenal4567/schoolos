# 22 — SchoolOS Finance Schema (Fees, Payments, Finance)

**Status:** Draft, first pass — companion to `21-schoolos-finance-architecture.md`. Follows `13-schoolos-database-schema-v2.md`'s table-by-table format and its Ground Rule 0 (`school_id` is `NOT NULL` on every tenant-scoped table, no exceptions — `05-database-map.md` §1's nullable-FK finding is not repeated here). Every table below is either named directly in `19-discovery-hierarchy-gap-closure-plan.md` §9's build-implies list or a direct schema consequence of a resolved decision (`16` D11–D13).

---

## 1. Fee categories — school-defined, per discovery §10.1

```
fee_categories
  id                  PK
  school_id           FK -> schools, NOT NULL
  key                 not null            -- 'tuition', 'medical', 'uniform', 'exam', 'other',
                                            -- plus one system-reserved 'rolled_over_debt' key
                                            -- (see §2, D12) — seeded per school, not user-editable
  label                not null
  is_system_reserved  boolean, default false  -- true only for 'rolled_over_debt';
                                                -- blocks admin edit/delete of that row
  deleted_at           soft-delete
  UNIQUE(school_id, key)
```

School-scoped, not global — discovery §10.1 lists five starter categories but the requirement is "school can create different fees," so category authorship is admin-facing CRUD, not a fixed platform enum (`21` §8 deliberately defers a cross-school override mechanism since this table already gives schools naming flexibility).

## 2. Fee assessments — the one row for "what a student owes," per discovery §10 and D11/D12

```
fee_assessments
  id                        PK
  school_id                 FK -> schools, NOT NULL
  academic_year_id          FK -> academic_years, NOT NULL
  student_id                FK -> users, NOT NULL          -- role_id must resolve to 'student';
                                                              -- checked at the one write path (§ below),
                                                              -- same invariant-at-write-path discipline
                                                              -- as D3's class_teacher_id
  fee_category_id           FK -> fee_categories, NOT NULL
  base_amount                decimal(12,2), NOT NULL
  discount_amount             decimal(12,2), NOT NULL, default 0
  scholarship_amount          decimal(12,2), NOT NULL, default 0
  amount_due                  decimal(12,2), NOT NULL       -- = base_amount - discount_amount -
                                                              -- scholarship_amount, computed and
                                                              -- fixed at INSERT time only (D11) —
                                                              -- never updated by a later discount/
                                                              -- scholarship grant once payments exist
                                                              -- against this row (see adjustment note below)
  due_date                    date, nullable
  rolled_over_from_assessment_id  FK -> fee_assessments, nullable, self-referencing
                                                              -- set only on rollover-created rows (D12);
                                                              -- traces a rolled-over balance back to its
                                                              -- originating session's assessment, so
                                                              -- 13.3's "previous-session debt" and
                                                              -- "amount rolled over" figures are computed
                                                              -- by tracing this chain, not a separately
                                                              -- maintained running total
  status                       enum(open, partially_paid, paid, void)
                                                              -- derived/cached from amount_remaining;
                                                              -- see note on amount_remaining below
  created_at, updated_at
```

**`amount_remaining` is deliberately not a column.** Per `21` §2, it's always computed as `amount_due − sum(payments.amount where payment.fee_assessment_id = this row and payment.status = 'confirmed')` — a stored column here could drift from the payments it's meant to summarize (the same "derive, don't duplicate" reasoning `12` applies elsewhere). `status` is a cached/denormalized convenience for filtering and display, recomputed by the same write path that records a payment — never independently settable.

**D11's adjustment path:** if a discount or scholarship needs to apply after payments already exist against an assessment, that assessment's `amount_due` is never rewritten. A new `fee_assessments` row is created instead, with `base_amount` set to the adjustment delta (positive or negative) and its own `discount_amount`/`scholarship_amount` breakdown — visible to the parent as a distinct line item, not a silent change to a total they may have already paid against.

**One write path:** `FeeAssessmentService::assess()`/`::adjust()` (`21` §2) are the only code permitted to write this table — mirrors D3's `ClassSectionRepository::assignClassTeacher()` precedent (a single method owns the invariant, not a database trigger).

## 3. Discounts and scholarships — policy records, per discovery §10.4/§10.5

```
discounts
  id                  PK
  school_id           FK -> schools, NOT NULL
  fee_assessment_id   FK -> fee_assessments, NOT NULL     -- always applied to a specific
                                                             -- assessment (discovery §10.4),
                                                             -- never enrollment-wide
  type                 enum(individual, percentage, fixed)
  value                decimal(12,2), NOT NULL             -- interpreted per `type`: a raw amount
                                                             -- for individual/fixed, a percentage
                                                             -- (0-100) for percentage
  granted_by           FK -> users, NOT NULL                -- school_admin who granted it
  reason               text, nullable
  created_at

scholarships
  id                  PK
  school_id           FK -> schools, NOT NULL
  student_id          FK -> users, NOT NULL
  academic_year_id    FK -> academic_years, NOT NULL       -- scholarships are granted per session,
                                                             -- not permanently attached to the student
  type                 enum(full, partial, specific_exemption)
  value                decimal(12,2), nullable              -- percentage or fixed amount for
                                                             -- 'partial'; null for 'full'
  fee_category_id     FK -> fee_categories, nullable         -- set only for 'specific_exemption'
                                                             -- (e.g. exempt from uniform fee only);
                                                             -- null for full/partial, which apply
                                                             -- across all of the student's assessments
                                                             -- for the academic year
  granted_by           FK -> users, NOT NULL
  reason               text, nullable
  created_at
```

Discovery §10.4 describes discounts as applied "to a fee_assessment" — so `discounts` rows are assessment-scoped from creation, matching the write-once-at-assessment-time model in §2. Discovery §10.5 describes scholarships as applied "at the student-enrollment level" — so `scholarships` rows are session-scoped policy (a student either has a scholarship for the year or doesn't), and `FeeAssessmentService::assess()` looks up any applicable `scholarships` row for the student/year (and, for `specific_exemption`, the fee category) at assessment time to compute that assessment's `scholarship_amount` snapshot. The **policy** lives in `scholarships`; the **fact** (what a specific assessment's number ended up being) lives in `fee_assessments.scholarship_amount` — this is the distinction `21` §2 draws between the two.

## 4. Payments — Paystack plus manual, one ledger, per discovery §10.2/§10.3 and D13

```
payments
  id                     PK
  school_id              FK -> schools, NOT NULL
  fee_assessment_id      FK -> fee_assessments, NOT NULL
  amount                 decimal(12,2), NOT NULL
  processor               enum(paystack, manual)             -- D13: Paystack confirmed; enum
                                                               -- rather than a hardcoded assumption
                                                               -- so a second processor is additive
  paystack_reference      string, nullable, unique             -- set only when processor = paystack;
                                                               -- verified against Paystack's API before
                                                               -- this row is written (21 §3 — never
                                                               -- trust a client-supplied success callback)
  receipt_upload_path     string, nullable                     -- set only when processor = manual and
                                                               -- the parent/admin attached a receipt
                                                               -- (discovery §10.2)
  status                  enum(pending, confirmed, failed)     -- online payments may sit 'pending'
                                                               -- between initiation and webhook
                                                               -- confirmation; manual payments are
                                                               -- 'confirmed' at write time (admin-entered)
  recorded_by              FK -> users, nullable                -- set for manual payments (which admin
                                                               -- entered it); null for Paystack-initiated
                                                               -- payments, which are parent-initiated
                                                               -- and processor-confirmed, not admin-entered
  created_at, updated_at
```

Only `status = 'confirmed'` payments count toward `fee_assessments`' derived `amount_remaining` (§2) — a `pending` Paystack payment must not appear to have paid down a balance before the webhook actually confirms it, and a `failed` one never did.

## 5. Expenses — admin-only, no parent visibility, per discovery §13.2

```
expenses
  id                  PK
  school_id           FK -> schools, NOT NULL
  category            enum(staff_payment, operational, other)
  amount              decimal(12,2), NOT NULL
  description         text, nullable
  incurred_on         date, NOT NULL
  recorded_by          FK -> users, NOT NULL
  created_at, updated_at
```

Deliberately simple — `21` §7 notes this table needs none of `fee_assessments`' discount/scholarship/rollover machinery. Scoped by `tenantScope()` only; no `relationshipScope()` dimension, since expenses have no per-student relationship to check (admin/accountant-role authority is school-wide by design, matching `12` §3a's framing of tenant-only scope as correct for admin-facing resources).

## 6. Scope-check obligations this schema creates (binding on the build, not optional)

Per `21` §5, every controller action that resolves one of these tables' rows by ID must carry the matching `ScopeService` check before this track's test gate closes:

| Table | By-ID read path | Required check |
|---|---|---|
| `fee_assessments` | Parent/student fee view | `tenantScope` + `relationshipScope` (student/parent branch) |
| `payments` | Parent payment history/receipt view | `tenantScope` + `relationshipScope`, via the owning `fee_assessment` |
| `discounts`/`scholarships` | Admin-only — no parent-facing by-ID route | `tenantScope` only |
| `expenses` | Admin/accountant reporting only | `tenantScope` only |

`payments`' write path (`PaymentController::store`) carries the same `relationshipScope` obligation as the read path — `21` §5 calls this out explicitly since a write-side scope gap here is worse than a read-only one.

---

Test-gate design for this schema (tenant + relationship scope on every fee/payment endpoint, D11's amount-due immutability, D12's rollover idempotency, D13's Paystack-confirmation-before-write) is a build-time artifact, not part of this schema pass — produced when `20` §5's build prompt is picked up, once this document and `21` are reviewed and the domain-map pass (`19` §0 step 1's standing requirement) is run against whatever academic-year/enrollment machinery already exists to confirm the FKs above resolve against real tables.
