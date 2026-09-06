# 05 — Database Map (GegoK12, verified from local source — core domain only)

**Status:** Verified against the 108 migration files sent in the initial audit pack. This pass covers the **tenant/academic/attendance core** — the tables most relevant to the handoff's architectural conclusions (Section 47). The remaining ~90 migrations (payroll, library, messaging, posts/feed, transport, etc.) are catalogued by filename in the initial listing but not yet reviewed table-by-table; flag if you want those covered too.

---

## 1. Tenant boundary — confirmed structurally sound at the schema level

`schools` is the root tenant table (`id`, `name`, `email`, `phone` — all unique — plus `status` boolean, soft-deletes). Every domain table reviewed so far correctly carries a `school_id` foreign key back to it: `standards`, `sections`, `standards_link`, `student_academics`, `attendances`, `student_parent_links`, `student_history` all have it. **This part of the design is genuinely solid** — the schema-level tenant boundary the handoff wants (Section 5/47 point 1) is already mostly there; the problems found so far are all in the *application-layer enforcement* of it (the superadmin bypass pattern, F3), not the schema.

One inconsistency: `student_parent_links.school_id` and `student_history.school_id` are both **nullable**, while `standards.school_id`, `sections.school_id`, `attendances.school_id` etc. are **not nullable**. A nullable tenant FK on a table that otherwise looks tenant-scoped is a gap — a null `school_id` row could slip past school-scoped queries entirely (visible to nobody) or, worse, be visible to everybody if a query forgets to filter nulls explicitly. Worth checking whether this is ever actually left null in practice.

## 2. The "class" concept is a 4-way join, not a single table

There's no single `classes` table. A class-section-in-a-year is `standards_link`, which composes:
- `standard_id` → `standards` (the grade/level, e.g. "Grade 5" — itself school-scoped)
- `section_id` → `sections` (e.g. "A"/"B" — also school-scoped, but notably **not** scoped to a standard, so sections are shared pool per-school rather than per-grade)
- `academic_year_id` → `academic_years`
- `class_teacher_id` → `users` (the homeroom/class teacher — a direct FK to `users`, not to `TeacherUser` or any teacher-specific table, consistent with the single-`users`-table design throughout)

This is a reasonable normalized design in principle, but it means "what class is this" always requires this 4-way join, and — as already found in `03-role-permission-map.md` (`StandardLink::class_teacher_id` relation filters `usergroup_id = 5` in the *relation definition*, not enforced by the schema) — nothing at the database level actually guarantees `class_teacher_id` points to a Teacher-usergroup user. That constraint lives entirely in application code.

## 3. Attendance — confirmed exactly the design gap the handoff called out

`attendances` table: `school_id`, `academic_year_id`, `standardLink_id` (nullable), `user_id`, `date`, `session` (enum: `afternoon`/`forenoon`), `status` (**boolean**, default true), `reason_id` (nullable, for absence reason), `remarks`, `recorded_by` (FK to `users`), soft-deletes.

**Confirmed:** the handoff's Section 5/47 point 5 — *"Attendance must be an auditable domain object/event, not a simple flag"* — is exactly correct, verified at the schema level. `status` is a literal boolean column. There is:
- **No unique constraint** on `(user_id, date, session)` — nothing at the database level prevents two attendance rows for the same person on the same day/session. Duplicate-prevention (explicitly required by the handoff's Section 42 test list) must be entirely an application-layer concern, and we haven't yet seen the controller that would enforce it.
- **No history/audit table** for attendance corrections — `recorded_by` captures who logged it, but if an admin later corrects a status, there's nothing in the schema showing what it changed *from*, when, or by whom. Soft-deletes exist, so a correction implemented as delete-then-recreate would at least leave a deleted row behind, but that's incidental, not designed audit trail.

**SchoolOS action:** exactly what the handoff already concluded — attendance needs to be a proper event/audit-logged entity with an explicit uniqueness constraint per person/day/session, not a boolean flag with a nullable reason.

## 4. Student enrollment is versioned by year, but there's no real "history" table despite the name

`student_academics` is one row per `(user_id, academic_year_id)` — so re-enrollment each year does produce a natural history-by-year shape, which partially satisfies the handoff's Section 47 point 6 (*"student enrollment must be historical and separate from permanent student identity"*). That's a genuine positive.

**But:** the table also mixes in a large amount of health/medical-adjacent fields directly — `medication_problems`, `medication_needs`, `medication_allergies`, `food_allergies`, `other_allergies`, `other_medical_information`, `height`, `weight` — alongside academic fields like `roll_number` and `academic_status`. This conflates two very different concerns (yearly academic enrollment vs. sensitive medical/health information) in one table with one access-control surface. Anyone with read access to a student's academic enrollment record automatically sees their medical information too — there's no separation that would let SchoolOS apply different, more restrictive access rules to health data.

**Separately — naming trap confirmed:** there is a table literally called `student_history`, which a new engineer would reasonably assume is the enrollment-history table described above. **It is not.** Reviewing its actual columns (`read_at`, `entity_id`, `entity_type`, `type` enum of `image`/`video`/`assignment`/`homework`) shows it's a **polymorphic read-receipt tracker** — recording whether a parent/student has viewed a given piece of content (an assignment, a homework post, a photo/video). Completely unrelated to academic enrollment history despite the name. This is exactly the kind of naming collision that costs a new engineer real time; worth explicitly avoiding in SchoolOS's schema naming.

**SchoolOS action:** (a) split medical/health data into its own table with its own access policy, separate from academic enrollment; (b) name tables for what they actually store — don't reuse a domain-sounding name for an unrelated technical concern.

## 5. Parent-child relationship — confirmed explicit, as the handoff wanted

`student_parent_links` (`parent_id` → `users`, `student_id` → `users`, both plain FKs to the single `users` table, `status` boolean, soft-deletes) is exactly the explicit parent-child linking table the handoff's Section 47 point 8 calls for. Reasonably clean — the main gap is the nullable `school_id` noted in §1, and that nothing at the schema level constrains `parent_id`/`student_id` to actually be users with the corresponding `usergroup_id` (7 and 6 respectively) — same application-layer-only enforcement pattern seen throughout.

---

**Open items:**
- ~90 remaining migrations not yet reviewed (payroll/finance, library, messaging/posts, transport, HR) — flag if these should be covered in a follow-up pass or left out of scope for SchoolOS's initial design.
- Still open from earlier passes: `DashboardController@index` (stock-keeper redirect), whether `MustBeParent` middleware is used anywhere.
