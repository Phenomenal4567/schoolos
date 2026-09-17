# SchoolOS

SchoolOS is a modern, web-based school management platform designed to help primary and secondary institutions manage students, staff, academics, attendance, fees, examinations, communications, and day-to-day administrative operations from a single unified system.

Rebuilt from the ground up on Next.js, TypeScript, Supabase, and PostgreSQL, SchoolOS replaces disconnected paper registers, spreadsheets, and manual ledgers with a cloud-native architecture built for multi-tenant scalability, serverless reliability, and institutional data protection.

---

## Table of Contents

- [Features](#features)
  - [School Administration & Onboarding](#school-administration--onboarding)
  - [Students & Guardian Management](#students--guardian-management)
  - [Academics & Timetable Engine](#academics--timetable-engine)
  - [Attendance Tracking](#attendance-tracking)
  - [Examinations & Report Cards](#examinations--report-cards)
  - [Promotions Engine](#promotions-engine)
  - [Public Admissions](#public-admissions)
  - [Finance, Invoicing & Payments](#finance-invoicing--payments)
  - [Communication & Learning Materials](#communication--learning-materials)
  - [Files & Data Exports](#files--data-exports)
  - [Platform Administration & Multi-Tenancy](#platform-administration--multi-tenancy)
- [Roadmap](#roadmap)
- [Architecture](#architecture)
- [Data & Storage Architecture](#data--storage-architecture)
- [Authentication & Authorization](#authentication--authorization)
- [School Onboarding Workflow](#school-onboarding-workflow)
- [Student & Parent Identification](#student--parent-identification)
- [Paystack Payment Integration](#paystack-payment-integration)
- [Environment Variables](#environment-variables)
- [Local Development](#local-development)
  - [Prerequisites](#prerequisites)
  - [Installation](#installation)
  - [Database Setup](#database-setup)
  - [Supabase Storage Setup](#supabase-storage-setup)
  - [Running the Application](#running-the-application)
  - [Running Tests](#running-tests)
- [Production Deployment](#production-deployment)
- [Security & Isolation](#security--isolation)
- [Troubleshooting](#troubleshooting)
- [Project Structure](#project-structure)
- [Contributing](#contributing)
- [License](#license)
- [Disclaimer](#disclaimer)

---

## Features

SchoolOS implements a comprehensive suite of institutional workflows. Every feature listed below is verified and active in the codebase:

### School Administration & Onboarding
* **Multi-Tenant Setup**: Isolated workspace per institution (`School` entity) with configurable branding, academic terms, and operational settings.
* **7-Step Onboarding Wizard**: Guided institutional setup covering school identity, academic calendars, class standards, curriculum subjects, staff directory, student enrollments, and activation cards.
* **Academic Calendar Management**: Academic years, multi-term configurations (Term 1, Term 2, Term 3), start/end dates, and configurable school working days.
* **Class & Section Organization**: Structured hierarchy of Standards (Grade levels) linked to Sections (arms such as A, B, Gold, Diamond) forming Class Sections.
* **Staff Management & Roles**: Role assignments, subject teacher delegations, and form/class teacher assignments.

### Students & Guardian Management
* **Student Record Lifecycle**: Comprehensive student profiles including registration numbers, gender, date of birth, medical notes, enrollment histories, and active/inactive statuses.
* **Parent/Guardian Identity System**: Guardian profiles tied directly to students with explicit relationship designations (Father, Mother, Legal Guardian) and multi-child support.
* **Parent Link Verification**: Dedicated approval workflow (`/api/admin/parent-links`) where school administrators verify guardian claims before granting portal access.
* **Bulk Roster Import**: Robust CSV ingestion engine with pre-validation, duplicate detection, automated invitation token generation, and downloadable activation cards.

### Academics & Timetable Engine
* **Curriculum & Subjects**: Institution-level subject registries mapped to education levels and individual classes.
* **Collision-Free Timetable Builder**: Intelligent scheduling engine that detects teacher double-booking, room conflicts, and period overlaps during timetable creation.
* **Timetable Publishing**: Administrative draft, review, and publication controls enabling instant timetable availability across teacher, student, and parent portals.

### Attendance Tracking
* **Daily Student Roll Call**: Fast morning attendance marking supporting Present, Absent, Late, Excused, and Half-Day statuses.
* **Subject & Topic Attendance**: Granular subject-period attendance logging that allows teachers to record both attendance and the specific curriculum topic taught.
* **Attendance Audit Trail & Corrections**: Formal attendance correction workflow (`/api/admin/attendance/corrections`) requiring administrative review and auditable reasons for retrospective modifications.
* **Staff Attendance with Dynamic QR**: Contactless staff check-in/check-out supporting manual time entry and rotating, time-limited QR codes.

### Examinations & Report Cards
* **Flexible Exam Components**: Multi-component grading configurations (Continuous Assessment 1, CA 2, Mid-term, Practical, Final Exam) with customizable weight percentages and maximum marks.
* **Teacher Mark Entry & Grading**: Secure score submission interfaces for assigned subject teachers with validation against maximum component thresholds.
* **Administrative Review & Publishing**: Multi-stage examination workflow requiring admin sign-off before grades become visible to students or parents.
* **Serverless PDF Report Cards**: Pure in-memory PDF generation (zero headless browsers or native OS binaries required) providing clean, printable term report cards with component breakdowns, weighted scores, class rankings, attendance stats, and head-teacher remarks.

### Promotions Engine
* **Criteria-Based Promotion Rules**: Flexible progression standards per academic year, including minimum average marks (GPA), minimum attendance percentages, passed subject thresholds, and mandatory core subjects.
* **Automated Promotion Runner**: End-of-year batch execution engine that evaluates student performance against class rules and advances qualifying students to the next grade.
* **Manual Override & Audit**: Administrative override capability (Promoted, Retained, Promoted on Trial) requiring a mandatory justification log for compliance.

### Public Admissions
* **Online Public Application Portal**: Dedicated public admission interface (`/apply?school=<short_code>`) allowing prospective parents to apply directly to a school.
* **Application Tracking**: Unique application numbers for real-time status inquiry (Submitted, Under Review, Accepted, Rejected, Withdrawn).
* **One-Click Enrollment Conversion**: Immediate conversion of accepted admission applicants into enrolled student records without manual re-entry.

### Finance, Invoicing & Payments
* **Fee Assessments & Invoicing**: Automated and manual invoicing by fee category (Tuition, Development Levy, Transport, Uniforms, Books) across classes or individual students.
* **Online Payments via Paystack**: Native checkout integration supporting debit/credit cards and instant bank transfers with automatic invoice balance reconciliation.
* **Manual Payment Recording**: Offline bursary collection recording (Cash, POS, Direct Bank Teller) with administrative verification and approval workflows.
* **Branded PDF Payment Receipts**: Instant, in-memory PDF receipt generation (`pdf-lib`) available for download by parents and bursars.
* **Automated Fee Reminder Engine**: Scheduled background reminders that compute overdue and upcoming balances, sending proactive notifications to parents.

### Communication & Learning Materials
* **Targeted Announcements**: School-wide or audience-scoped broadcasts (Teachers, Parents, Students, or specific classes).
* **Content Read Receipts**: Verification engine recording exact view timestamps when recipients read announcements or access course materials.
* **Interactive Academic Calendar**: Event scheduling for term dates, parent-teacher conferences, examinations, and public holidays.
* **Direct Feedback System**: Structured communication channel between parents/students and school administration with threaded responses and status tracking.
* **Digital Learning Materials**: Digital library repository for syllabi, lesson notes, and study resources with role-scoped access control.

### Files & Data Exports
* **Cloud Storage Architecture**: Full migration to Supabase Storage with strict isolation across public branding assets and private institutional documents.
* **Data Exporters**: Multi-entity data exports generating RFC 4180-compliant CSV files for student rosters, attendance logs, and examination records.
* **In-Memory ZIP Archiving**: Streaming compression creating backup bundles in memory without local disk persistence.
* **Signed Access URLs**: Time-bounded (15-minute) signed download URLs for all private archives and sensitive documents.
* **Automated Pruning**: Scheduled cleanup job that automatically prunes expired export archives after their 7-day retention period.

### Platform Administration & Multi-Tenancy
* **Role-Based Portals**: Distinct, tailor-made portals for Super Administrators, School Administrators, Teachers, Students, and Parents.
* **Super Admin Control Center**: Global oversight dashboard for managing institutional tenants, monitoring subscriptions, and enforcing platform policies.

---

## Roadmap

The following capabilities are identified as future enhancements:

- [ ] **PWA (Progressive Web App)**: Service worker registration, web app manifest, and offline record caching for intermittent internet connectivity.
- [ ] **CBT (Computer-Based Testing)**: Online timed quizzes, question banks, objective question auto-grading, and anti-cheating mechanisms.
- [ ] **Automated SMS & WhatsApp Messaging**: Direct SMS and WhatsApp API integrations for urgent alerts, emergency closures, and instant fee reminders.
- [ ] **Custom Report Card Designer**: Drag-and-drop template builder for bespoke institution report card formatting.
- [ ] **Multi-Currency Billing**: Support for international school tuition collections in USD, GBP, and EUR via Stripe.

---

## Architecture

SchoolOS is built as a cloud-native, serverless web application designed to run on modern edge/serverless infrastructure without relying on stateful local disks, background PHP workers, or local daemon processes.

```
┌────────────────────────────────────────────────────────────────────────┐
│                              CLIENT TIER                               │
│        React 19 Server & Client Components · Tailwind CSS v4           │
│        Responsive Portals: Super Admin, Admin, Teacher, Student, Parent │
└────────────────────────────────────┬───────────────────────────────────┘
                                     │ HTTPS / HTTP-only Cookies
┌────────────────────────────────────▼───────────────────────────────────┐
│                           APPLICATION TIER                             │
│                     Next.js 15 (App Router on Vercel)                  │
│  ┌─────────────────────────┐  ┌─────────────────────────────────────┐  │
│  │ Authenticated Sessions  │  │ Multi-Tenant Context Engine         │  │
│  │ (JWE AES-256-GCM Token) │  │ (Strict school_id Scope Validation) │  │
│  └─────────────────────────┘  └─────────────────────────────────────┘  │
│  ┌─────────────────────────┐  ┌─────────────────────────────────────┐  │
│  │ Vercel Cron Endpoints   │  │ Paystack Webhook Handler            │  │
│  │ (Constant-Time HMAC)    │  │ (HMAC-SHA512 Signature Check)       │  │
│  └─────────────────────────┘  └─────────────────────────────────────┘  │
└──────────────────────┬───────────────────────────────┬─────────────────┘
                       │                               │
┌──────────────────────▼───────────┐   ┌───────────────▼─────────────────┐
│         PERSISTENCE TIER         │   │          STORAGE TIER           │
│       Supabase PostgreSQL        │   │        Supabase Storage         │
│  (Prisma ORM with PgBouncer Pool)│   │  (4 Public/Private S3 Buckets)  │
└──────────────────────────────────┘   └─────────────────────────────────┘
```

### Core Technologies

| Technology | Purpose in SchoolOS |
| :--- | :--- |
| **Next.js 15** | Full-stack application framework providing App Router, React Server Components, server actions, and serverless API route handlers. |
| **React 19** | Modern UI library utilizing React transitions (`useTransition`), optimistic UI updates, and server rendering. |
| **TypeScript 5** | Strict end-to-end type safety across the database layer, service business logic, and UI components. |
| **Prisma 6** | Database ORM providing type-safe SQL query generation, migration orchestration, and connection pooling integration. |
| **Supabase PostgreSQL** | Hosted multi-tenant relational database with PgBouncer transaction connection pooling for serverless execution. |
| **Supabase Storage** | S3-compatible cloud object storage managing institutional assets, private student photos, and export archives. |
| **Jose (JWT/JWE)** | High-performance cryptographic library handling AES-256-GCM session encryption and signature verification. |
| **Paystack API** | Financial gateway managing online fee payments, instant webhooks, and bank transfer verifications. |
| **Vercel** | Serverless hosting platform running Next.js route handlers, edge caching, and Vercel Cron scheduled jobs. |

---

## Data & Storage Architecture

SchoolOS enforces strict institutional isolation across both relational database tables and cloud object storage.

### 1. Database Multi-Tenancy
* Every tenant-scoped entity (students, staff, classes, fees, results, attendance, documents) carries a `school_id` foreign key referencing the `schools` root table.
* Database operations derive the tenant identifier directly from the authenticated session context using `getTenantContext()`. User-supplied IDs in query strings or request payloads are never trusted to define tenant boundaries.

### 2. Cloud Storage Buckets
File persistence is delegated entirely to online Supabase Storage. Local serverless disks are treated as ephemeral scratchpads and are never used for persistent storage.

| Bucket Name | Access Level | Contents & Purpose | Security / Retention |
| :--- | :--- | :--- | :--- |
| `public-school-assets` | **Public** | School logos, banners, and institutional branding materials. | Direct CDN URL access. |
| `private-student-media` | **Private** | Student identification photos and staff profile portraits. | 15-minute temporary signed URLs only. Public access rejected. |
| `private-school-documents` | **Private** | Staff qualifications, signed receipts, learning material attachments, lesson notes. | 15-minute signed URLs. Strict tenant path validation. |
| `private-export-archives` | **Private** | Multi-CSV ZIP backup archives generated by the export engine. | 15-minute signed URLs. 7-day automatic retention pruning. |

### 3. Tenant Path Partitioning
All storage objects are segregated by tenant prefix:
```text
{bucket-name}/schools/{school_id}/{category}/{unique_filename}
```
Before uploading or generating signed download links, `StorageService.assertTenantPath()` validates that the targeted object path matches the active school context, blocking cross-school file access attempts.

---

## Authentication & Authorization

SchoolOS uses an integrated session authentication model designed specifically for multi-tenant educational institutions.

### Session Architecture
* **Tamper-Proof JWE**: Sessions are encoded as JSON Web Encrypted (JWE) tokens using AES-256-GCM encryption with SHA-256 HMAC integrity.
* **HTTP-Only Cookies**: Tokens are stored in a secure cookie named `schoolos_session` (`httpOnly: true`, `sameSite: "lax"`, and `secure: true` in production).
* **Multi-Identifier Login**: Users can sign in using their **email address**, **phone number** (e.g., `08012345678`), or **student/staff registration number** (e.g., `STU-2026-001`).
* **Brute-Force Rate Limiting**: The authentication endpoint (`/api/auth/login`) tracks failed attempts per IP and identifier, enforcing exponential backoff and returning HTTP `429 Too Many Requests` with a standard `Retry-After` header.

### Role-Based Access Control (RBAC)
User roles are validated server-side using strongly-typed security guards (`guards.ts`):

| Role Key | Portal Route | Primary Access Scope |
| :--- | :--- | :--- |
| `super_admin` | `/super-admin` | Multi-school platform management, school approvals, platform settings. |
| `school_admin` | `/admin` | Complete school management: academics, admissions, staff, students, fees, results, exports. |
| `teacher` | `/teacher` | Assigned classes, daily attendance, subject topic attendance, mark entry, materials. |
| `student` | `/student` | Personal profile, attendance record, published exam results, timetable, materials. |
| `parent` | `/parent` | Linked children records, attendance, report card downloads, fee invoices, online payment. |
| `accountant` | `/admin/finance` | Fee assessments, manual payment logging, approvals, expense tracking. |
| `staff` | `/staff/attendance`| Personal profile, rotating QR code check-in/out, attendance history. |

---

## School Onboarding Workflow

When a new school registers on SchoolOS, the onboarding engine ensures complete institutional data readiness:

```
[1. School Registration (/school/create)]
   └─ Enter school name, official contact info, school type, education levels, and admin password.
   └─ Auto-provisions School record and primary School Admin account.
         │
         ▼
[2. Onboarding Wizard (/onboarding)]
   ├─ Step 1: School Profile (Address, logo, operational contacts)
   ├─ Step 2: Academic Session (Initial academic year and term schedule)
   ├─ Step 3: Classes & Sections (Starter classes created from education levels)
   ├─ Step 4: Curriculum Subjects (Assign subjects to created grade levels)
   ├─ Step 5: Staff Directory (Add teachers & staff with roles)
   ├─ Step 6: Student Roster (Bulk CSV import or manual student creation)
   └─ Step 7: Final Review & Activation (Generate activation credentials)
         │
         ▼
[3. Full Portal Launch (/admin)]
   └─ School transitions to "complete" setup status and unlocks all administrative modules.
```

---

## Student & Parent Identification

Accurate guardian identification is critical in school management. SchoolOS **never relies on surname matching** or probabilistic heuristics to link families.

### Explicit Identification Mechanism
1. **Unique Digital Identifiers**: Parents are identified exclusively through their verified **email address** or verified **mobile phone number**.
2. **Student-Parent Association**: Associations are recorded in explicit `StudentParentLink` database entries containing:
   * `school_id`: Enforcing institutional isolation.
   * `student_id`: Target child ID.
   * `parent_id`: Guardian account ID.
   * `relationship_type`: Specified relationship (`father`, `mother`, `guardian`, `sponsor`).
   * `status`: Current state (`pending` or `active`).
3. **Claim & Verification Flow**:
   * During CSV roster import or manual registration, parent contact details create an invited guardian profile and a `pending` link.
   * School administrators review and confirm pending links in the administrative portal (`/api/admin/parent-links`).
   * Once approved, the parent gains immediate access to that specific child's academic records, fee invoices, and attendance logs.
4. **Multi-Child Families**: A single parent login seamlessly links to multiple children across different classes, allowing guardians to toggle between students within one interface.

---

## Paystack Payment Integration

SchoolOS integrates Paystack for automated, transparent school fee collections.

### Payment Flow
1. **Invoice Initialization**: A parent views their outstanding fee assessment in `/parent/fees` and clicks "Pay with Paystack".
2. **Checkout Session**: The server generates a unique, tamper-proof transaction reference (`REF-{timestamp}-{random}`) and initializes a transaction with Paystack specifying the exact amount in kobo.
3. **Webhook Reconciliation**:
   * When payment succeeds, Paystack dispatches a `charge.success` event to `/api/webhooks/paystack`.
   * **HMAC-SHA512 Verification**: The webhook handler computes the HMAC signature of the raw payload using `crypto.timingSafeEqual` and compares it against `x-paystack-signature`. Requests with invalid signatures are immediately rejected with HTTP `400`.
   * **Amount & Currency Validation**: The handler verifies that the paid amount in kobo matches the expected invoice balance exactly, preventing partial-payment exploits.
   * **Idempotent Settlement**: The payment is marked as `completed`, the invoice status transitions to `paid` or `partially_paid`, and a branded PDF receipt is generated.

---

## Environment Variables

Copy `.env.example` to `.env.local` for local development.

### Required Variables

| Variable | Browser Safe? | Description |
| :--- | :--- | :--- |
| `DATABASE_URL` | **No (Server only)** | PostgreSQL connection string. Use Supabase transaction pooler (`:6543/postgres?pgbouncer=true`). |
| `SESSION_SECRET` | **No (Server only)** | 32+ character random string used for AES-256-GCM JWE session encryption. |
| `CRON_SECRET` | **No (Server only)** | 16+ character random secret securing background Vercel Cron endpoints. |

### Optional / Integration Variables

| Variable | Browser Safe? | Description |
| :--- | :--- | :--- |
| `DIRECT_URL` | **No (Server only)** | Direct PostgreSQL connection string (`:5432/postgres`) used by Prisma for migrations. |
| `NEXT_PUBLIC_APP_URL` | **Yes (Client & Server)** | Base canonical URL of the application (e.g., `http://localhost:3000` or `https://schoolos.app`). |
| `PAYSTACK_SECRET_KEY` | **No (Server only)** | Paystack secret key (`sk_test_...` or `sk_live_...`). Required for online payments. |
| `NEXT_PUBLIC_PAYSTACK_PUBLIC_KEY` | **Yes (Client & Server)** | Paystack public key (`pk_test_...` or `pk_live_...`). Used by client checkout elements. |
| `NEXT_PUBLIC_SUPABASE_URL` | **Yes (Client & Server)** | Hosted Supabase project URL (`https://xxxx.supabase.co`). |
| `NEXT_PUBLIC_SUPABASE_ANON_KEY` | **Yes (Client & Server)** | Supabase anonymous API key for client-safe operations. |
| `SUPABASE_SERVICE_ROLE_KEY` | **No (Server only)** | Supabase service-role key for backend storage uploads and signed URL generation. |
| `RESEND_API_KEY` | **No (Server only)** | Resend API key for transactional email notifications. |
| `MAIL_FROM_ADDRESS` | **No (Server only)** | Default email sender name and address (e.g., `SchoolOS <notifications@schoolos.app>`). |

> **Security Rule**: Variables without the `NEXT_PUBLIC_` prefix are strictly accessible to server-side code and are never bundled into client JavaScript.

---

## Local Development

### Prerequisites
* **Node.js**: `v20.x` or higher (LTS recommended)
* **npm**: `v10.x` or higher
* **PostgreSQL**: Hosted Supabase project or local PostgreSQL instance
* **Git**

### Installation

1. Clone the repository:
   ```bash
   git clone <repository-url>
   cd schoolos/schoolos-app
   ```

2. Install dependencies:
   ```bash
   npm install
   ```

3. Configure your local environment:
   ```bash
   cp .env.example .env.local
   ```
   Open `.env.local` and configure your `DATABASE_URL`, `SESSION_SECRET`, and `CRON_SECRET`.

### Database Setup

1. Push the Prisma schema to your development database:
   ```bash
   npm run prisma:push
   ```

2. Seed canonical system roles and permissions:
   ```bash
   npm run db:seed
   ```

#### Institutional & User Account Provisioning
SchoolOS operates on real institutional tenant boundaries. Accounts are provisioned via:
* **Platform Super Admin**: Platform-level administration at `/super-admin` (`superadmin@schoolos.app`).
* **School Admin**: Register your institution via the self-service onboarding workflow at [`/onboarding`](http://localhost:3000/onboarding).
* **Teachers & Staff**: Added and assigned roles by the School Admin via the Admin Portal at `/admin/staff`.
* **Students**: Enrolled via Student Admissions at `/admin/students` or public application at `/admissions`.
* **Parents**: Invited and linked via Parent Management at `/admin/parents` or during student enrollment.

For full login procedures, supported identifiers, and credentials, see [ROLE_LOGIN_GUIDE.md](./ROLE_LOGIN_GUIDE.md).

### Supabase Storage Setup
In your Supabase project dashboard, navigate to **Storage** and create the following 4 buckets:
1. `public-school-assets` (Toggle **Public Bucket** to ON)
2. `private-student-media` (Keep **Public Bucket** OFF)
3. `private-school-documents` (Keep **Public Bucket** OFF)
4. `private-export-archives` (Keep **Public Bucket** OFF)

### Running the Application
Start the Next.js development server:
```bash
npm run dev
```
Open [http://localhost:3000](http://localhost:3000) in your browser.

### Running Tests
Execute the full Vitest automated test suite:
```bash
npm test
```
To run static type checking:
```bash
npx tsc --noEmit
```
To run lint checks:
```bash
npm run lint
```

---

## Production Deployment

SchoolOS is optimized for zero-maintenance deployment on **Vercel** connected to **Supabase**.

### Step-by-Step Vercel Deployment

1. **Push to GitHub**: Ensure all commits are pushed to your remote repository.
2. **Import into Vercel**:
   * Navigate to [Vercel Dashboard](https://vercel.com) > **Add New Project**.
   * Select your GitHub repository.
   * **Important**: Set **Root Directory** to `schoolos-app`.
   * Framework Preset: **Next.js**.
3. **Configure Environment Variables**:
   In Vercel Project Settings > Environment Variables, add:
   * `DATABASE_URL`: Supabase Transaction Pooler connection string (`:6543/postgres?pgbouncer=true`).
   * `DIRECT_URL`: Supabase Direct connection string (`:5432/postgres`).
   * `SESSION_SECRET`: A secure 32+ character random string.
   * `CRON_SECRET`: A secure 16+ character random string.
   * `NEXT_PUBLIC_APP_URL`: Your custom production domain (e.g., `https://app.schoolos.com`).
   * `PAYSTACK_SECRET_KEY` & `NEXT_PUBLIC_PAYSTACK_PUBLIC_KEY`: Live Paystack keys.
   * `NEXT_PUBLIC_SUPABASE_URL` & `SUPABASE_SERVICE_ROLE_KEY`: Supabase project credentials.
   * `RESEND_API_KEY`: Transactional email provider key.
4. **Deploy**: Click **Deploy**. Vercel will build the Next.js application and deploy serverless endpoints.
5. **Scheduled Jobs (Vercel Cron)**:
   The repository includes a pre-configured `vercel.json` with three automated schedules:
   * `0 2 * * *` — `/api/cron/exports`: Runs daily scheduled data backups.
   * `0 3 * * *` — `/api/cron/prune-exports`: Cleans up export archives older than 7 days.
   * `0 8 * * *` — `/api/cron/fee-reminders`: Evaluates due and overdue fees and dispatches guardian alerts.

---

## Security & Isolation

* **Tenant Guardrails**: Direct database queries enforce `school_id` ownership. The application code contains **zero raw unparameterized SQL queries**, preventing SQL injection.
* **Timing-Safe Operations**: Cryptographic signature validation (Paystack webhooks and Vercel Cron tokens) uses `crypto.timingSafeEqual` to prevent timing side-channel attacks.
* **Session Cryptography**: Session tokens use AES-256-GCM JWE encryption with built-in expiration and clock tolerance guards.
* **HTTP Hardening**: Production responses include strict security headers:
  * `Strict-Transport-Security: max-age=63072000; includeSubDomains; preload`
  * `X-Frame-Options: DENY` (prevents clickjacking)
  * `X-Content-Type-Options: nosniff` (blocks MIME confusion)
  * `Referrer-Policy: strict-origin-when-cross-origin`
  * `Permissions-Policy: camera=(), microphone=(), geolocation=()`

---

## Troubleshooting

### Authentication does not work / Redirect loops
* Ensure `SESSION_SECRET` in `.env.local` is at least 32 characters long.
* Clear stale cookies in your browser (`schoolos_session`).
* Verify that your database is seeded (`npm run db:seed`) and the user account status is `active`.

### Database connection timeouts in serverless routes
* Verify that `DATABASE_URL` points to the **Supabase PgBouncer pooler** (`port 6543`) with `?pgbouncer=true`.
* Direct connections (`port 5432`) quickly exhaust PostgreSQL connection limits under concurrent serverless invocations.

### Storage upload returns 403 Forbidden or fails
* Verify that `SUPABASE_SERVICE_ROLE_KEY` is configured in your server environment variables.
* Check that all 4 required buckets have been created in Supabase Storage with exact names.
* Ensure the target upload path follows the tenant format: `schools/{school_id}/...`.

### Paystack webhook is not received or rejected
* In Paystack Dashboard > Settings > API & Webhooks, set your Webhook URL to: `https://<your-domain>/api/webhooks/paystack`.
* Ensure `PAYSTACK_SECRET_KEY` on your server matches the secret key of the Paystack environment (test vs live) sending the webhook.

### Cron jobs fail with 401 Unauthorized
* Verify that `CRON_SECRET` matches between your Vercel environment variables and your scheduled job caller.
* Vercel Cron automatically passes `Authorization: Bearer <CRON_SECRET>`, which is validated by `cron-guard.ts`.

---

## Project Structure

```
schoolos-app/
├── prisma/
│   ├── schema.prisma             # Multi-tenant database schema (PostgreSQL)
│   └── seed.ts                   # Institutional starter & demo user seed script
├── src/
│   ├── app/
│   │   ├── (auth)/login/         # Multi-identifier login interface
│   │   ├── (portals)/
│   │   │   ├── admin/            # School Admin dashboard & operational modules
│   │   │   ├── parent/           # Parent portal: children, fees, reports, feedback
│   │   ├── apply/                # Public prospective student admissions portal
│   │   ├── onboarding/           # 7-step institutional onboarding wizard
│   │   ├── school/create/        # Initial school registration page
│   │   ├── student/              # Student portal: results, attendance, timetable
│   │   ├── super-admin/          # Platform super-administrator oversight portal
│   │   ├── teacher/              # Teacher portal: daily roll call, marks, materials
│   │   ├── api/
│   │   │   ├── admin/            # Institutional administrative API routes
│   │   │   ├── auth/             # Login, logout, session resolution
│   │   │   ├── cron/             # Vercel Cron scheduled endpoints
│   │   │   ├── webhooks/paystack # Secure Paystack webhook listener
│   │   └── page.tsx              # Public landing page
│   └── lib/
│       ├── academic/             # Curriculum, terms, classes, and subjects
│       ├── admissions/           # Public application review & conversion
│       ├── attendance/           # Student & staff attendance tracking services
│       ├── auth/                 # JWE encryption, session cookies, RBAC guards
│       ├── communication/        # Announcements, calendar, feedback, materials
│       ├── cron/                 # Timing-safe cron authorization guard
│       ├── exams/                # Components, mark calculation, PDF report cards
│       ├── exports/              # CSV generation, ZIP compression, export lifecycle
│       ├── finance/              # Assessments, Paystack client, receipt PDFs
│       ├── onboarding/           # Wizard state machine & bulk CSV roster importer
│       ├── promotions/           # Academic promotion rules & automated runner
│       ├── storage/              # Supabase Storage client & bucket policies
│       ├── timetable/            # Collision detection & schedule publishing
│       └── env.ts                # Zod runtime environment variable validation
├── vercel.json                   # Vercel Cron configurations & schedule definitions
└── package.json                  # Next.js 15, React 19, Prisma, Tailwind CSS
```

---

## Contributing

1. Fork the repository on GitHub.
2. Create a feature branch:
   ```bash
   git checkout -b feature/institutional-enhancement
   ```
3. Implement your changes following established TypeScript and Prisma patterns.
4. Run automated checks:
   ```bash
   npm test
   npx tsc --noEmit
   npm run lint
   ```
5. Commit your changes with clear, descriptive commit messages:
   ```bash
   git commit -m "feat(academics): add grading scale customization"
   ```
6. Push to your fork and submit a Pull Request.

---

## License

License information has not yet been specified.

---

## Disclaimer

SchoolOS is management software provided to assist educational institutions with daily administration. Schools and institutional operators remain solely responsible for configuring operational policies, reviewing academic records, ensuring data protection compliance, and adhering to applicable national or local education regulations.
