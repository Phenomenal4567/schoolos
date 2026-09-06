# 13 — SchoolOS Database Schema

**Status:** Draft. Every table below is a direct response to a specific finding from `05-database-map.md`, `09-attendance-map.md`, `11-security-findings.md`, or a design decision from `12-schoolos-architecture.md` — no table exists here on general "best practice" grounds alone. Where GegoK12's schema is worth keeping (per `12` §8), that's stated explicitly rather than silently redesigned. This is a logical schema (tables, columns, constraints, relationships) — physical concerns (indexing strategy beyond the constraints implied here, partitioning, migration ordering) belong in `14-schoolos-implementation-plan.md`.

---

## 1. Tenant root — unchanged from GegoK12

`05-database-map.md` §1 confirmed GegoK12's schema-level tenant boundary is sound; the failure was enforcement, not structure. Keep the shape:

```
schools
  id                  PK
  name                unique, not null
  email               unique, not null
  phone               unique, not null
  status              enum(active, suspended)   -- GegoK12: boolean; SchoolOS: explicit states,
                                                  -- a suspended school is a real, distinct state from
                                                  -- deleted, not just a false flag
  deleted_at          soft-delete
```

**Rule carried into every table below:** `school_id` is `NOT NULL` on every tenant-scoped table, no exceptions. `05-database-map.md` §1 found `student_parent_links.school_id` and `student_history.school_id` nullable in GegoK12 while comparable tables weren't — a nullable tenant FK is a structural opening for exactly the kind of scope gap F18/F20/F21/F22 found at the application layer. SchoolOS should not leave that opening at the schema level even before any query code is written.

## 2. Identity and role — collapses `usergroup_id` + Laratrust into one system (F8, F9)

`03-role-permission-map.md` found three parallel authorization systems; `12-schoolos-architecture.md` §3 already decided to collapse them into **Role** (what you can do) and **Scope** (what you can act on). Schema for the Role half:

```
roles
  id                  PK
  key                 unique, not null   -- 'teacher', 'student', 'parent', 'school_admin',
                                          -- 'librarian', 'accountant', 'stock_keeper', ...
                                          -- replaces the 13 bare usergroup_id integers (03 §1)
                                          -- with a real lookup table, not hand-maintained constants
  label               not null

permissions
  id                  PK
  key                 unique, not null   -- 'approve_leave', 'approve_assignment', ...
                                          -- absorbs GegoK12's Laratrust sub-roles
                                          -- (principal/leave_checker/leave_applier —
                                          -- confirmed as the one part of GegoK12's
                                          -- authorization sprawl that's conceptually
                                          -- sound, per 07-teacher-domain-map.md §3)

role_permissions
  role_id             FK -> roles, part of composite PK
  permission_id       FK -> permissions, part of composite PK

users
  id                  PK
  school_id           FK -> schools, NOT NULL   -- no superadmin exception at the schema level;
                                                  -- F3's bypass becomes a query-layer concern
                                                  -- (12 §1), not a nullable-FK escape hatch
  role_id             FK -> roles, NOT NULL      -- replaces usergroup_id; exactly one role per user
  email               unique, nullable           -- nullable because not every identifier type applies
                                                  -- to every role (F5/F6's point: never hardcode which
                                                  -- field is "the" lookup field)
  mobile_no           unique, nullable
  registration_number unique, nullable
  status              enum(active, inactive, exited)
  deleted_at          soft-delete
```

**Explicitly not carried over:** `usergroup_id` as a bare integer (F8's root cause), the separate Laratrust `role_user`/`permission_user`/`permission_role` junction tables running alongside it (`03` §2/§3 — System B and System C existing in parallel with System A), and the specialized `StudentUser`/`TeacherUser`/`ParentUser` subclasses that don't enforce their own type (F9). One `users` table, one `role_id`, real foreign keys — a query that should only return teachers filters on `role_id`, and that filter should be a single global scope applied once per specialized query context, not a per-scope-method convention.

**Unique constraints on `email`/`mobile_no`/`registration_number` are new relative to GegoK12.** F6 found `checkschool`'s validator could resolve the wrong user via a non-unique `name` match; F5 found the registration-number path crashes because a downstream check hardcodes a different field. Making the three real identifier fields actually unique at the schema level is what makes "resolve by exactly one of these, consistently" (`12` §2's `resolveIdentifier`) enforceable rather than aspirational. `name` is **not** a unique/identifier column in this schema — it's a display field only, ending F6's entire failure class at the schema level.

## 3. Scope — the assignment tables F18/F20/F21/F22 all point back to

This is the section with the most direct evidence behind it. `12-schoolos-architecture.md` §3a identified two relationship tables GegoK12 already has correctly modeled — the defect in every one of F18/F20/F21/F22 was that application code failed to *query* them, not that they didn't exist. SchoolOS keeps both shapes and makes them the only path to a scoped record.

```
academic_years
  id                  PK
  school_id           FK -> schools, NOT NULL
  label               not null              -- '2026-2027'
  is_current          boolean

standards                                    -- grade/level, e.g. "Grade 5"
  id                  PK
  school_id           FK -> schools, NOT NULL
  name                not null

sections                                     -- 'A' / 'B' — GegoK12 pools these per-school,
  id                  PK                     -- not per-standard (05 §2); kept as-is, it's a
  school_id           FK -> schools, NOT NULL -- reasonable normalization, not a defect
  name                not null

class_sections                               -- GegoK12's standards_link 4-way join (05 §2),
  id                  PK                     -- renamed for clarity per 12 §7's naming-discipline rule
  school_id           FK -> schools, NOT NULL
  academic_year_id    FK -> academic_years, NOT NULL
  standard_id         FK -> standards, NOT NULL
  section_id          FK -> sections, NOT NULL
  class_teacher_id    FK -> users, NOT NULL
  CHECK / trigger-or-app-enforced: class_teacher_id must reference a user with role 'teacher'
    -- 05 §2 found nothing enforces this at the DB level in GegoK12; a CHECK constraint referencing
    -- another table isn't portable in most RDBMSs, so this needs either a trigger or must be the
    -- one thing ScopeService's write path guarantees before insert — flag for 14 to decide the
    -- concrete mechanism, but the requirement itself is schema-documented here so it isn't lost

class_teacher_assignments                    -- GegoK12's class_teacher_links / Teacherlink model
  id                  PK                     -- (07-teacher-domain-map.md §2) — confirmed real,
  school_id           FK -> schools, NOT NULL -- populated, and correctly used for lesson
  academic_year_id    FK -> academic_years, NOT NULL -- plans/timetable, but NEVER consulted by
  class_section_id    FK -> class_sections, NOT NULL  -- the authorization Gates that needed it (F18)
  subject_id          FK -> subjects, NOT NULL
  teacher_id          FK -> users, NOT NULL
  UNIQUE (class_section_id, subject_id, teacher_id, academic_year_id)

student_enrollments                          -- GegoK12's student_academics, split per F14 —
  id                  PK                     -- see §4 below for why medical fields are gone from here
  school_id           FK -> schools, NOT NULL
  academic_year_id    FK -> academic_years, NOT NULL
  student_id          FK -> users, NOT NULL
  class_section_id    FK -> class_sections, NOT NULL
  roll_number         not null
  status              enum(active, transferred, graduated, withdrawn)
  UNIQUE (student_id, academic_year_id)      -- one enrollment row per student per year, matches
                                               -- 05 §4's positive finding that GegoK12 already
                                               -- versions enrollment by year — kept as-is

student_parent_links                          -- kept close to GegoK12's shape per 12 §8 —
  id                  PK                      -- explicitly called out as "keep this shape almost
  school_id           FK -> schools, NOT NULL  -- as-is." Only change: school_id is NOT NULL here
  parent_id           FK -> users, NOT NULL    -- (05 §1's nullable-FK gap, closed), and no second,
  student_id          FK -> users, NOT NULL    -- competing mechanism exists alongside it (see below)
  status              enum(active, inactive)
  UNIQUE (parent_id, student_id)
```

**Explicitly not carried over:** GegoK12's second, self-referencing `users.ref_id` parent-child mechanism (`User::members()`, consumed asymmetrically by `mother()`/`father()` — flagged in `08-parent-domain-map.md` §3 as a likely-latent bug and, independent of the bug, as vestigial duplication of `student_parent_links`). SchoolOS has exactly one parent-child relationship table. There is no `ref_id`-equivalent column on `users` at all — the duplication can't recur if the second mechanism's column doesn't exist to reach for.

**Why this section matters more than a normal schema section:** F18, F20, F21, and F22 are four independent, confirmed cases of application code skipping a check against exactly this data. `class_teacher_assignments` and `student_parent_links` were never the problem in GegoK12 — they're correctly shaped and (mostly) correctly populated. The problem was that `Gate::allows('member', $user)` and `Attendance::where('user_id', $student_id)` never joined against them. Restating these tables here isn't redundant with `05-database-map.md` — it's establishing that **any SchoolOS query resolving a specific student/class/subject record must express its scope as a join against `class_teacher_assignments` or `student_parent_links`**, not as a comment or a convention. `14-schoolos-implementation-plan.md` should treat "no repository method may return a single tenant-scoped record without one of these two joins" as a literal code-review/lint rule, per `12` §3a's proposal.

## 4. Health data — split from enrollment (F14)

```
student_health_profiles
  id                  PK
  school_id           FK -> schools, NOT NULL
  student_id          FK -> users, NOT NULL, UNIQUE
  medication_problems  nullable
  medication_needs     nullable
  medication_allergies nullable
  food_allergies       nullable
  other_allergies      nullable
  other_medical_info   nullable
  height               nullable
  weight               nullable
```

Independently policy-gated from `student_enrollments` — per `12` §6, default access is school nurse/admin roles, not every staff member who can view a class roster. This is a straight extraction of the fields F14 found sitting in `student_academics`; no new fields invented.

## 5. Attendance — event-sourced, unique-constrained, correctable (F13, `09-attendance-map.md`)

```
attendance_records
  id                  PK
  school_id           FK -> schools, NOT NULL
  academic_year_id    FK -> academic_years, NOT NULL
  class_section_id    FK -> class_sections, NOT NULL
  student_id          FK -> users, NOT NULL
  date                date, NOT NULL
  session             enum(morning, afternoon)
  status               enum(present, absent, late, excused)   -- not boolean (05 §3 / F13)
  recorded_by          FK -> users, NOT NULL
  recorded_at          timestamp, NOT NULL
  UNIQUE (student_id, date, session)         -- schema-level, per-person — 05 §3 found this
                                               -- completely absent in GegoK12; the guard that did
                                               -- exist (09 §3) was class-session-granularity and
                                               -- race-condition-prone (check-then-act, no lock).
                                               -- A unique constraint makes the race structurally
                                               -- impossible rather than merely reduced.

attendance_corrections
  id                  PK
  attendance_record_id FK -> attendance_records, NOT NULL
  previous_status      not null
  new_status           not null
  corrected_by         FK -> users, NOT NULL
  corrected_at         timestamp, NOT NULL
  reason               not null
```

`09-attendance-map.md` §4 found GegoK12 has **no correction capability at all** — not just a missing audit trail, a missing feature, since the class-session lock made per-student correction structurally impossible without redesigning the guard first. Because the unique constraint above is per-`(student_id, date, session)` rather than per-class, a correction here is a normal `UPDATE` plus an `attendance_corrections` row — the schema doesn't need a separate redesign to support it the way GegoK12's would have.

## 6. Naming discipline applied (F15)

GegoK12's `student_history` table is actually a content read-receipt tracker, not enrollment history — a name collision that cost real investigation time (`05` §4, F15). SchoolOS names this for what it does:

```
content_read_receipts
  id                  PK
  school_id           FK -> schools, NOT NULL
  user_id             FK -> users, NOT NULL        -- the parent/student who viewed something
  entity_type         enum(image, video, assignment, homework)
  entity_id           bigint, NOT NULL              -- polymorphic target, same shape as GegoK12's
  read_at             timestamp, NOT NULL
```

No table in this schema is named for a domain concept it doesn't actually implement. If a future table needs a name like "history," it should contain what "history" means in that context (a changelog of a specific entity), not an unrelated tracking mechanism.

## 7. Traceability table (finding/decision → schema element)

| Source | Schema response |
|---|---|
| `05` §1 — nullable `school_id` on some tenant tables | Every `school_id` column here is `NOT NULL`, no exceptions |
| F3 — scattered superadmin bypass | No schema-level exemption exists; `users.school_id` is `NOT NULL` even for platform admins — bypass is a query-layer concern per `12` §1, not a schema escape hatch |
| F5, F6 — inconsistent/non-unique login field lookups | `email`, `mobile_no`, `registration_number` are all `UNIQUE`; `name` is not a lookup field at all |
| F8, F9 — three parallel authorization systems, unenforced subclass typing | Single `roles`/`permissions`/`role_permissions` set; one `role_id` on `users`, no specialized subclasses to diverge |
| F14 — medical data inside enrollment table | `student_health_profiles` split out, independently policy-gated |
| F15 — misleading `student_history` name | Renamed `content_read_receipts`, describes actual contents |
| F13 — no attendance uniqueness, no correction path | `UNIQUE (student_id, date, session)` + `attendance_corrections` table |
| F18, F20, F21, F22 — scope checks skipped at the application layer | `class_teacher_assignments` and `student_parent_links` restated as the mandatory join path for any single-record query; `08`'s `ref_id` duplicate mechanism has no schema equivalent here |
| `08` §3 — `ref_id`/`members()` duplicate parent-child mechanism | No `ref_id`-equivalent column exists on `users`; `student_parent_links` is the only path |

---

**What this document does not cover:** payroll/finance, library, messaging, transport, and HR schemas — `05-database-map.md`'s ~90 unreviewed migrations. These should get their own domain-map pass before a schema is drafted for them, consistent with how `06`–`10` preceded this document for the core domain. Treat this schema as covering exactly what `12-schoolos-architecture.md` covers: tenant, identity/role, scope, attendance, and the health/enrollment split — not the full ERP surface.
