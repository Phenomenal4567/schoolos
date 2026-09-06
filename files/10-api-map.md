# 10 — API Map (GegoK12, verified from real `app/` source)

**Status:** Verified against every controller in `app/Http/Controllers/Api/` (top-level, ~4,160 lines across 24 files) and `app/Http/Controllers/Api/Teacher/` (~4,740 lines across 24 files). Route-level detail (exact URL, middleware per-route) is not re-verified here — this pack's `app.7z` contained `app/` only, not `routes/`, so prefix/middleware facts are carried over from `04-route-map.md` (`/api` prefix, `api` middleware group, both subtrees registered from the same `routes/api.php` + the `@include`d `teacherapi.php`) rather than re-checked. Everything below is controller-body-level: what each endpoint actually does with its input.

This pass exists specifically to close the open item left by `06`–`09`: whether F20's IDOR pattern (`Api\AttendanceController::index($student_id)`) was a one-off or systemic. **It is systemic, and worse than originally scoped** — two new findings (F21, F22) surfaced that lack even the tenant check F20 has.

---

## 1. Two subtrees, two audiences

| Subtree | Audience | Identity source | Files | Lines |
|---|---|---|---|---|
| `Api/*` (top-level) | Parent + Student (shared surface, per `06-student-domain-map.md` open item — still not confirmed whether students hit these same controllers or a separate namespace) | Sanctum token, `Auth::user()` | 22 controllers + `Search/UserSearchController` | ~4,160 |
| `Api/Teacher/*` | Teacher (mobile) | Sanctum token, `Auth::user()` / `Auth::id()` | 24 controllers | ~4,740 |

Both subtrees sit under the same `api` middleware group (`04-route-map.md` §1) — there is no additional per-subtree middleware layer visible at the controller level that would enforce role separation beyond whatever each individual endpoint does internally. Scoping is therefore entirely a controller-body concern in this codebase, which is exactly why the pattern below is worth mapping exhaustively rather than spot-checking.

---

## 2. The dominant shape: "fetch/act-on a specific student's record by ID"

The large majority of `Api/*` endpoints share one signature shape: a route parameter naming a target (`$student_id`, `$id`) that the method trusts without checking it belongs to the caller. This table covers every controller with that shape, confirmed by reading the method body (not inferred from the signature alone):

| Controller::method | Param(s) | Checks present | Verdict |
|---|---|---|---|
| `ChildrenController::showChildren` | `$id` | Intersects `$id` against caller's own `student_parent_links` rows | **Correct** — the reference pattern |
| `AttendanceController::index` | `$student_id` | `school_id` only | Tenant-only (**F20**) |
| `FeesController::paid`, `unpaid` | `$student_id` | `school_id` only | Tenant-only, same shape as F20 |
| `MarksController::index`, `getmarks` | `$student_id`, `$exam_id` | `school_id` only | Tenant-only, same shape as F20 |
| `TimetableController::index` | `$student_id` | `school_id` only | Tenant-only, same shape as F20 |
| `DisciplineController::index`, `performance` | `$student_id` | none visible (not even explicit `school_id` filter in `index`) | Unscoped or worse |
| `ExamController::upcomingExam`, `pastExam` | `$student_id` | `school_id` only | Tenant-only, same shape as F20 |
| `HomeworkController::pending`, `finished`, `show`, `store`, `replycomment`, `destroy` | `$student_id` (+ `$homework_id`/`$id`) | `school_id` only; **`store` writes a `StudentHomework` row under caller-supplied `$student_id`** | Tenant-only on read, identity-spoofing on write |
| `AssignmentController::index`, `completed`, `show`, `store`, `destroy` | `$student_id` (+ `$id`/`$assignment_id`) | `school_id` only; same write-spoofing shape as Homework | Tenant-only on read, identity-spoofing on write |
| `LeaveController::index`, `store` | `$student_id` | `school_id` only; **`store` files a leave application under caller-supplied `$student_id`** | Tenant-only on read, identity-spoofing on write |
| `LeaveController::show`, `update` | `$id` (leave application, not student) | **none at all — no `school_id` filter either** | Unscoped, cross-tenant — **F21** |

"Identity-spoofing on write" means: since the endpoint never checks that `$student_id` is the caller's own ID or one of their linked children, an authenticated parent/student can submit homework, an assignment, or a leave application **as a different student** — this is a distinct risk from read-IDOR and wasn't captured by F20's original attendance-only framing.

**Every row above except `ChildrenController::showChildren` and the two `LeaveController` methods shares F20's exact tenant-only shape.** Rather than log each as a separate finding (they're one root cause), F20 in `11-security-findings.md` should be read as representative of the whole `Api/*` "fetch this student's X" surface, not attendance-specific. The two `LeaveController` methods are categorically worse (no tenant check either) and are logged separately as **F21**, alongside the equivalent gap found on the teacher side of the same `TeacherLeaveApplication` table.

---

## 3. Teacher-side (`Api/Teacher/*`): mixed — some correct, some worse than F18

Unlike the uniform tenant-only pattern above, the teacher subtree is inconsistent *within itself*, including within the same controller:

| Controller::method | Checks present | Verdict |
|---|---|---|
| `AttendanceController::index` | `class_teacher_id = Auth::id()` on `StandardLink` | **Correct** — relationship-scoped, no Gate needed |
| `HomeworkController::pendingList`, `completedList` | `class_teacher_id = Auth::id()` filter | **Correct**, same pattern |
| `HomeworkController::destroy` | `Gate::allows('homework', $homework)` | Tenant-only (F18) |
| `HomeworkController::show`, `edit`, `update` | **none** | Unscoped — **F22** |
| `AssignmentController::show`, `update`, `destroy` | **none** (all three, including `destroy`) | Unscoped — **F22** |
| `LeaveController::show`, `update`, `destroy` (teacher's own leave) | **none** | Unscoped, cross-tenant — **F21** |
| `StudentLeaveController::approveStore`, `rejectStore` (approving a student's leave) | **none** | Unscoped, cross-tenant — **F21** |
| `MeController::myInfo` | N/A — resolves via `Auth::user()->id`, no route param at all | **Correct by construction** — nothing to check |

The `HomeworkController` row is the most diagnostic finding of this whole pass: `destroy` on a `Homework` record checks `Gate::allows('homework', ...)`; `show`, `edit`, and `update` on the *same resource, same controller* check nothing. Whoever wrote this controller knew the check belonged there — they applied it to one of four CRUD-adjacent methods and not the other three. That's a stronger argument for structural (not per-method) enforcement than F18 alone made, because F18 could be read as "the check exists but is too shallow." F22 shows the check can simply be missing on a sibling method with no signal anything is wrong.

---

## 4. Positive patterns worth standardizing on

Three genuinely correct patterns exist in this codebase already, each independently arrived at:
- **`ChildrenController::showChildren()`** — intersect requested ID against caller's own relationship rows before querying. The template for parent/student-facing single-record fetches.
- **`Api\Teacher\AttendanceController::index()` / `HomeworkController::pendingList`/`completedList`** — filter the query itself by `class_teacher_id = Auth::id()` at the `StandardLink` level, rather than fetching first and checking after. Arguably cleaner than a post-hoc Gate check (F18's shape) since an unauthorized record is never loaded at all.
- **`MeController::myInfo()`** (and presumably its teacher-side equivalent) — no route parameter, identity comes entirely from the session/token. This is the same "resolve self from session, never from a route parameter" pattern `06-student-domain-map.md` already flagged as the one identity-resolution pattern in this codebase that's already correct.

None of these are unique mechanisms — they're the same idea (constrain the query to what the relationship graph actually allows) applied at three different layers (post-fetch intersect, pre-fetch query filter, no-param self-resolution). SchoolOS's `relationshipScope` (`12-schoolos-architecture.md` §3a) should be flexible enough to express all three, since GegoK12 itself shows all three are viable depending on the shape of the endpoint.

---

## 5. SchoolOS implications

- **F20 was undersold as an attendance-specific finding — it's the default shape of the entire parent/student `Api/*` surface.** SchoolOS's `relationshipScope` requirement (§3a) isn't a fix for one controller, it's a fix for a pattern repeated across at least 9 controllers.
- **Write endpoints carry a distinct risk read endpoints don't: identity spoofing.** `HomeworkController::store`, `AssignmentController::store`, and `LeaveController::store` all let a caller act *as* an arbitrary student ID, not just *view* one. SchoolOS's scope layer must apply identically to writes — `relationshipScope` should gate the mutation, not just the subsequent read of what was mutated.
- **F21/F22 show that even a known-correct pattern (Gate checks, per-method discipline) fails silently when applied per-method by hand** — `HomeworkController::destroy` remembering the Gate while `show`/`edit`/`update` don't, in the same file, is the clearest evidence in the whole audit that SchoolOS needs a mechanism that makes "add the check" the default for every ID-resolving endpoint, not an opt-in a developer can forget three times out of four.
- **Confirms `class_teacher_links`-style filtering (query-level, not post-fetch) is a better implementation shape than a Gate check** where it's feasible — `Api\Teacher\AttendanceController::index` never loads another teacher's data at all, vs. F18's web-side `Gate::allows('member', $user)` which fetches the full student record and then decides whether to discard it. SchoolOS's `ScopeService.relationshipScope` should default to constraining the query builder, with the post-fetch-check shape as a fallback only where a query-level constraint genuinely isn't expressible.

---

## 6. Traceability additions

| Finding | SchoolOS response |
|---|---|
| F20 (generalized this pass — ~9 controllers, same shape) | `12-schoolos-architecture.md` §3a: `relationshipScope` mandatory on every `Api/*` single-record endpoint, read and write |
| F21 — `TeacherLeaveApplication` fully unscoped, both subtrees | §3a tier-3 (unscoped): `tenantScope` + `relationshipScope` both structurally required, never per-method opt-in |
| F22 — `HomeworkController`/`AssignmentController` show/update unscoped, sibling `destroy` correct | §3a: shared enforcement mechanism, not per-controller-author discipline; motivates the proposed lint/test check |

---

**Open items:**
- Route-level facts (exact `/api` sub-paths, whether `Api/Teacher/*` sits under a distinct URL prefix from top-level `Api/*`) need `routes/api.php` + `routes/teacherapi.php`, which weren't in this source pack (`app/` only) — re-verify against `04-route-map.md` once those files are available again, rather than treating this document's subtree split as route-confirmed.
- ~~Not every `Api/*`/`Api/Teacher/*` controller got a full line-by-line pass...~~ **Closed this pass.** All 6 previously-suspect controllers are now reviewed: `EventGalleryController` (F23), `NoticeBoardController` (F24), `TaskController` (F25) were already logged; `LessonPlanController` (F26), `FeedbackController` (F27), and `Search/UserSearchController` (F28) are newly logged this pass. Hit rate holds: all 3 newly-reviewed controllers had at least one gap, bringing the running total to 12 of ~14 candidate controllers with the pattern. No remaining unreviewed controllers in either `Api/*` subtree at the controller-body level.
- **Partially resolved:** whether students hit the same top-level `Api/*` controllers or a dedicated namespace. `04-route-map.md` confirms a distinct `App\Http\Controllers\Student` namespace exists under `/student`, middleware group `web, auth, student` — i.e., a session-based web surface separate from the Sanctum-token `api` group `Api/*` sits under. This is real evidence students have their *own* dedicated route group rather than sharing `Api/*` by default. **Still not fully closed:** this doesn't rule out students *also* authenticating against `Api/*` via a mobile client (the same way parents do) in addition to the web `/student` surface — that would require the actual `routes/api.php` (not in this source pack) to confirm which `usergroup_id`s the Sanctum guard accepts on that subtree. Treat as "likely separate, not yet proven exclusive."
- `Search/UserSearchController` — now reviewed, see F28. Worse than the F20 pattern: no scoping of any kind (not even tenant-only), and cross-tenant by design rather than by omission.
