# 15 — Academic Domain Map: Subjects, Timetable, Lessons, Assignments, Exams (GegoK12, verified from real `app/` source)

**Status:** Verified against every controller that touches subjects/timetable/lesson-plans/assignments/exams across all four subtrees (`Api/*`, `Api/Teacher/*`, `Teacher/*` web, `Admin/*` web), plus the relevant models. This is the domain-map pass `14-schoolos-implementation-plan.md` §4/§8 flagged as newly-necessary before Phase 4 schema work — same purpose and format as `06`–`09`. Schema-level facts (migrations) are not available this pass — `app.7z` contains `app/` only, same caveat as `10-api-map.md`.

---

## 1. Headline finding: Exam, Marks, and Timetable are broken on every request, in every install — a namespace bug, not a scoping gap

`Api\ExamController`, `Api\MarksController`, and `Api\TimetableController` (the entire parent/student-facing read surface for exams, marks, and timetables) import and call:

```php
use App\Models\Exam;      // ExamController
use App\Models\Mark;      // MarksController
use App\Models\TempTimetable;  // TimetableController
```

**None of these classes exist anywhere in `app/Models`** (confirmed: zero files matching `exam*`, `mark*`, `timetable*` in the 107-file `app/Models` directory). Every call to `upcomingExam`, `pastExam`, `index`/`getmarks`/`show` (Marks), and `index` (Timetable) will throw `Class "App\Models\Exam" not found` (or `Mark`/`TempTimetable`) — a fatal error on the very first line that touches the model, for every school, regardless of configuration.

**This is not a missing feature — it's a wrong namespace.** Exam and Timetable are genuinely addon/plugin modules, confirmed by grepping the rest of the codebase:

- `app/Models/StandardLink.php` (`temp_timetable()` relation): `$this->hasMany('\Gegok12\Timetable\Models\TempTimetable', ...)`
- `Admin\StudentDetailsController.php`, `Admin\PromotionController.php`, `Admin\EventsController.php` all correctly reference the addon namespace, and correctly guard it:
  ```php
  if (class_exists('Gegok12\Exam\Models\Exam')) {
      $exam = \Gegok12\Exam\Models\Exam::where(...)->get();
  }
  ```

So the codebase has a **correct, established pattern** for touching these addon-provided models (`class_exists()` guard + fully-qualified `Gegok12\...\Models\...` reference) used consistently in four other controllers — and the three `Api/*` read controllers simply don't follow it. They `use App\Models\Exam` (a class that has never existed in `app/Models`, not even before an addon was involved) with no guard at all. This reads as either a copy-paste from an earlier, differently-organized version of the app, or code that was never actually exercised against a real environment — either way, it's a hard break, not a partial one: **there is no configuration in which these three controllers currently work**, addon installed or not, because the import itself is wrong regardless of whether `Gegok12\Exam\Models\Exam` exists at runtime.

**Impact:** the entire parent/student mobile-app Exam, Marks, and Timetable surface is dead code in its current form. Any user pulling upcoming exams, past exam results, marks, or their child's timetable via the mobile API gets a fatal error, not empty data — worse than a missing feature, since it likely also breaks whatever wraps the request (uncaught in `ExamController`'s two methods that lack try/catch at all; `TimetableController` has none either).

**SchoolOS implication:** this resolves a real design question for Phase 4 before it starts — GegoK12's own architecture treats Exam and Timetable as *optional, separately-installed modules* (the `Gegok12\Exam\*` / `Gegok12\Timetable\*` namespace split, the `class_exists()` guards elsewhere), not core functionality. `14-schoolos-implementation-plan.md` §4 currently scopes "exams/marks" as part of core Phase 4 build alongside subjects/timetable/lessons/assignments — worth an explicit decision: does SchoolOS build exams/timetable as core (folding the addon's responsibility in, no more optional-module complexity), or preserve the optional-module boundary GegoK12 intended (and actually implement the guard correctly this time, unlike the reference app)? Either is defensible, but it should be a deliberate choice now that the reference implementation's own version of "optional module" is confirmed broken rather than assumed working.

---

## 2. Where each academic sub-domain actually lives

| Sub-domain | Read surface (parent/student) | Read/write surface (teacher) | Admin/setup surface | Status |
|---|---|---|---|---|
| Subjects | — (no dedicated read endpoint found) | via `Teacherlink` (assignment, not a subject CRUD) | `Admin\SubjectController` | Not reviewed this pass — CRUD only, admin-gated, lower risk; flagged as open item |
| Timetable | `Api\TimetableController::index($student_id)` | — (no teacher-facing controller in `app/`) | — | **Broken** (§1) — also addon-namespace, correctly guarded nowhere in this controller |
| Lesson Plans | `Api\LessonPlanController` (`print`, `index`, `subjectIndex`, `show`) | `Api\Teacher\LessonPlanController` (full create/edit/approve workflow); web equivalents `Teacher\LessonPlanController`/`LessonPlanAddController`/`LessonPlanEditController`/`LessonPlanApprovalController` (not individually re-verified this pass, flagged open) | — | Scoping gaps found both sides — F26 (parent/student), F30 (teacher, incl. approval-workflow bypass) |
| Assignments | `Api\AssignmentController` (already covered, `10-api-map.md` §2 — tenant-only reads, identity-spoofing writes) | `Api\Teacher\AssignmentController` (create/read/update/delete — matches F22 exactly, already logged) + `Api\Teacher\StudentAssignmentController` (grading) | — | New this pass: F29 (grading surface, zero scoping) |
| Exams / Marks | `Api\ExamController`, `Api\MarksController` | — (no teacher-facing controller in `app/`) | — | **Broken** (§1) |

**Notable gap, not yet explained:** no teacher- or admin-facing controller exists anywhere in `app/Http/Controllers` for *creating* an exam, entering marks, or building a timetable. Given §1's finding, the most likely explanation is that this management UI lives inside the same `Gegok12\Exam\*`/`Gegok12\Timetable\*` addon packages themselves (shipped separately, not part of this `app/` source tree) rather than in core `app/Http/Controllers` — consistent with `F1`'s original discovery of an addon-install controller (`AddonInstallExamController`) that manages exam-addon installation from the admin side. Not confirmed without the addon package source, which this pack doesn't contain.

---

## 3. New scoping findings this pass (added to `11-security-findings.md` as F29, F30)

- **F29 — `Api\Teacher\StudentAssignmentController`:** the grading surface (a teacher setting `obtained_marks`/`comments` on a student's assignment submission) has *zero* scoping on every method, including `store`/`update`. Any teacher, any school, can write grades onto any student's submission by ID. This is a materially worse risk class than the read-IDOR findings elsewhere in the log — it's forged academic records, not just exposed ones.
- **F30 — `Api\Teacher\LessonPlanController::approve`/`reject`:** no tenant scoping *and* no role check, despite this same controller's `index()` correctly branching on `hasRole('principal')` two methods away. Any teacher can approve or reject any lesson plan — a workflow-integrity bypass on top of the usual cross-tenant read/write gap.

Both follow the audit's now-familiar shape (`10-api-map.md` §2–§3, `11` F18/F22/F24): the correct check exists *somewhere nearby in the same file* and simply wasn't applied to the method that needed it most.

---

## 4. Positive pattern worth adding to the standardization list

`Api\Teacher\LessonPlanController::index()` is a genuinely correct example of **role-based scope broadening**, distinct from the three patterns `10-api-map.md` §4 already catalogued (post-fetch intersect, pre-fetch query filter, no-param self-resolution):

```php
if (!Auth::user()->hasRole('principal')) {
    $q->where('teacher_id', Auth::id());
}
```

Tenant scoping (`school_id`/`academic_year_id` via the `teacherlink` relation) always applies; the *additional* teacher-only restriction is conditionally lifted for a role that legitimately needs broader visibility (a principal reviewing all lesson plans for approval). This is the right shape for SchoolOS's `relationshipScope` to support explicitly — a role parameter that widens (not bypasses) the relationship check — rather than the all-or-nothing tenant/no-tenant split seen elsewhere. The fact that `approve`/`reject` in the *same file* don't reuse this exact check (F30) is exactly the kind of near-miss `12-schoolos-architecture.md` §3a's proposed lint/test rule is meant to catch.

---

## 5. SchoolOS implications (extends `12-schoolos-architecture.md` §3a, `14` §4)

- **A missing-dependency/broken-import class of bug needs its own CI check, alongside F1/F11's route-integrity check** (`14` §7) — `class_exists()` guards protecting optional-module code are a real, load-bearing pattern in this codebase (four correct examples exist), so SchoolOS's equivalent (if it keeps any addon/plugin boundary at all) needs a build-time or CI-time check that every reference to an optional module is actually guarded, not just a convention developers are trusted to remember. §1's finding is proof that trust alone already failed three times in one source tree.
- **Grading and approval-workflow writes need the same mandatory `relationshipScope` as student-record reads** — F29 and F30 both show write endpoints in the academic domain are, if anything, *less* likely to be scoped than reads, extending the same conclusion `11-security-findings.md` F25 already drew for the Task domain to Academic as well.
- **Role-based scope-widening (§4 above) is a real requirement**, not a hypothetical — `ScopeService.relationshipScope` needs a parameter for "this role sees a wider relationship set," modeled on the correct `hasRole('principal')` branch found here, applied consistently to every method that touches the same resource (unlike this controller's own `approve`/`reject`).

---

**Open items:**
- `Admin\SubjectController` (subject CRUD) — not reviewed this pass; admin-gated, presumed lower risk, but not confirmed. Consistent with this audit's standing rule, treat as suspect not safe until checked.
- Web-side lesson plan controllers (`Teacher\LessonPlanController`, `LessonPlanAddController`, `LessonPlanEditController`) — not individually re-verified this pass; `LessonPlanApprovalController` is presumed to share F30's approve/reject gap given F31 found the identical pattern in all five other approval controllers checked, but wasn't itself opened and read — worth a quick direct confirmation rather than assumed.
- ~~`Api\Teacher\Approval\AssignmentApprovalController` / `HomeworkApprovalController`, and their `Admin\Approval\HomeworkApprovalController` / `Teacher\Approval\*` web equivalents — not reviewed this pass.~~ **Closed.** All five reviewed; all five share F30's gap exactly — logged as **F31** in `11-security-findings.md`. Approval workflows are now confirmed, not just suspected, as a systemic gap independent of surface (API/web) or audience (teacher/admin).
- The addon package source for `Gegok12\Exam\*` and `Gegok12\Timetable\*` (not in this source pack) — would confirm whether exam/mark/timetable *creation* genuinely lives there, and whether the addon's own code has the same class of scoping gaps found throughout core `app/` — currently unknown, not assumed either way.
- `Admin\PromotionController`'s use of `Gegok12\Exam\Models\Exam` (student promotion likely depends on exam results) — not traced this pass; worth checking whether promotion logic silently no-ops or errors when the Exam addon isn't installed, given `class_exists()` is checked but the fallback behavior when it returns `false` wasn't reviewed.
