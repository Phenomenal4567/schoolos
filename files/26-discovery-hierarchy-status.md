# 26 - Discovery Hierarchy Status

**Purpose:** Working status map for `school_management_system_discovery_hierarchy.md`, checked against the current SchoolOS codebase. This file tracks what is done, what is partial, and what remains incomplete so the discovery list does not drift behind implementation again.

**Last verified:** 2026-09-05. Latest full test run: `php artisan test` passed - 556 tests, 1666 assertions (up from 490/1496 earlier the same day — the +66 are the new Account Creation & Onboarding system's coverage: invitations, setup wizard, staff portal, parent self-service linking, direct student registration). See `27-schoolos-current-status-2026-09-05.md` for the test-health snapshot, the three timetable-settings bugs fixed on 2026-09-05, and the Subject Attendance build note.

## Account Creation & Onboarding (2026-09-05)

Closes the onboarding/invitation gap across all five personas without touching existing auth/RBAC:

- **Invitations** (new `invitations` table/model/`InvitationRepository`, `App\Notifications\InvitationNotification`, `Public\InvitationController` at `/invitations/accept`): admin-initiated activation for teacher/staff/parent/school-admin accounts, additive to the existing "admin types a password" path. Hashed single-use token, expiry, `users.status` gained an `'invited'` value.
- **School Admin setup wizard** (`Admin\SetupWizardController`, `/admin/setup`, `schools.setup_status`/`setup_step`): a guided, resumable, non-gating checklist over the *existing* admin dashboard forms — no academic/subject/staff CRUD was rebuilt.
- **Staff Portal** (`StaffPortal\DashboardController`, `/staff/dashboard`): minimal generic landing page for accountant/librarian/receptionist/staff roles, which previously had no dashboard of their own at all (`SessionController`/`AuthenticationService::dashboardPathFor()` fell through to `/`).
- **Parent self-service** (`Public\ParentRegistrationController`, `ParentPortal\ChildLinkRequestController`, new `student_parent_links.status = 'pending'`, `Admin\ParentLinkController::approve()`/`reject()`): a parent can register and request a child link, but `ParentLinkRepository::link()` — the only thing that ever writes `'active'` — still only ever runs from an admin approval action, preserving that repository's existing "admin-initiated, not self-service" contract exactly.
- **Student registration** (`StudentRepository::register()`, `Admin\StudentController::store()`/`invite()`): admin-direct student creation (previously only the public admission form could create a new student `User` row) plus an explicit "enable portal login" action, expressing STUDENT RECORD vs. STUDENT LOGIN ACCOUNT without a schema change.
- **Security fix found along the way**: `AuthenticationService::authenticate()` checked school-level suspension but never the user's own `status` column — an `'inactive'`/`'exited'` account with a known password could still log in. Closed with `AccountDisabledFailure`/`AccountNotActivatedFailure`.

**Known gaps, by design:** multi-role-per-user (schema is single-`role_id`, out of scope — would be an RBAC redesign); rich per-staff-role feature portals (accountant/librarian-specific screens don't exist anywhere in SchoolOS yet); admission-created students still default to `status = 'active'` with an unusable password rather than `'invited'` (left untouched to avoid changing existing, tested behavior — only the new direct-registration path uses `'invited'`).

## Summary

Most of the discovery hierarchy is implemented. The remaining gaps are concentrated around department modeling, subject-level attendance detail, configurable notification policy, advanced staff-attendance verification methods, CBT delivery, WhatsApp-specific integration, and export retention policy.

## Done

### 1. School Setup & Administration

- **School Profile:** Done. School name, logo, initials, location, Google Maps URL, contact information, and school type are implemented through super-admin school management and admin school-profile editing.
- **Academic Structure:** Mostly done. Academic sessions/years, terms, classes/standards, arms/sections, subjects, class teachers, and subject-teacher assignments exist. As of 2026-09-04, the school-wide period grid (period number + start/end time, `timetable_periods`) and per-school working-days configuration (`schools.working_days`) are also in, editable through `Admin\TimetableController::updateSettings()`.
- **School Calendar / Agenda:** Done. Calendar events support resumption/term/activity/holiday-style event records, with admin authoring and parent/student/teacher visibility.

### 2. Student Management

- **Student Registration / Enrollment:** Done. Admin enrollment exists for already-admitted students, and the public/admin admission workflow exists for application-to-enrollment.
- **Student Profile / Records:** Done for the current scope. Student records, class assignment, parent links, and medical information are modeled.
- **Student ID:** Done. `IdentifierService` generates student IDs using school/class/name-derived components.
- **Student ID Card / QR:** Done. ID-card rendering and QR-token issuance exist through `Admin\IdCardController` and `QrTokenService`.

### 3. Parent & Student Portal

- **Parent Portal:** Done for profile, attendance, results, fees, amount paid/remaining, payment history, calendar, announcements, learning materials, result PDF download, attendance notifications, online payment, and receipt upload.
- **Student Portal:** Done for attendance, results, result PDF download, learning materials, timetable, school calendar, announcements, and related read-only academic surfaces. Student fee outstanding-balance visibility was intentionally removed because the discovery fee portal requirement is parent-facing.

### 4. Student Attendance

- **Daily/Class Attendance:** Done. Morning and afternoon sessions are supported; teachers can mark class attendance.
- **Subject Attendance:** Done, as of 2026-09-05. `subject_attendance_records` mirrors `attendance_records`' shape (same status enum, same lockForUpdate()-then-correct upsert, same dedicated `subject_attendance_corrections` table + `audit_logs` entries) but is keyed by `timetable_slot_id` instead of a session enum, so a teacher records attendance per subject period, not just per class/session. Only the teacher on that slot's `teacher_id` — not just any teacher assigned to the class — can mark or view it (`SubjectAttendanceController`, `/teacher/subject-attendance`). "Topic taught" is a separate `subject_attendance_topics` row keyed by `(timetable_slot_id, date)` — a session-level fact, not duplicated per student — upserted via `SubjectAttendanceRepository::recordTopic()`.
- **Parent Notifications:** Partially done. Absence notifications and absence corrections are implemented for class attendance; subject attendance has no notification wiring yet (matches class attendance's own `AttendanceMarked`/`AttendanceCorrected` events, which also have no listener — see `SubjectAttendanceMarked`/`SubjectAttendanceCorrected`).

### 5. Staff / Teacher Attendance

- **Staff Attendance Core:** Partially done. Staff attendance records, manual check-in, QR check-in, duplicate prevention, and admin/staff scoping exist.
- **Staff ID Card:** Mostly done through staff IDs, photos, and QR-token infrastructure.

### 6. Teacher & Staff Management

- **Teacher/Staff Enrollment:** Partially done. Admin staff account creation exists and staff IDs can be generated.
- **Teacher Profile:** Done. Teachers view/update their own basic profile (`TeacherPortal\ProfileController::update()`) plus richer fields on a peer `staff_profiles`/`staff_documents` pair (`StaffProfileRepository`): qualifications, responsibilities, CV upload, any number of labeled supporting-document uploads, and a "school rules acknowledged" timestamp. `Admin\StaffProfileController` gives a school_admin read-only oversight of any of that school's own staff (profile fields + document downloads), tenant-scoped through `ScopeService`. Every write is gated to `StaffAttendanceRepository::STAFF_ROLE_KEYS` (the existing staff-role set), so a student/parent/super_admin cannot acquire a staff profile.
- **Teacher Dashboard:** Done for current role-scoped academic surfaces: classes, subjects, student attendance, lesson notes, scheme of work, results, timetable, announcements, and notifications.

### 7. Academic Management

- **Scheme of Work:** Done. Admin upload/authoring and teacher/parent/student read surfaces exist.
- **Lesson Management:** Done for viewing scheme of work, uploading lesson documents/notes, teacher-authored lessons, and admin lesson-document upload.
- **E-Textbooks / Learning Materials:** Done. Learning materials exist across admin/teacher/parent/student surfaces with read receipts where applicable.

### 8. Examination & Results

- **Exam/Marks Core:** Done. Exams, components, weighted marks, teacher entry, admin review/edit/publish, audit logging, parent/student visibility gates, and PDF downloads exist.
- **Rich Report Card Fields:** Done. `ExamRemark` (a peer table to `exam_marks`, keyed by exam_id+student_id) carries a class-teacher remark (written by the exam's own class_section teacher, gated the same way `ExamMarkRepository` gates mark entry) and a proprietor remark (written by that school's school_admin — this schema's stand-in for "proprietor/proprietress," since there is no distinct proprietor role). `AttendanceSummaryService` computes "times school opened" / "times the student attended" for the AcademicTerm covering the exam's `exam_date` (falling back to the whole academic year when no such term is configured), since neither `Exam` nor `AttendanceRecord` carries an `academic_term_id`. Both feed into `ExamResultPdfService::render()` and are shown read-only on the parent/student exam pages alongside marks.

### 9. Student Promotion

- **Automatic Promotion:** Done. Promotion rules and automatic evaluation exist.
- **Manual Promotion:** Done. Admin override with required reason exists.

### 10. School Fees & Payments

- **Fee Categories:** Done.
- **Flexible Payment:** Mostly done. Online payments, manual payments, part-payments, and parent receipt uploads exist.
- **Online Payment:** Done for Paystack initiation plus webhook confirmation.
- **Discounts / Scholarships:** Done.

### 11. Parent Fee Portal

- **Current Session:** Done. Total fee, amount paid, amount remaining, payment status, and payment history are visible to parents.
- **Previous Sessions:** Mostly done. Rolled-over balance and debt rollover are modeled.
- **Receipt Upload:** Done. Parent-uploaded receipts create pending manual payment rows for review.

### 12. Enrollment / Admission

- **Digital Admission Workflow:** Done. Public application, applicant data, parent/guardian information, medical information, fee-category acknowledgement, documents metadata, admin review/reject/withdraw/accept, and student/enrollment creation exist.

### 13. School Finance / Admin Dashboard

- **Revenue / Expenses / Cash Flow / Transactions:** Done for current scope. Fee/payment totals, outstanding debt, rolled-over debt, expenses, and finance dashboard reporting exist.
- **Fee / Debt Notifications:** Done. `FeeAssessed`/`PaymentConfirmed` events (fired from `FeeAssessmentRepository::assess()` and `PaymentRepository`'s confirm paths) notify every active parent of a new fee or a confirmed payment. `fees:send-reminders` (`SendFeeReminders`, scheduled daily) notifies parents of assessments 3 days from `due_date` (one-shot) and overdue assessments (repeating every `schools.fee_overdue_reminder_days` days, default 7), reusing `FeeAssessment::amountRemaining()` as the one definition of "outstanding." The overdue-repeat cadence is per-school and editable by that school's own school_admin through the existing school-profile edit form (`Admin\SchoolProfileController`). Parent-facing only, matching the fee portal's own parent-facing scope.

### 14. Communication

- **Internal Communication:** Mostly done. Announcements, notifications, attendance alerts, and parent/student feedback exist.

### 15. Backup & Data Management

- **Exports / Backups:** Done. Export jobs, downloadable generated exports, scheduled export command, and pruning command exist.

### 16. Security & Access

- **Authentication:** Done. Login supports the generalized identifier/password pattern through `AuthenticationService`; school suspension behavior is enforced.

### 17. Role-Based Access Control

- **RBAC / Scoping:** Done for current role set. `Role`, `Permission`, route middleware, `ScopeService`, and super-admin schoolless scoping are implemented.

## Partial / Incomplete

### Departments

Discovery lists departments under academic structure. No department model, table, controller, or assignment workflow was found.

### Configurable Attendance Notifications

Absence notifications exist, but no school-level setting was found for configuring whether parents receive instant attendance notifications for every attendance event.

### Advanced Staff Attendance Methods

Manual and QR check-in are built. Geolocation, camera/selfie verification, face/liveness verification, fingerprint, and similar methods remain unbuilt or explicitly unsupported.

### CBT Examination Delivery

The exam/marks governance flow is built, but CBT-specific examination delivery is not. Written-vs-CBT mode is not fully modeled as a delivery workflow.

### WhatsApp Integration

No WhatsApp API/channel integration was found. The internal announcement/notification system covers the integrated-communication alternative, but WhatsApp-specific delivery remains a product decision/gap.

### Export Retention / Storage Policy

Exports are built, but a fuller retention/storage policy for long-lived school records remains a product/security decision.

## Housekeeping Closed

- Removed dead duplicate controller file `app/Http/Controllers/Admin/Promotioncontroller .php`.
- Removed dead duplicate controller file `app/Http/Controllers/ParentPortal/Promotioncontrolle.php`.
- 2026-09-05: fixed a live 500 on `GET /teacher/timetable` caused by a stale compiled Blade view cache referencing the already-removed `teacher.timetable.store` route (`storage/framework/views/*.php`; cleared via `php artisan view:clear`).
- 2026-09-05: fixed a live 500 on the timetable settings screen (`Admin\TimetableController::settings()`/its view) — `School::$casts` had no `array` cast for the `working_days` JSON column, so `in_array($value, $selectedDays, true)` in `admin/timetable/settings.blade.php` crashed on a raw JSON string for any school that had already saved working days once. Added `'working_days' => 'array'` to `App\Models\School::casts()`.
- 2026-09-05: `TimetableRepository::replaceSettings()` now normalizes period start/end times to `H:i:s` before persisting, matching the `time` column type.

## Suggested Next Build Order

1. ~~Subject attendance with topic taught~~ — done 2026-09-05, see §4 above.
2. Departments, if schools actually need department-level grouping for subjects/staff.
3. Advanced staff attendance methods only after privacy, retention, and verification policy is confirmed.
4. CBT examination delivery, if product confirms computer-based delivery is required alongside the existing written-exam governance flow.
5. WhatsApp integration only if product confirms WhatsApp delivery is required rather than internal notifications being sufficient.
6. Export retention/storage policy, as a product/security decision rather than a build task.

## Build Notes

- **2026-09-05 — Subject Attendance:** New tables `subject_attendance_records`, `subject_attendance_corrections`, `subject_attendance_topics`; new models `SubjectAttendanceRecord`/`SubjectAttendanceCorrection`/`SubjectAttendanceTopic`; new `SubjectAttendanceRepository::mark()`/`recordTopic()`; new `TeacherPortal\SubjectAttendanceController` (`index`/`show`/`store`/`topic`) at `/teacher/subject-attendance`; new events `SubjectAttendanceMarked`/`SubjectAttendanceCorrected` (no listener yet, same as class attendance's own events). 23 new tests across `SubjectAttendanceRepositoryTest` and `TeacherPortal\SubjectAttendanceControllerTest`. No parent/student read surface was built — the write path only, matching class attendance's own teacher-only scope; `class_section_id`/`subject_id` are denormalized onto both new tables so a future read surface gets `ScopeService`'s generic dispatch for free.
