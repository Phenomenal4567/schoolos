# SchoolOS — Master Product & Implementation Plan

> **Subordination notice (added per `16-schoolos-decisions-register.md`, closing the Architecture & Specification Consistency Audit's blocking item 3):** This document's Phase 1/MVP feature lists (§34 "Phase 1 — Must Have" and §46, below) were written from product vision and discovery interviews, independent of the GegoK12 audit. Where they conflict with `14-schoolos-implementation-plan.md`'s audit-grounded phase sequencing — specifically: Finance, exams/results, and biometric staff attendance methods (face/fingerprint) in Phase 1 — **`14`'s sequencing governs.** This plan remains the source of truth for product vision, feature scope, and long-term direction; it is no longer the source of truth for *what ships in which phase*. See `SchoolOS-Architecture-Consistency-Audit.md` §4 item 1 and §26 for the reconciled phase plan, and `16` D1–D5 for the decisions this subordination depends on.

## 1. Product Vision

**SchoolOS** will be a multi-school SaaS platform that allows schools to manage their entire operation from one system.

It should cover:

- School administration
- Students
- Parents
- Teachers and staff
- Academic management
- Student attendance
- Staff attendance
- Examinations and results
- Fees and payments
- Communication
- School calendar
- Documents and records
- Security and permissions
- Reporting and analytics

The goal is not to reproduce an existing school ERP exactly.

The goal is to build a **simpler, configurable, modern School Operating System** that can work for different types and sizes of schools.

---

# 2. Core Product Principle

## Configure the school instead of forcing the school to adapt to SchoolOS.

Different schools have different:

- Attendance systems
- Academic structures
- Fee structures
- Roles
- School calendars
- Communication preferences
- Examination systems
- Technology resources

Therefore, SchoolOS should provide configurable modules rather than one rigid workflow.

This is particularly important for attendance.

The discovery research already established that staff attendance should not use one mandatory method for every school.

---

# 3. SchoolOS Architecture

SchoolOS should be organized into major layers.

## Layer 1 — Platform

This belongs to the SchoolOS company/platform.

### Super Admin

Controls:

- Schools
- Subscriptions
- Plans
- Platform users
- Feature availability
- School activation/suspension
- Platform configuration
- System-wide analytics
- Support
- Audit logs

SchoolOS should therefore be designed as a **multi-tenant SaaS platform**, not a single-school application.

---

# 4. Layer 2 — School

Every school gets its own isolated environment.

## School Setup

During onboarding, the school configures:

### School profile

- School name
- Logo
- Initials
- Location
- Google Maps location
- Contact information
- School type

The discovery document identifies Crèche, Primary, Secondary and potentially College/Tertiary sections.

### Academic structure

- Session
- Terms
- Classes
- Arms
- Subjects
- Departments
- Class teachers
- Subject teachers

These are already identified as core school structures.

---

# 5. Role & Permission System

SchoolOS should use a proper role-based access-control architecture from the beginning.

Initial roles:

1. Super Admin
2. School Admin
3. Principal
4. Teacher
5. Accountant/Bursar
6. Staff
7. Parent
8. Student

These roles are already identified in the discovery research, although the document correctly notes that the exact roles still need validation with more schools.

## Important architectural rule

Do not build permissions simply as:

> "If user is teacher, show teacher dashboard."

Instead use:

**User → School → Role → Permissions → Resources**

This will make SchoolOS much easier to expand later.

---

# 6. Student Management

## Student lifecycle

SchoolOS should manage:

**Application → Admission → Enrollment → Student Profile → Class Assignment → Attendance → Academics → Results → Promotion → Alumni/History**

Student registration should include:

- Student information
- Parent/guardian
- Medical information
- Admission information
- Class
- Session
- Documents

The discovery document already proposes digital enrollment rather than continuing manually handled school records.

---

# 7. Student Identity

Every student should receive a unique SchoolOS school ID.

Example:

**SCH-ADE-SS2-001**

Student ID cards can contain:

- Photograph
- Student ID
- Name
- Class
- School information
- QR code

The research specifically identifies QR codes as potentially useful for attendance, identification and verification.

---

# 8. Parent & Student Portal

Parents should be able to access:

- Student profile
- Attendance
- Results
- Fees
- Amount paid
- Outstanding balance
- Transactions
- Calendar
- Announcements
- Learning materials

Students should have a restricted portal for:

- Results
- Attendance
- Learning materials
- Timetable
- Calendar

The discovery document already defines these portal requirements.

---

# 9. Student Attendance

Student attendance must remain **separate from teacher/staff attendance**.

## Daily attendance

Support:

- Morning attendance
- Afternoon attendance

The research specifically found that attendance is taken twice daily in the studied school context.

## Class attendance

Teacher records:

- Present
- Absent
- Possibly late/excused status

## Subject attendance

For subject-based schools:

**Teacher → Subject → Class → Lesson → Students → Attendance**

The teacher can also record:

- Topic taught
- Lesson information

This distinction is important because:

> A teacher being present at school does not mean every student attended that teacher's lesson.

That should be reflected in the database and reporting architecture.

---

# 10. SchoolOS Teacher & Staff Attendance

This becomes one of the core infrastructure modules.

## School configuration

During setup:

### Staff Attendance Method

The school chooses its preferred method:

- QR Code
- Selfie Upload
- Live Selfie / Face Verification
- Fingerprint
- GPS / Location Verification

The school may eventually be able to enable more than one method.

---

# 11. Attendance Method 1 — Selfie Upload

Low-resource option.

Teacher:

1. Opens attendance
2. Takes/uploads selfie
3. SchoolOS records timestamp
4. Optional GPS is captured
5. Attendance is submitted

Best suited to schools that want photographic evidence without requiring biometric hardware.

---

# 12. Attendance Method 2 — Live Face Verification

Higher-security phone-based option.

### Enrollment

Teacher registers their face once.

### Daily attendance

Teacher:

1. Opens attendance
2. Camera activates
3. Takes live selfie
4. SchoolOS compares the face with registered identity
5. Optional liveness check runs
6. GPS can be verified
7. Attendance is recorded

Possible liveness actions:

- Blink
- Turn head
- Look left/right

The objective is to prevent somebody from simply presenting another person's photograph.

SchoolOS should primarily retain the **attendance event and verification result**, rather than unnecessarily storing every attendance selfie.

---

# 13. Attendance Method 3 — QR Code

Two possible approaches:

### Staff QR

Every staff member receives a QR-enabled ID card.

### School daily QR

School generates a changing attendance QR.

Teacher scans it to check in.

A rotating/daily QR reduces the possibility of someone reusing an old attendance code remotely.

---

# 14. Attendance Method 4 — Fingerprint

For schools with appropriate hardware.

The fingerprint device identifies the staff member and sends an attendance event to SchoolOS.

SchoolOS does not need to become the biometric hardware itself.

Instead:

**Fingerprint Device → Attendance Integration → SchoolOS**

This keeps the platform flexible.

---

# 15. Attendance Method 5 — GPS

GPS should be treated as an **additional verification layer**, not necessarily the entire attendance method.

For example:

**QR + GPS**

or

**Selfie + GPS**

or

**Face Verification + GPS**

The school can configure whether location verification is required.

---

# 16. Staff Attendance Record

Every attendance event should ultimately resolve to a standard record:

**Staff → School → Date → Check-in → Check-out → Method → Verification → Location → Status**

Example:

| Field | Example |
|---|---|
| Staff | Teacher John |
| Date | 24 Aug 2026 |
| Check-in | 7:42 AM |
| Check-out | 4:15 PM |
| Method | Face |
| GPS | Verified |
| Liveness | Passed |
| Status | Present |

This creates a common attendance model regardless of the technology used.

---

# 17. Attendance Status Engine

SchoolOS should calculate attendance status from school configuration.

Possible statuses:

- Present
- Late
- Absent
- Early departure
- Excused
- Pending verification
- Rejected

The school can configure:

- Expected resumption time
- Late threshold
- Closing time
- Grace period

Example:

**School resumption: 7:30 AM**

**Grace period: 15 minutes**

7:40 → Present

7:55 → Late

This should be configurable rather than hardcoded.

---

# 18. Teacher Management

Teacher profile:

- Personal information
- Photograph
- Staff ID
- Qualifications
- Subjects
- Classes
- Attendance
- Responsibilities
- Documents

The discovery research already identifies qualifications, subjects, classes, attendance and responsibilities as teacher-profile information.

---

# 19. Teacher Dashboard

A teacher should see only what applies to them.

Dashboard:

- Today's classes
- Timetable
- My students
- Student attendance
- Lessons
- Scheme of work
- Results
- Remarks
- Announcements
- My attendance

This follows the discovery requirement that teachers should only see functions relevant to their role.

---

# 20. Academic Management

Core academic structure:

**Session → Term → Class → Arm → Subject → Teacher → Lesson**

Modules:

- Scheme of work
- Lesson management
- Lesson notes
- Topics taught
- E-textbooks
- Learning materials

The discovery research already supports these academic workflows.

---

# 21. Examination & Results

SchoolOS should support different school types.

Do not assume every school uses CBT.

Possible systems:

### Secondary

- CBT
- Written examinations

### Primary/Crèche

- Written assessments
- Teacher assessments

Results can include:

- Scores
- Grades
- Aggregate
- Teacher remark
- Proprietor/proprietress remark
- Attendance
- School opening days
- Student attendance
- Overall performance

These requirements come directly from the discovery hierarchy.

---

# 22. Promotion

SchoolOS should support:

### Automatic promotion

School defines criteria.

### Manual override

Authorized administrator can override the recommendation.

The system should assist the school without removing human control.

---

# 23. Fees & Finance

Finance becomes another major SchoolOS pillar.

## Fees

Schools can create:

- Tuition
- Medical
- Uniform
- Examination
- Other fees

Support:

- Full payment
- Part payment
- Manual payment
- Online payment
- Payment receipt upload
- Discounts
- Scholarships

The discovery research specifically identifies flexible payments, Paystack integration, discounts and scholarships.

---

# 24. Parent Finance Portal

Parents should see:

**Current session**

- Total fees
- Amount paid
- Balance
- Payment status

**Previous sessions**

- Previous payments
- Outstanding debt
- Transaction history
- Rolled-over balance

Notifications:

- New fees
- Outstanding fees
- Overdue payments
- Payment confirmation
- Deadlines

---

# 25. School Finance Dashboard

School administrators should see:

### Revenue

- Fees
- Other payments
- Received
- Outstanding

### Expenses

- Staff payments
- Operations
- Other expenses

### Cash flow

- Money in
- Money out
- Balance
- Debt
- Previous-session debt

### Transactions

- Payments
- Expenses
- Receipts

The discovery hierarchy explicitly expands SchoolOS beyond academics into school financial operations.

---

# 26. Communication

SchoolOS should gradually reduce dependence on fragmented communication channels.

School → Parent:

- Announcements
- Fee alerts
- Attendance alerts
- Result notifications
- Activities

School → Teacher:

- Announcements
- Instructions
- Activities

Teacher → Administration:

- Internal communication

The research notes that schools currently rely heavily on WhatsApp and identifies integrated communication as a potential opportunity.

---

# 27. Notifications

Notifications should be configurable.

Examples:

### Attendance

"Your child has been marked present."

### Fees

"Your outstanding school balance is..."

### Results

"Your child's result is now available."

### School

"School resumes on..."

Do not automatically send every possible notification.

Each school should configure what parents receive.

---

# 28. School Calendar

School administrators create:

- Resumption
- Terms
- Mid-term
- Exams
- Holidays
- Activities
- Closing dates
- Other events

Parents, students and teachers see the calendar according to their permissions.

This is already identified as a school-wide requirement.

---

# 29. Security & Infrastructure

This is where our GegoK12 reverse-engineering work becomes useful.

Before implementing SchoolOS authentication, we should understand:

- Existing authentication patterns
- Role routing
- Middleware
- User groups
- School isolation
- Login flows
- Session handling
- Permission checks
- API authentication
- Attendance authorization

But we should **learn from the existing architecture without blindly copying it**.

SchoolOS should have a cleaner authorization model.

---

# 30. Multi-Tenant Data Isolation

This is a critical requirement.

Every school-owned entity should be associated with a school/tenant.

Examples:

**School**

→ Users

→ Teachers

→ Students

→ Parents

→ Classes

→ Subjects

→ Attendance

→ Results

→ Fees

→ Transactions

A user from School A must never be able to access School B's records.

This should be enforced at the application and database/security layers.

---

# 31. Audit Logs

Important administrative actions should be logged.

Examples:

- User created
- Role changed
- Student edited
- Attendance modified
- Result changed
- Fee created
- Payment recorded
- Payment reversed
- Staff attendance manually corrected
- School configuration changed

For attendance in particular:

**Who created it?**

**Who modified it?**

**When?**

**What was changed?**

---

# 32. Backup & Recovery

SchoolOS should support:

- Automatic backup
- Manual backup
- Term backup
- Session backup
- Custom-date backup
- Downloadable backup

The discovery research specifically identifies backups as important because schools may accumulate years of records.

---

# 33. Reporting & Analytics

SchoolOS should eventually provide dashboards for:

### School

- Student population
- Attendance
- Staff attendance
- Fees
- Outstanding debt
- Academic performance

### Teacher

- Classes
- Students
- Attendance
- Lessons
- Results

### Parent

- Child attendance
- Results
- Fees

### Super Admin

- Number of schools
- Active schools
- Subscription status
- Usage
- Platform health

---

# 34. Feature Priority

We should NOT build every discovery item immediately.

The research explicitly recommends classifying features into:

- Must Have
- Important
- Nice to Have
- Not Validated Yet

and tracking which school requested each feature.

## Phase 1 — Must Have

> **Superseded for sequencing purposes (see subordination notice at top of document).** The items below reflect product vision, not build order. As actually sequenced by `14-schoolos-implementation-plan.md` / `16-schoolos-decisions-register.md`:
> - **Platform foundation, Core school management, and Attendance (student only)** — genuinely Phase 1 (`14` §1) and Phase 2–3 (`14` §2–3); consistent with this list.
> - **Finance** (Fee setup, Student fee assignment, Payments, Balances, Receipts) — **not** Phase 1. Deferred to Phases 6–8 (`14` §6) — zero schema, zero domain-map audit exists yet.
> - **Teacher/staff attendance** — schema reserved (`13` §7b) but not built in Phase 1. Only `manual`/`qr`/`selfie` methods are in scope once built; configurable GPS/face/fingerprint verification requires a privacy/retention design pass first (`16`).
> - **Academic → Results** — depends on the Exams/Timetable core-vs-addon decision, resolved in `16` as core, but scheduled in `14` Phase 4 (its own short domain-map pass required first), not Phase 1.
> - **Timetable** — same as above: core, but Phase 4, not Phase 1.

### Platform foundation

- Multi-school architecture
- Authentication
- RBAC
- School onboarding
- School configuration
- User management

### Core school management

- Students
- Parents
- Teachers/staff
- Classes
- Arms
- Subjects
- Academic session
- Terms
- Calendar

### Attendance

- Student attendance
- Teacher/staff attendance
- Configurable attendance method
- Attendance records
- Check-in/check-out
- Attendance status
- Attendance reporting

### Academic

- Timetable
- Scheme of work
- Lessons
- Results

### Finance

- Fee setup
- Student fee assignment
- Payments
- Balances
- Receipts

---

# 35. Phase 2 — Important

- Parent notifications
- Online payments
- Advanced attendance verification
- GPS
- Face/liveness verification
- QR attendance
- Staff ID cards
- Result PDF generation
- Promotion
- Academic reports
- Finance dashboard
- Communication
- Backup management
- Audit logs
- Analytics

---

# 36. Phase 3 — Nice to Have

Potential future modules:

- Library
- Transport
- Payroll
- Inventory
- Advanced messaging
- E-textbooks
- Digital lesson creation
- Advanced analytics
- Mobile applications
- Biometric integrations

These should not delay the core product.

---

# 37. Phase 4 — Not Yet Validated

Any feature that sounds useful but has not been sufficiently confirmed by schools should remain here.

This protects SchoolOS from becoming overloaded with assumptions.

The discovery document explicitly warns against turning every interview finding into an immediate product feature.

---

# 38. Recommended Development Order

## Stage 1 — Foundation

Build:

**Multi-tenancy**

↓

**Authentication**

↓

**Roles & permissions**

↓

**School onboarding**

↓

**School configuration**

↓

**User management**

---

## Stage 2 — People

Build:

**Students**

↓

**Parents**

↓

**Teachers**

↓

**Staff**

↓

**Classes**

↓

**Subjects**

---

## Stage 3 — Attendance

Build the attendance engine before building advanced verification.

### First:

- Attendance model
- Check-in/check-out
- Status engine
- School configuration
- Student attendance
- Staff attendance
- Reports

### Then:

- QR
- Selfie
- GPS
- Face verification
- Fingerprint integration

This prevents the attendance system from becoming dependent on one technology.

---

# 39. Stage 4 — Academics

Build:

- Timetable
- Scheme of work
- Lessons
- Student subject attendance
- Assessments
- Exams
- Results
- Report cards
- Promotion

---

# 40. Stage 5 — Finance

Build:

- Fee structures
- Student charges
- Payments
- Receipts
- Balances
- Discounts
- Scholarships
- Parent finance portal

---

# 41. Stage 6 — Communication

Build:

- Announcements
- Notifications
- Parent alerts
- Teacher announcements
- Internal communication

---

# 42. Stage 7 — Intelligence & Operations

Build:

- Analytics
- Reports
- Audit logs
- Backups
- Advanced dashboards
- Platform monitoring

---

# 43. The Attendance Architecture We Should Aim For

The most important architectural decision is:

**Do not create five completely different attendance systems.**

Create one attendance engine.

```text
                 SCHOOLOS ATTENDANCE ENGINE
                           │
          ┌────────────────┼────────────────┐
          │                │                │
       STUDENT           STAFF          TEACHER
      ATTENDANCE       ATTENDANCE       ATTENDANCE
          │                │                │
          │                │                │
       Manual             QR             Selfie
       Teacher           GPS              Face
       Marking          Fingerprint       GPS
                                     
                           ↓
                  Verification Layer
                           ↓
                    Attendance Event
                           ↓
              Status / Check-in / Checkout
                           ↓
                    Reports & Analytics
                           ↓
                  Parent/School Alerts
```

The underlying attendance record remains consistent regardless of how the person was verified.

---

# 44. The School Configuration Engine

A major SchoolOS differentiator should be the configuration system.

Instead of hardcoding school behavior:

```text
School
 ├── Academic settings
 ├── Attendance settings
 ├── Fee settings
 ├── Notification settings
 ├── Result settings
 ├── Calendar settings
 └── Role/permission settings
```

For attendance:

```text
Attendance Settings
 ├── Staff method
 ├── Student method
 ├── Morning attendance
 ├── Afternoon attendance
 ├── Late threshold
 ├── GPS required?
 ├── Face verification required?
 ├── Liveness required?
 ├── Parent notification?
 └── Manual correction allowed?
```

This is the direction that makes SchoolOS a genuine operating system rather than another rigid school ERP.

---

# 45. What We Should Learn From GegoK12

The GegoK12 reverse-engineering work should now become a **reference/audit exercise**, not the foundation of SchoolOS.

We should extract:

- What works
- What is unnecessarily complicated
- How authentication is implemented
- How roles are represented
- How routes are separated
- How school users are identified
- How dashboards are selected
- How permissions are enforced
- How existing school workflows are modeled

Then SchoolOS should be designed with a cleaner architecture.

The objective is:

**Understand → Extract lessons → Design better → Implement independently**

Not:

**Understand → Copy everything**

---

# 46. First SchoolOS MVP

> **Superseded for sequencing purposes (see subordination notice at top of document).** Same conflicts as §34: Finance and configurable/biometric staff attendance are not Phase 1 under `14`/`16`; Timetable and Results are real Phase 1 scope items in the product sense but scheduled under `14` Phase 4, pending its own domain-map pass. Parent portal below is affirmed by `16` — build it, scoped to attendance + announcements + profile only for its first version.

If we needed to get a real usable version into a school quickly, the MVP should contain:

### Platform

- Super Admin
- School creation
- School subscription/status
- Multi-tenant isolation

### School

- School profile
- Academic session
- Terms
- Classes
- Arms
- Subjects
- Staff
- Students
- Parents

### Authentication

- Login
- Password reset
- Role-based dashboards
- Permissions

### Attendance

- Student attendance
- Staff attendance
- Configurable staff attendance method
- Check-in
- Check-out
- Attendance history
- Reports

### Academics

- Teacher assignments
- Timetable
- Subject attendance
- Lesson/topic tracking
- Results

### Finance

- Fee structure
- Student fees
- Payments
- Balance
- Receipts

### Parent portal

- Student information
- Attendance
- Results
- Fees
- Announcements

That is enough to create a meaningful first SchoolOS product without attempting to build the entire discovery document at once.

---

# 47. Final Product Structure

The eventual SchoolOS navigation should roughly become:

```text
SCHOOL
│
├── Dashboard
│
├── Administration
│   ├── School Profile
│   ├── Academic Structure
│   ├── Calendar
│   ├── Users
│   └── Roles & Permissions
│
├── People
│   ├── Students
│   ├── Parents
│   ├── Teachers
│   └── Staff
│
├── Attendance
│   ├── Student Attendance
│   ├── Staff Attendance
│   ├── Attendance Configuration
│   └── Reports
│
├── Academics
│   ├── Classes
│   ├── Subjects
│   ├── Timetable
│   ├── Scheme of Work
│   ├── Lessons
│   ├── Exams
│   └── Results
│
├── Finance
│   ├── Fees
│   ├── Payments
│   ├── Discounts
│   ├── Scholarships
│   ├── Expenses
│   └── Reports
│
├── Communication
│   ├── Announcements
│   ├── Notifications
│   └── Messaging
│
├── Reports
│
└── Settings
    ├── School Settings
    ├── Attendance Settings
    ├── Notification Settings
    ├── Security
    ├── Backup
    └── Audit Logs
```

# 48. The Strategic Goal

SchoolOS should ultimately become:

> **One operating system for the day-to-day running of a school.**

The five major pillars from the discovery research give us the foundation:

**Administration + Academics + Finance + Communication + Infrastructure.**

The next step should therefore **not** be to immediately start coding every module.

The next step is to turn this plan into a **technical blueprint**:

1. Finalize the SchoolOS modules.
2. Define the database/entities.
3. Define the tenant/school architecture.
4. Define users, roles and permissions.
5. Define authentication.
6. Define the attendance engine.
7. Define the API boundaries.
8. Define the admin/teacher/parent/student workflows.
9. Define the MVP.
10. Then begin implementation.

That gives us a much stronger foundation than simply modifying GegoK12 feature-by-feature.