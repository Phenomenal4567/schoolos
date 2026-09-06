# 17 — SchoolOS Academic Domain Map (Phase 4 pre-schema pass): Subjects, Timetable, Lesson Plans, Assignments, Exams/Marks

**Status:** This is the domain-map pass `14-schoolos-implementation-plan.md` §4/§8 flagged as required before Phase 4 schema work — "in the style of `06`–`09`," adapted for a phase where the reference-codebase findings are already fully captured (`15-academic-domain-map.md`, plus F26–F31 in `11-security-findings.md`) and the job is design, not further reverse-engineering. No schema, migration, or model code is written in this pass — that's the next task, once this document's entity list and scope model are settled. Ground Rule 0 (`14` §0) and `12-schoolos-architecture.md` §3a apply unchanged: every design decision below routes through `ScopeService`, never a new per-controller check.

---

## 1. What this phase inherits, already resolved

Three things `15` §1 explicitly left open are already closed by `16-schoolos-decisions-register.md`'s corrections section, so this pass doesn't re-litigate them:

- **Exams/Timetable are core, not an addon/plugin boundary** (`16`, corrections section). GegoK12's `Gegok12\Exam\*`/`Gegok12\Timetable\*` optional-module split — and its three-controller namespace bug that made the entire feature dead code in every install (`15` §1) — is not a shape SchoolOS inherits. Exam, Marks, and Timetable get real `app/Models` classes and real migrations in this phase, same as everything else.
- **Subjects already exist.** `Subject` (model, migration, `Admin\SubjectController`) shipped in Phase 2 to resolve the `class_teacher_assignments.subject_id` dangling FK (`13` §3). This phase adds no new subject schema — it only adds resources that reference `subjects.id`.
- **`ScopeService::relationshipScope()`'s generic column-based dispatch already covers most of what this phase needs, for free.** `teacherRelationshipScope()` narrows any model with a `class_section_id` column to the teacher's assigned sections; `parentRelationshipScope()`/`studentRelationshipScope()` narrow any model with a `student_id` column the same way (see that file's `Schema::hasColumn()` branches). Every new table below that carries one of those two columns is scoped correctly the moment it exists — no new `ScopeService` branch needs writing for the common case. The prior task's `$wideVisibilityRoles` parameter (also already shipped) covers the one shape that generic dispatch can't: a role whose narrowing needs to be *conditionally lifted* for a specific resource. §4 below works out whether Phase 4 actually needs that lift for any of its five roles.

## 2. Entities this phase adds

| Entity | Carries | Reference-codebase status (`15`) |
|---|---|---|
| `Timetable` (slot-level) | `class_section_id` | Broken in reference — wrong-namespace import, addon boundary. SchoolOS: new, core. |
| `LessonPlan` | `class_section_id`, authored by a teacher | Present in reference; scoping gaps both sides (F26 parent/student, F30/F31 teacher approval workflow) |
| `Assignment` | `class_section_id` | Present in reference; F22-shaped gaps on `show`/`update`/`destroy` |
| `AssignmentSubmission` (the grading surface `15` calls `StudentAssignmentController`) | `student_id` | Present in reference; F29 — zero scoping on the entire controller, including writing grades |
| `Exam` | `class_section_id` (an exam is given to a section) | Broken in reference — wrong-namespace import |
| `ExamMark` | `student_id` | Broken in reference — wrong-namespace import |

Six entities, not five — `15`'s "assignments" sub-domain is really two resources with two different actors writing to them (a teacher creates the `Assignment`; a teacher also writes the grade, but onto a per-student `AssignmentSubmission` row, which is F29's exact target). Splitting them here is what makes each one's `ScopeService` column requirement (`class_section_id` vs. `student_id`) unambiguous — collapsing them back into one table would force a single model to carry both columns for no reason two already-generic `ScopeService` branches can't handle separately.

## 3. Scope model per entity

Every row below assumes the standard call shape already established in every Phase 1–3 controller: `tenantScope()` first, then `relationshipScope()`, intersected into the query before it runs — never fetch-then-check (`12` §3a, `ScopeService`'s own class doc comment).

| Entity | Teacher sees | Parent/student sees | Admin (`school_admin`) sees | Scoping mechanism |
|---|---|---|---|---|
| `Timetable` | Sections they're assigned to (`class_teacher_assignments`) | Own/linked children's sections | Whole school (default branch) | Generic `class_section_id` dispatch — no new code |
| `LessonPlan` | Sections they're assigned to | Own/linked children's sections | Whole school (default branch) | Generic `class_section_id` dispatch — no new code |
| `Assignment` | Sections they're assigned to | Own/linked children's sections | Whole school (default branch) | Generic `class_section_id` dispatch — no new code |
| `AssignmentSubmission` | **Not** generic — see below | Own/linked children's submissions | Whole school (default branch) | `student_id` dispatch for parent/student; teacher needs a join through the parent `Assignment`, see below |
| `Exam` | Sections they're assigned to | Own/linked children's sections | Whole school (default branch) | Generic `class_section_id` dispatch — no new code |
| `ExamMark` | **Not** generic — see below | Own/linked children's marks | Whole school (default branch) | Same shape as `AssignmentSubmission` |

**The two rows marked "not generic" are the actual design work this phase does.** `teacherRelationshipScope()`'s current implementation intersects `class_section_id` against the teacher's `class_teacher_assignments` rows directly. `AssignmentSubmission` and `ExamMark` don't carry `class_section_id` themselves (a submission belongs to a student, not a section) — they carry `student_id` and a foreign key back to their parent `Assignment`/`Exam`, which does carry `class_section_id`. A teacher's access to a submission is "does this row's parent `Assignment` belong to a section I'm assigned to," not "does this row have a `student_id` I'm linked to" (teachers have no `student_parent_links`-style relationship to students at all — that table is parent-only, per `13` §3).

This means `teacherRelationshipScope()` needs one new, explicit branch for these two model shapes — not a generalization of the existing `class_section_id`/`student_id` checks, since neither column exists directly on the row:

```php
// New branch, alongside the existing class_section_id / default cases:
if (Schema::hasColumn($table, 'assignment_id')) {
    $assignedSectionIds = /* same lookup already used above */;
    return $query->whereIn(
        'assignment_id',
        Assignment::whereIn('class_section_id', $assignedSectionIds)->pluck('id')
    );
}
// same shape for exam_id -> Exam::whereIn('class_section_id', ...)
```

This is additive to `teacherRelationshipScope()`, not a change to its existing `class_section_id`/default branches — those two entities (`AssignmentSubmission`, `ExamMark`) are the only reason this method grows at all in Phase 4.

**F29's fix is exactly this branch.** The reference codebase's `StudentAssignmentController` (any teacher, any school, writes grades onto any submission) is precisely "no `relationshipScope` check on a model that needs the join-through-parent shape, because the existing generic dispatch doesn't cover it and nobody wrote the specific branch." Phase 4 writing this branch *before* the grading endpoint exists (test-first, same posture as the `$wideVisibilityRoles` task) closes F29 by construction rather than by code review.

## 4. Role-based scope widening: does Phase 4 need it?

`15` §4's positive pattern — `LessonPlanController::index()`'s `hasRole('principal')` branch, widening a teacher's own-lesson-plans-only view to every lesson plan in the school for approval — is the pattern the `$wideVisibilityRoles` parameter was built to express generically. Checking it against SchoolOS's actual role vocabulary (`RoleSeeder`: `super_admin`, `school_admin`, `teacher`, `student`, `parent`, `accountant`, `librarian`, `receptionist`, `staff`) surfaces a real design question worth resolving explicitly rather than assuming the parameter gets used just because it exists.

**Finding: it doesn't need to be invoked for the lesson-plan-approval case.** GegoK12's `principal` role has no SchoolOS equivalent as a distinct role — the closest concept is `school_admin`, which already gets full within-school visibility on every one of this phase's entities via `relationshipScope()`'s existing default branch (no role match → query unchanged, tenant-scope-only). `school_admin` reviewing/approving any teacher's lesson plan is already exactly what happens today, with zero new code, because `school_admin` was never narrowed in the first place — there's nothing to widen.

**So `$wideVisibilityRoles` stays unused by this phase's five approval/review endpoints**, and that's the correct outcome, not a gap: the "add a role, don't touch the service" property the parameter was built for means a future role that genuinely needs conditional widening (a department-head-style role sitting between `teacher` and `school_admin` — narrower than admin, wider than a single teacher's own sections — is the shape that would actually need it) can be added by a caller passing that role's key into the list, without this document or `ScopeService`'s core logic changing again. Recording this now means the next engineer who reads the `$wideVisibilityRoles` parameter and wonders "where's the caller that uses this" has an answer: nowhere yet, by design, because no current role needs it — not an oversight.

**Do NOT add a `'principal'` role for this.** Same reasoning the prior task's brief already applied to its own test fixtures: an existing role (`school_admin`) covers the real requirement (a school-level reviewer) without a new migration, seeder entry, or `Role` row. If a genuine principal-vs-school-admin distinction becomes a real product requirement later (e.g., a principal who can approve lesson plans but not manage user accounts), that's a new decision for the register, made when the requirement is real — not spec­ulatively built now.

## 5. Write-path scoping requirements (F29, F30, F31)

Per `12` §3a and `14`'s Phase 1 test gate, every endpoint that resolves a specific record by ID calls both `tenantScope()` and `relationshipScope()` before acting — reads and writes alike, no exception for mutation endpoints. This phase's write paths, and the specific reference-codebase gap each closes:

| Write path | Must check | Closes |
|---|---|---|
| Create/update `LessonPlan`, `Assignment`, `Exam`, `Timetable` slot | `relationshipScope()` against the target `class_section_id` — a teacher can only author content for sections they're assigned to | Generalizes F18's fix to four new resource types |
| Grade an `AssignmentSubmission` / enter an `ExamMark` | The new join-through-parent branch (§3) — a teacher can only grade submissions belonging to an assignment/exam on one of their own sections | **F29** directly |
| Approve/reject a `LessonPlan` (or any future approval-workflow resource) | Role check (`school_admin`, per §4) **and** `tenantScope()` — the role check alone isn't sufficient, since F30/F31 both show the *tenant* check was also missing, not just the role check | **F30, F31** |
| Any single-record fetch (`show`, `print`, `index` filtered by a route-supplied ID) across all six entities | `relationshipScope()`, not tenant-only | **F26** (the parent/student-facing `LessonPlanController` shape) generalized to the whole domain |

The `print()`-specific lesson (F26: an unscoped fetch's side effect — a PDF render — ran before any check would have caught it) generalizes to this phase's own file-producing paths (a printable timetable, a printable exam mark-sheet, if either ships): the scope check runs before any side-effecting work starts, not just before the response is built.

## 6. Test gate (extends Phase 1's gate, per `14` §4)

Every row below is the same shape as Phase 1's gate table, applied to this phase's six new resource types:

| Test | Regresses |
|---|---|
| A teacher with no `class_teacher_assignments` row for a section cannot read/write that section's timetable, lesson plans, assignments, or exams | F18, generalized |
| A teacher cannot grade a submission (or enter a mark) belonging to an assignment/exam on a section they aren't assigned to, even though the submission/mark row itself has no `class_section_id` | **F29** |
| A parent/student cannot fetch another student's submission, mark, lesson plan, or timetable via any endpoint that takes an ID | F20/F26, generalized |
| Approving or rejecting a `LessonPlan` requires both the reviewer's role (`school_admin`) and matching `tenant_id` — a `school_admin` from another school cannot approve it, and a `teacher` cannot approve it regardless of tenant | **F30, F31** |
| Every "fetch/act on a specific [timetable/lesson-plan/assignment/submission/exam/mark] by ID" endpoint has a registered scope check — enforced by the same automated route-table check Phase 1 established, extended to these six resource types | F22, extended |
| A `school_admin` sees every lesson plan/assignment/exam/timetable entry in their own school without needing `$wideVisibilityRoles` — confirms §4's finding is actually true in the built system, not just reasoned about here | New coverage, no reference-codebase equivalent (nothing in GegoK12 tests this since the admin surface there was never scope-audited this deeply) |

## 7. Open items carried into schema work

- Exact `Assignment`/`AssignmentSubmission` field lists (due dates, attachment storage, late-submission handling) — not decided in this pass, deferred to schema design since none of it changes the scoping model above.
- Exam structure — whether `ExamMark` is one row per student per exam or one row per student per exam per subject (an exam may cover multiple subjects) is a schema question, not a scoping one; either shape still carries `student_id` and dispatches through the same branch.
- Timetable slot granularity (day/period vs. a recurring weekly pattern) — schema question, doesn't change `class_section_id`-based scoping.
- Whether `LessonPlanController::print()`'s file-write-into-storage behavior (§5) is worth keeping as a feature at all, given `15`'s finding that it wrote content across tenant boundaries in the reference implementation purely because the read wasn't scoped — SchoolOS keeping the feature is fine now that the read is scoped by construction, but worth a deliberate yes/no rather than silent carry-forward, consistent with how `16` treated the parent-dashboard and exams-core-vs-addon questions.

---

**Traceability:** F18 (generalized to four new resource types), F20/F26 (generalized read-scoping), F22 (extended route-scope-check test), F29 (new `teacherRelationshipScope()` branch, §3), F30/F31 (role-check-plus-tenant-check on approval workflows, §5). No new `D`-numbered decision is added to `16` by this pass — §4's "no principal role, `school_admin` already covers it" is a design conclusion of this document, not a Phase-1-blocker-shaped decision; it should move into `16` (or a Phase 4 equivalent register) only if it's ever revisited, per that document's own closing note about decisions being binding absent an explicit reason to reopen them.
