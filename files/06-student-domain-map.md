# 06 — Student Domain Map (GegoK12, verified from real `app/` source)

**Status:** Verified against actual controllers and models. Student is a read-mostly role: 19 controllers under `app/Http/Controllers/Student/`, no separate `Api/Student/` namespace — students and parents appear to share the top-level `Api/*` surface (confirmed for parents in `08-parent-domain-map.md`; student mobile access likely goes through the same controllers scoped by `Auth::id()`, not yet independently confirmed).

---

## 1. Controller surface (web, `app/Http/Controllers/Student/`)

| Area | Controllers |
|---|---|
| Own record | `DashboardController`, `UserProfileController` |
| Academic work | `AssignmentController`, `HomeworkController`, `TaskController` |
| Communication/social | `FeedController`, `PostsController`, `PostCommentsController`, `PostCommentDetailsController`, `PostDetailController`, `PostReplyCommentsController`, `ConversationController`, `NotificationController` |
| Content pages | `PagesController`, `PageDetailsController`, `NoticeBoardController`, `EventsController`, `HolidaysController` |
| Misc | `ActivityLogController`, `LibraryActivityController` |

Notably **no `Student\AttendanceController`** — students have no dedicated web controller for viewing their own attendance. Read access to attendance is confirmed to exist only via `Api\AttendanceController::index($student_id)` (the same top-level API surface parents use — see `08-parent-domain-map.md`). Whether the student web dashboard surfaces attendance through `Traits\Dashboard::studentDashboard()` (called from `DashboardController::index`) wasn't traced in detail this pass — worth confirming if a web attendance view matters for SchoolOS parity.

## 2. `DashboardController::index` — same non-unique-lookup risk pattern noted elsewhere is absent here (uses `Auth::id()`)

```php
$student_id = Auth::id();
$student    = User::where('id', $student_id)->first();
$standardLink_id = $student->studentAcademicLatest->standardLink_id;
$dashboard = $this->studentDashboard($school_id, $student, $standardLink_id, ...);
```

Worth noting as a **positive** contrast to the patterns flagged elsewhere (F6, and the `Teacher\StudentDetailsController::show($name)` lookup in `07-teacher-domain-map.md` §2): this resolves the student strictly from `Auth::id()`, the authenticated session — there's no way to view another student's dashboard by manipulating a route parameter, because none exists. This is the shape SchoolOS wants everywhere self-service data is involved: identity from session, never from a client-supplied identifier.

## 3. `studentAcademicLatest` — confirms the "class" concept resolution path

`$student->studentAcademicLatest->standardLink_id` is how a student's current class is resolved at runtime — this is the practical, code-level use of the `StudentAcademic` → `standardLink_id` → `standards_link` 4-way-join chain already documented in `05-database-map.md` §2 and §4. Confirms that chain is load-bearing in actual request handling, not just a schema artifact.

## 4. SchoolOS implications

- No new architectural findings beyond what's already captured — this pass mainly confirms the student surface is appropriately narrow (own-record-only, session-derived identity) and doesn't introduce new authorization risk of its own. The risk to student data comes from the *other* side — teachers/other roles viewing student records without proper scope, per F18 in `11-security-findings.md`, not from the student controllers themselves.
- SchoolOS should keep the "resolve self from session, never from a route parameter" pattern for all self-service student/parent endpoints — it's the one identity-resolution pattern in this codebase that's already correct.

---

**Open items:**
- Whether student web dashboard actually surfaces attendance (via `Traits\Dashboard::studentDashboard()`) — not traced.
- Whether students hit the same top-level `Api/*` controllers as parents (shared surface, scoped differently) or have any dedicated API namespace — not confirmed; but the underlying concern **was checked and confirmed as a real vulnerability**: `Api\AttendanceController::index($student_id)` accepts a `$student_id` parameter with **no check at all** that it belongs to the authenticated caller or one of their linked children. Logged as `11-security-findings.md` F20 (IDOR) — this is worse than merely "not yet verified."
