# 07 — Teacher Domain Map (GegoK12, verified from real `app/` source)

**Status:** Verified against actual controllers and models. Teacher is the largest role surface in the app: 40 web controllers (`app/Http/Controllers/Teacher/`), a near-parallel `Api/Teacher/` set for mobile, plus payroll/approval sub-namespaces. This pass covers the domain shape and the two most architecturally significant confirmed findings (teacher-assignment scope, and the authorization-gate gap covered fully in `11-security-findings.md` F18). It does not exhaustively review all 40+ controllers line-by-line.

---

## 1. Controller surface (web, `app/Http/Controllers/Teacher/`)

Grouped by function:

| Area | Controllers |
|---|---|
| Own class/section | `StandardsLinkController`, `StandardsLinkDetailsController` |
| Student records | `StudentDetailsController` (310 lines — student profile, attendance, fees, discipline, documents, exam marks, siblings), `StudentAssignmentController`, `StudentHomeworkController` |
| Attendance | `AttendanceController` (see `09-attendance-map.md`) |
| Academic content | `AssignmentController`, `HomeWorkController`, `LessonPlanController` (+ `LessonPlanAddController`, `LessonPlanEditController`) |
| Approval workflow | `Approval/AssignmentController`, `Approval/AssignmentApprovalController`, `Approval/HomeWorkController`, `Approval/HomeWorkApprovalController` — this is the Laratrust-role-gated sub-privilege surface already flagged in `03-role-permission-map.md` §2 (`hasRole('principal')`/`hasRole('leave_checker')`) |
| Leave | `LeaveController` (own leave requests), `StudentLeaveController` (approving student leave — a *different* concern gated by the Laratrust `student_leave_checker` sub-role) |
| Communication/social | `FeedController`, `PostsController`, `PostCommentsController`, `PostCommentDetailsController`, `PostDetailController`, `PostEditController`, `PostAddController`, `PostReplyCommentsController`, `ConversationController`, `NotificationController`, `GroupController` |
| Content pages | `PagesController`, `PageDetailsController`, `NoticeBoardController`, `EventsController`, `HolidaysController` |
| Payroll (self-service) | `Payroll/PayrollController`, `Payroll/TransactionController`, `BankDetailController` |
| Misc | `VisitorLogController`, `PostalRecordController`, `CallLogController`, `ActivityLogController`, `LibraryActivityController`, `TaskController`, `UserProfileController`, `DashboardController`

`Api/Teacher/` largely mirrors this (Attendance, Assignment, Homework, LessonPlan, Approval, Leave, StudentLeave, NoticeBoard, Events, Holidays) for the mobile app, plus API-only concerns: `LoginController`, `MeController`, `SandboxController`, `SchoolController`, `FeedbackController`, `DisciplineController`, `EventGalleryController`.

## 2. Teacher-class assignment: an explicit table exists, but is display-only

**`class_teacher_links`** (model `Teacherlink`) is a real, explicit assignment table: `school_id`, `academic_year_id`, `standardLink_id`, `subject_id`, `teacher_id`. It's exactly the "explicit teacher assignment" the handoff (§47 point 7) calls for — a teacher is linked to a specific class-section-in-a-year *and* a specific subject, not just "this teacher teaches somewhere." Confirmed used for lesson plans (`Teacherlink::lessonPlan()` relation) and referenced in timetable logic.

**But it is never consulted for authorization.** `Teacher\StudentDetailsController::show()` — the controller that displays a student's full record to a teacher — gates access with `Gate::allows('member', $user)`, and that Gate closure (`AuthServiceProvider::boot()`) checks only `$user->school_id == $member->school_id`. It does not query `class_teacher_links` to confirm the requesting teacher is actually assigned to that student's class. The same pattern holds for `Gate::allows('homework', ...)` and `Gate::allows('assignment', ...)` in the corresponding controllers. **Full detail and severity assessment in `11-security-findings.md` F18** — this is the single most significant authorization finding in the audit.

There's also a second, separate lookup-by-non-unique-field pattern worth flagging alongside F6: `StudentDetailsController::show($name)` resolves the target student via `User::where('name', $name)->first()` — a route parameter matched against the non-unique `name` column, the same design smell already flagged for login in F6.

## 3. Teacher-side approval workflow rides on the Laratrust sub-role system

The `Approval/*` controllers (`AssignmentApprovalController`, `HomeWorkApprovalController`) are gated by Laratrust `hasRole('principal')`/`hasRole('leave_checker')` checks per `03-role-permission-map.md` §2 — this is the *only* meaningful use of System B (Laratrust) in the whole app. It answers "is this teacher also empowered to approve other teachers' assignments/homework/leave," a genuinely separate concept from `usergroup_id` (System A) or the newly-found Gate/Policy layer (F18). SchoolOS's "permission on a role" recommendation (`12-schoolos-architecture.md` §3) directly targets collapsing this into the Role concept rather than a bolted-on third system — confirmed as the right call, since this sub-role concept sits completely outside both other authorization mechanisms currently.

## 4. Payroll self-service exists as a teacher-facing surface

`Teacher/Payroll/PayrollController`, `Teacher/Payroll/TransactionController`, `Teacher/BankDetailController` — a teacher can view their own payroll/transactions and manage their own bank details. This is new information relative to the handoff, which flagged payroll/finance as entirely unreviewed (`12-schoolos-architecture.md`, closing note). Not deeply reviewed this pass — flagged as a concrete pointer for whenever payroll gets its own domain map.

## 5. SchoolOS implications

- The "Scope" concept in `12-schoolos-architecture.md` §3 needs `class_teacher_links`-equivalent data (teacher↔class↔subject↔year assignment) wired directly into whatever authorization mechanism SchoolOS uses — not left as organizational metadata the way GegoK12 leaves it. This is now the most evidence-backed single recommendation in the whole SchoolOS design doc.
- Keep the "sub-privilege on a role" pattern (principal/leave-checker) as a permission, per the existing recommendation — now confirmed as the one part of GegoK12's authorization sprawl that's conceptually sound, just implemented via the wrong (bolted-on) mechanism.
- Never resolve a specific record by a non-unique human-readable field (`name`) in a controller that renders sensitive data — resolve by primary key/route-model-binding, always.

---

**Open items:**
- `Api/Teacher/*` controllers not diffed line-by-line against their web counterparts — worth a pass to confirm they don't diverge in validation/authorization the way the login endpoints did (`02-authentication-map.md` §2).
- Payroll domain (`Teacher/Payroll/*`, and the presumed `Payroll/*` admin-side controllers referenced in `04-route-map.md`) not yet reviewed as its own domain.
- `LessonPlanController` approval chain and `Teacherlink::lessonPlan()` not traced in detail.
