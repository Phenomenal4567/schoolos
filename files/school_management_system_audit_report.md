# School Management System --- Codebase Audit Report

## Audit Summary

The codebase has been verified against
`school_management_system_discovery_hierarchy.md`.

### Overall Findings

-   **Genuinely missing:** 8
-   **Already built (contrary to previous understanding):** 5
-   **Built, but working in the wrong way:** 1

------------------------------------------------------------------------

# 1. Genuinely Missing Features

## 3. Admin Access to All Class Timetables

This feature is genuinely missing.

There is no `Admin\TimetableController` in the codebase.

The routes file also confirms this limitation through its own comment:
the admin surface is **"write-path-focused, not a full read UI"**,
explicitly identifying timetable as one of the resources without an
admin read view.

### Required

Admin should be able to view and manage class timetables from the admin
interface.

------------------------------------------------------------------------

## 4. Session Mark = CA (40) + Exam (60) = 100, School-Configurable

This functionality does not exist in any form.

The `exams` and `exam_marks` tables do not contain:

-   An `exam_type` field distinguishing CA from Exam
-   Weight or percentage fields
-   Aggregation logic
-   Configurable assessment components

Currently, `ExamMark` stores only a flat:

-   `marks_obtained`
-   `max_marks`

There is no concept of separate CA and Exam components, let alone
school-configurable weighting.

### Required

The system should support assessment components such as:

-   CA --- 40%
-   Exam --- 60%
-   Total --- 100%

The weighting should be configurable by each school.

------------------------------------------------------------------------

## 5. Admin + Teacher Can Both Edit Results, With an Edit Trail

This feature is missing.

There is no `Admin\ExamController`, meaning administrators currently
cannot access or modify examination results.

Additionally, `ExamMarkRepository::record()` uses `updateOrCreate()`
without audit logging.

This differs from attendance corrections, where the system uses the
`AuditLog` / `AttendanceCorrection` mechanism.

### Required

Both teachers and administrators should be able to edit results.

Every modification should record an audit trail showing information such
as:

-   Who made the change
-   What was changed
-   Previous value
-   New value
-   When the change occurred

------------------------------------------------------------------------

## 6 & 7. Admin Review Before Publishing / Admin Edit After Publishing

These features are also missing.

The root cause is the absence of an admin examination surface.

There is no `Admin\ExamController`.

Furthermore, `ExamMark` has no status field such as:

-   Draft
-   Reviewed
-   Published

Currently, marks can move directly from teacher entry to parent/student
visibility without an administrative review gate.

### Required Workflow

A suitable workflow would be:

1.  Teacher enters results
2.  Results remain in draft/review state
3.  Admin reviews results
4.  Admin can edit/correct results if necessary
5.  Admin publishes results
6.  Parent/student can view the published results

------------------------------------------------------------------------

## 8. Admin Uploading Lesson Documents

This is missing.

`Admin\LessonPlanController` currently only provides:

-   `approve()`
-   `reject()`

It is therefore a review workflow for teacher-submitted materials rather
than an authoring/upload workflow for administrators.

There is no `store()` action that allows an admin to upload their own
lesson materials.

### Required

Administrators should be able to upload and manage lesson documents
directly.

------------------------------------------------------------------------

## 10. Downloading/Exporting Results as PDF

Student/parent result PDF downloading is not implemented.

The only existing export functionality is through `ExportJobController`.

That feature is a **data-backup/export tool** that produces CSV files
for items such as:

-   Roster
-   Attendance
-   Exam marks

It is not a student/parent-facing result download feature.

There is also no PDF/download functionality referenced in the student or
parent examination controllers/views.

### Required

Parents and students should be able to download their results as a
properly formatted PDF.

------------------------------------------------------------------------

## 15. Teacher & Staff Enrollment Form

This is missing.

There is no route or controller that provides a teacher/staff enrollment
form or creates teacher/staff users through the admin interface.

Currently, staff accounts only exist through:

`database/seeders/UserSeeder.php`

### Required

Admin should have an enrollment form for creating:

-   Teachers
-   Staff members

The process should create the appropriate user account and staff
profile.

------------------------------------------------------------------------

## 16. Parent Enrollment Form

This is missing.

`Admin\ParentLinkController` only links an already-existing parent
account to an already-existing student.

It does not provide functionality to register/create a new parent.

### Required

Admin should be able to:

1.  Create/register a parent
2.  Enter the parent's details
3.  Create the parent account
4.  Link the parent to one or more students

------------------------------------------------------------------------

## 17. Teacher Profile Page

A teacher profile page does not exist.

There is no teacher profile route or view in the codebase.

The only identified profile-related implementation is
`StudentHealthProfile`, which is unrelated to a teacher profile.

### Required

Teachers should have a profile page where relevant information can be
viewed and, where appropriate, updated.

------------------------------------------------------------------------

## 14. Calendar Visible to Teachers

This feature is partially missing.

Working calendar implementations exist for:

-   Admin
-   Parent
-   Student

However, there is no:

`TeacherPortal\CalendarEventController`

There is also no calendar route under:

`/teacher/*`

### Required

Teachers should have access to the school calendar and relevant calendar
events through their teacher portal.

------------------------------------------------------------------------

# 2. Already Built Features

The following features were verified as already existing and functional.

## 1. Teacher Attendance

Teacher attendance already exists.

The codebase contains:

-   `StaffAttendance\CheckInController` --- teacher/staff self check-in
-   `Admin\StaffAttendanceController` --- admin QR scanning

The only attendance variant that is not implemented is the
**kiosk/school-displayed-QR self-scan variant**.

Manual/admin scanning and the existing self check-in functionality work.

------------------------------------------------------------------------

## 9. Teachers Uploading Lesson Notes

This feature already exists and works.

Implementation:

`TeacherPortal\LessonPlanController::store()`

Teachers can upload lesson notes/materials through the existing
lesson-plan functionality.

------------------------------------------------------------------------

## 11. Automatically Generated Student ID

This feature is already implemented and matches the specified format.

The implementation is located in:

`app/Services/IdentifierService.php`

The method:

`generateStudentId()`

generates IDs following the specified structure, for example:

`SCH+ADE+SS2+001`

The ID is generated automatically during enrollment.

A parallel:

`generateStaffId()`

implementation also exists for staff.

------------------------------------------------------------------------

## 12. Student & Teacher ID Cards

ID cards are already implemented.

The functionality is provided by:

`Admin\IdCardController`

It covers both:

-   Students
-   Teachers/staff

The ID cards include relevant information such as:

-   Photo
-   ID
-   QR code

------------------------------------------------------------------------

## 13. Learning Materials for Parents

This feature already exists and is functional.

Implementation:

`ParentPortal\LearningMaterialController`

Parents can access the available learning materials through the parent
portal.

------------------------------------------------------------------------

# 3. Built, But Working in the Wrong Way

## 2. Student Shouldn't See Fee Owing

This feature is implemented in the opposite direction of the intended
requirement.

`StudentPortal\FeeController` deliberately displays the student's:

`amount_remaining`

This means students can currently see the amount they owe.

There is also a code comment explicitly framing the student-facing fee
view as intentional, referring to it as:

> "any future student-facing fee view"

However, the discovery document's Section 11 specified a **parent fee
portal**, not a student-facing fee-owing view.

### Required Change

The student-facing fee-owing functionality should be:

-   Removed, or
-   Locked down so students cannot see outstanding fee balances

Parents should retain access to the appropriate fee information.

------------------------------------------------------------------------

# 4. Consolidated Gap List

  \#   Requirement                                    Status
  ---- ---------------------------------------------- -------------------
  1    Teacher attendance                             Built
  2    Student should not see fee owing               Built incorrectly
  3    Admin access to all class timetables           Missing
  4    CA + Exam weighting                            Missing
  5    Admin + teacher result editing + audit trail   Missing
  6    Admin review before publishing                 Missing
  7    Admin edit after publishing                    Missing
  8    Admin lesson document upload                   Missing
  9    Teacher lesson note upload                     Built
  10   Result PDF download/export                     Missing
  11   Auto-generated student ID                      Built
  12   Student/teacher ID cards                       Built
  13   Parent learning materials                      Built
  14   Teacher calendar                               Partially missing
  15   Teacher/staff enrollment                       Missing
  16   Parent enrollment                              Missing
  17   Teacher profile page                           Missing

------------------------------------------------------------------------

# 5. Final Assessment

The audit shows that several features previously believed to be missing
are already implemented.

The most significant remaining gaps are concentrated around:

1.  **Result management and governance**
    -   CA/Exam weighting
    -   Result editing
    -   Audit trails
    -   Admin review
    -   Publishing workflow
    -   PDF result generation
2.  **User/account management**
    -   Teacher/staff enrollment
    -   Parent enrollment
    -   Teacher profiles
3.  **Portal completeness**
    -   Teacher calendar
    -   Admin timetable viewing
    -   Admin lesson-material uploads
4.  **Scope correction**
    -   Remove or restrict student access to outstanding fee balances

These findings should be used as the basis for the next development
phase and gap-closure tickets.
