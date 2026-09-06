# 09 — Attendance Domain Map (GegoK12, verified from real `app/` source)

**Status:** Verified against actual controllers, the actual write-path trait, the form-request validators, and the model — not grep. Confirms and sharpens `05-database-map.md` §3 and `11-security-findings.md` F13, both previously schema-only observations.

---

## 1. Where attendance-taking actually lives

Three controllers expose attendance-taking, all calling the same underlying write path:

| Controller | Route context | Methods |
|---|---|---|
| `Teacher\AttendanceController` | web, `/teacher` | `list`, `create`, `store`, `export` |
| `Admin\AttendanceController` | web, `/admin` | `list`, `create`, `store`, `export`, `student`, `studentList` |
| `Api\Teacher\AttendanceController` | API/mobile | `index`, `store`, `absentList` |

All three `store()` methods do the same thing: validate via an `AttendanceAddRequest`, then call `$this->createAttendance(...)` from the shared `App\Traits\AcademicProcess` trait. **This is a genuine positive** — unlike the login flow (`02-authentication-map.md` §2, three+ divergent implementations), attendance-writing is centralized in one trait method reused by web admin, web teacher, and API. There is no separate "staff attendance" write path confusion either — `createStaffAttendance` exists as a distinct, parallel trait method for non-student attendance and is out of scope here.

There is no `Student\AttendanceController` — students/parents can only read attendance (via `Api\AttendanceController::index($student_id)`), never write it. Consistent with role expectations, not a gap.

## 2. The write path: `AcademicProcess::createAttendance()`

```php
for ($i = 0; $i < $data->absentCount; $i++) {
    $attendance = new Attendance;
    $attendance->school_id = ...; $attendance->academic_year_id = ...;
    $attendance->standardLink_id = $data->standardLink_id;
    $attendance->date = ...; $attendance->session = $data->session;
    $attendance->user_id = $data->{'user_id'.$i};
    $attendance->status = 0;   // absent
    $attendance->recorded_by = $admin;
    $attendance->save();
    // + fires SinglePushEvent + SingleNotificationEvent per absent student's parents
}
for ($i = 0; $i < $data->presentCount; $i++) {
    $attendance = new Attendance;
    // same fields, status = 1 (present)
    $attendance->save();
}
```

**Confirmed:** `status` is written as a literal `0`/`1` integer with no enum, matching the schema finding in `05-database-map.md` §3 exactly. There is no `late`/`excused` status anywhere in this write path — the schema's boolean design and the application's boolean design agree; a "late" concept doesn't exist end-to-end, not just at the schema layer.

**No duplicate check inside `createAttendance` itself.** The loop does a bare `new Attendance; ->save()` with no query beforehand checking whether a row already exists for that `(user_id, date, session)`. Whatever duplicate protection exists is entirely upstream, in the form request — see §3.

## 3. Duplicate prevention IS attempted in application code — but only at class-session granularity, and it's race-condition prone

`AttendanceAddRequest::rules()` (the web/admin version, `App\Http\Requests\AttendanceAddRequest`) registers a `check_session` validator:

```php
Validator::extend('check_session', function (...) {
    $attendance = Attendance::where([
        ['school_id', ...], ['academic_year_id', ...],
        ['date', $date], ['session', request('session')],
        ['standardLink_id', $standardLink_id]
    ])->exists();
    return !$attendance;   // fails validation ("Attendance already updated") if any row already exists
});
```

This **does** function as a duplicate guard, but with two real limitations:

1. **Granularity is class+date+session, not per-student.** It doesn't check `(user_id, date, session)` — it checks "has *any* attendance row been recorded for this whole class on this date/session at all." That's actually stricter in one sense (blocks the entire re-submission, not just individual student rows) but coarser in another: it can't distinguish "this specific student was double-marked" from "the whole class was re-submitted." Functionally it does prevent the classic duplicate-row scenario the schema finding (F13) worried about, as a side effect of blocking any second submission outright.
2. **Classic check-then-act race condition, no locking, no DB constraint behind it.** The `->exists()` check and the subsequent batch of `->save()` calls in `createAttendance` are not wrapped together atomically against this specific check — two concurrent `store()` requests for the same class/date/session (a genuinely plausible scenario: a slow connection causing a UI double-submit, or two staff members opening the same class simultaneously) can both pass `check_session` before either has committed its inserts, since Laravel form-request validation runs to completion *before* the controller/trait's `DB::beginTransaction()` in `createAttendance` even starts. Nothing in the schema (`05-database-map.md` §3, F13) backs this up — there is still no unique constraint on `(user_id, date, session)` at the database level. **This confirms F13's original point exactly**: the duplicate-prevention that exists is application-only, and is exactly the kind of check-then-act logic that a DB-level constraint is meant to make redundant/safe.

The `Api\Teacher` version of `AttendanceAddRequest` (`App\Http\Requests\API\Teacher\AttendanceAddRequest`) has the identical `check_session` logic, just against differently-named request fields (`Standardlinkid`/`Date`/`Session` vs. `standardLink_id`/`date`/`session`) — another small instance of the naming/shape inconsistency pattern already noted for auth in `02-authentication-map.md` §2.

Additionally, `check_user` (both versions) prevents the *same student* appearing twice **within one submitted payload** — a client-side payload-integrity check, unrelated to the schema-level concern.

## 4. No correction, no edit, no audit trail — confirmed, not inferred

Every attendance controller reviewed (`Teacher`, `Admin`, `Api\Teacher`) was checked for update/edit/correction methods. **None exist.** The only mutating method anywhere in the attendance domain is `store()` (create). There is no `update()`, `edit()`, `correct()`, or soft-delete-triggering endpoint exposed in any of the three controllers.

Consequences:
- Once attendance is recorded for a class/date/session, `check_session` **permanently blocks any further submission for that exact combination** — there is no in-app way for a teacher or admin to fix a mistake (e.g., marked the wrong student absent). The soft-deletes on the `Attendance` model (`use SoftDeletes;`, confirmed in `app/Models/Attendance.php`) exist as an Eloquent trait, but nothing in the reviewed controllers ever calls `delete()` on an attendance record, so soft-deletes are present at the model level with no code path that uses them for corrections.
- This sharpens `05-database-map.md` §3's "we haven't yet seen the controller that would enforce it" into a confirmed finding: it's not that correction exists elsewhere unreviewed — the entire attendance domain (three controllers, one shared write trait, two request classes) has been read, and no correction path exists anywhere in it.

## 5. Parent notification on absence — real-time, per-parent fan-out

Also confirmed in `createAttendance`: for every student marked absent, the loop does `foreach ($student->parents as $parent)` and fires both a `SinglePushEvent` and a `SingleNotificationEvent`, plus a separate `sendToAttendanceReminder(...)` call (SMS/email, not yet traced further). This is a real, working feature — parents are notified same-day when their child is marked absent — but it happens **inside** the same loop/transaction as the attendance write, meaning a slow notification dispatch or a failure in the parent-lookup (`$student->parents`) could affect the attendance-recording transaction's timing or failure behavior. Not confirmed as a bug (event dispatch is likely queued, not synchronous — would need `App\Events\SinglePushEvent`/`SingleNotificationEvent` reviewed to confirm queuing), but worth noting as a coupling between "record attendance" and "notify parents" that SchoolOS should deliberately decouple (e.g., an `AttendanceRecorded` domain event that a separate notification listener reacts to, rather than notification logic inline in the write path).

## 6. SchoolOS implications (sharpens `12-schoolos-architecture.md` §5)

The existing SchoolOS design doc (§5) already calls for event-sourced attendance with a schema-level unique constraint and a `corrections` sub-entity. This pass confirms every part of that reasoning against the actual application code, and adds:

- The unique constraint needs to be at **`(user_id, date, session)`**, not class-level — GegoK12's class-level check is a workaround for not having the finer-grained constraint, and it's why no correction flow could ever be safely built on top of it (you can't "just resubmit one student" when the guard blocks the whole class-session).
- A correction flow is not just a nice-to-have gap — it's a **complete absence of a currently-missing capability** in the reference implementation. SchoolOS gets to design this from scratch, not adapt an existing (broken) one.
- Decouple notification dispatch from the attendance-write transaction — model it as a domain event (`AttendanceRecorded`/`AttendanceCorrected`) with a separate listener, per the reference implementation's fan-out-in-the-write-path pattern being worth avoiding, not copying.

---

**Open items:**
- `Api\Teacher\AttendanceController::absentList()` and `Api\AttendanceController::index()` (read paths) not yet traced in detail — low priority, read-only.
- `createStaffAttendance` (staff/non-student attendance) — parallel trait method, not covered in this pass; would need its own short review if SchoolOS treats staff attendance as in-scope.
- `sendToAttendanceReminder` — not yet traced; would confirm SMS/email dispatch mechanics referenced in §5.
- `App\Events\SinglePushEvent` / `SingleNotificationEvent` — not yet reviewed; would confirm whether these are queued (async) or synchronous within the attendance-write transaction.
