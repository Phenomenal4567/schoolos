# SchoolOS User Guide

Welcome to **SchoolOS** — a unified, web-based management platform designed to help primary and secondary schools streamline daily operations, academic tracking, student records, fee collection, and family communication.

This guide is written for school administrators, principals, bursars, teachers, and administrative staff. It explains how to set up, operate, and make the most of SchoolOS without needing technical background.

---

## Table of Contents

1. [Getting Started](#1-getting-started)
2. [Setting Up Your School](#2-setting-up-your-school)
3. [Managing Staff](#3-managing-staff)
4. [Managing Students](#4-managing-students)
5. [Managing Parents & Guardians](#5-managing-parents--guardians)
6. [Classes & Subjects](#6-classes--subjects)
7. [Attendance Tracking](#7-attendance-tracking)
8. [Results & Academics](#8-results--academics)
9. [Fees & Payments](#9-fees--payments)
10. [Reports & Exports](#10-reports--exports)
11. [Files & Documents](#11-files--documents)
12. [Notifications & Communication](#12-notifications--communication)
13. [Understanding Your Dashboard](#13-understanding-your-dashboard)
14. [Account & Security Settings](#14-account--security-settings)
15. [Using SchoolOS on Mobile Devices](#15-using-schoolos-on-mobile-devices)
16. [Recommended Daily Workflows](#16-recommended-daily-workflows)

---

## 1. Getting Started

### Accessing SchoolOS
You can access SchoolOS from any modern web browser on a computer, tablet, or smartphone (Google Chrome, Apple Safari, Microsoft Edge, or Mozilla Firefox).

1. Open your browser and navigate to your school's SchoolOS web address (e.g., `https://app.schoolos.com` or your designated institution URL).
2. Click **Sign In** on the top navigation bar.

### Signing In
SchoolOS allows you to sign in with whichever credential is most convenient for you:
* **Email Address**: (e.g., `principal@apexacademy.edu`)
* **Mobile Phone Number**: (e.g., `08012345678` or `+2348012345678`)
* **Student or Staff Registration Number**: (e.g., `TCH-001`, `STU-2026-042`)

Enter your identifier, your password, and click **Sign In to Portal**.

> **Note**: For your security, multiple incorrect password attempts will temporarily lock login attempts for a few minutes. If you forget your password, contact your school administrator to reset it.

### Your Dedicated Portal
Once signed in, SchoolOS automatically directs you to the portal designed for your specific role:
* **School Administrators & Principals** $\rightarrow$ `/admin` (Complete administrative control)
* **Teachers** $\rightarrow$ `/teacher` (Class attendance, grade entry, timetable, and lesson materials)
* **Students** $\rightarrow$ `/student` (Class schedules, personal attendance, published report cards, study notes)
* **Parents & Guardians** $\rightarrow$ `/parent` (Children's records, academic results, fee balances, online payment)
* **Accountants & Bursars** $\rightarrow$ `/admin/finance` (Fee assessments, payment approvals, expenditure records)
* **General Staff** $\rightarrow$ `/staff/attendance` (Personal clock-in/out and attendance logs)

---

## 2. Setting Up Your School

When your institution first joins SchoolOS, the system walks you through a structured **7-Step Onboarding Wizard** to ensure your school is fully configured before classes begin.

### Step 1: School Profile
* Enter your school's full legal name, official email, phone number, physical address, and school type (Mixed/Co-ed, Boys Only, Girls Only).
* Upload your school logo (PNG or JPEG). This logo will appear automatically on official student report cards and payment receipts.

### Step 2: Academic Session & Terms
* Configure the current **Academic Year** (e.g., `2025/2026`).
* Set up term dates for **First Term**, **Second Term**, and **Third Term** (start and end dates).
* Select your school's operational working days (e.g., Monday through Friday).

### Step 3: Classes & Sections
* SchoolOS organizes grades into **Standards** (e.g., *Creche*, *KG 1*, *Primary 1 to 6*, *JSS 1 to 3*, *SSS 1 to 3*).
* For each standard, add your class **Sections** or arms (e.g., *Section A*, *Section B*, *Gold*, *Diamond*).
* The combination creates specific classes (e.g., *JSS 1 - A*, *Primary 4 - Gold*).

### Step 4: Curriculum Subjects
* Add the subjects taught at your institution (e.g., *Mathematics*, *English Language*, *Basic Science*, *Civic Education*).
* Assign which subjects apply to which educational levels.

### Step 5: Staff Directory
* Add teachers, administrators, bursars, and support personnel.
* Assign their roles and assign class teachers (form teachers) to their respective classes.

### Step 6: Student Roster & Parent Linking
* Add students manually or use the **Bulk CSV Spreadsheet Import** tool to upload your entire student body in minutes.
* Include parent contact details (phone and email) to prepare family accounts.

### Step 7: Final Review & Activation
* Review your entire setup summary.
* Click **Complete Setup & Launch**.
* Download printable **Student & Parent Activation Cards** containing initial login credentials for distribution to families.

---

## 3. Managing Staff

School administrators can manage all employee profiles from **Staff Management** in the Admin Portal.

### Adding New Staff Members
1. Go to **Admin Portal** $\rightarrow$ **Staff**.
2. Click **+ Add Staff Member**.
3. Fill in the staff member's full name, email address, mobile phone number, job title, and assigned role:
   * **Teacher**: Has access to class attendance, mark entry, subject timetables, and learning materials.
   * **Accountant / Bursar**: Has access to finance ledgers, payment recording, and fee assessments.
   * **School Admin**: Has full managerial access across all operational modules.
   * **Staff**: General non-teaching staff (drivers, security, maintenance) with access to attendance check-in.
4. Set an initial password or generate an invitation link.

### Assigning Subject Teachers
To assign a teacher to a specific subject in a class:
1. Go to **Admin Portal** $\rightarrow$ **Academics** $\rightarrow$ **Class Sections**.
2. Select the target class (e.g., *SSS 2 - Science*).
3. Under **Assigned Subjects**, select the subject and choose the designated teacher from the dropdown.
4. The teacher will immediately see this class on their personal dashboard.

### Assigning Class Teachers (Form Masters)
Every class can have a designated Class Teacher responsible for daily morning roll calls and general student pastoral care:
1. Go to **Admin Portal** $\rightarrow$ **Classes & Sections**.
2. Click **Assign Class Teacher** next to the section.
3. Select the teacher and click **Save**.

---

## 4. Managing Students

Student profiles maintain a single, complete history of every child's academic journey.

### Adding an Individual Student
1. Go to **Admin Portal** $\rightarrow$ **Students**.
2. Click **+ Register Student**.
3. Provide the student's personal details:
   * Full Name, Gender, Date of Birth.
   * Admission / Registration Number (e.g., `STU-2026-015`). If left blank, SchoolOS can automatically generate one.
   * Initial Class & Section.
   * Medical / Health Notes (allergies, special conditions, blood group).
4. Click **Save Student**.

### Bulk CSV Import (Fast Ingestion)
When enrolling dozens or hundreds of students at once:
1. Go to **Admin Portal** $\rightarrow$ **Imports**.
2. Click **Download Sample CSV Template**.
3. Open the spreadsheet and fill in the columns (`full_name`, `gender`, `dob`, `class_name`, `section_name`, `parent_name`, `parent_phone`, `parent_email`).
4. Upload the completed CSV.
5. SchoolOS validates every row for format errors or duplicate registration numbers before importing.
6. Click **Confirm Import**. The system creates the students, establishes class enrollments, provisions parent profiles, and generates printable activation sheets.

### Promoting Students to the Next Grade
At the end of an academic year:
1. Go to **Admin Portal** $\rightarrow$ **Promotions**.
2. Set your promotion rules (minimum overall pass percentage, attendance requirements, and mandatory subjects like English and Maths).
3. Click **Run Promotion Evaluation**.
4. The system calculates which students have qualified for the next class.
5. Review the recommendations. You can manually adjust any decision (e.g., granting *Promoted on Trial* or *Retained*) with an administrative note.
6. Click **Confirm Promotions** to advance enrollments into the new academic year.

---

## 5. Managing Parents & Guardians

SchoolOS uses an explicit guardian identification model to ensure strict privacy and child safeguarding.

### How Parents Are Identified
Unlike systems that guess relationships based on matching surnames, SchoolOS **strictly connects parents through verified email addresses or mobile phone numbers**. This prevents sensitive academic and financial records from ever being exposed to unauthorized individuals.

### Linking Parents to Children
1. When a parent registers or is imported via spreadsheet, a `StudentParentLink` record is created.
2. The administrator reviews the relationship in **Admin Portal** $\rightarrow$ **Parent Links**.
3. Relationships can be specified as **Father**, **Mother**, **Guardian**, or **Sponsor**.
4. Once verified, click **Approve Link**.

### Multiple Children (Family Accounts)
If a family has three children enrolled across Kindergarten, Primary, and Secondary school:
* The parent signs into their account once.
* Their portal displays all three children on their dashboard.
* The parent can click on any child's card to switch between their report cards, timetables, and fee balances without logging out.

---

## 6. Classes & Subjects

### Managing Class Standards & Sections
* **Standard**: The overall year level (e.g., *Grade 5* or *JSS 2*).
* **Section**: The branch or arm (e.g., *Arm A*, *Arm B*, *Silver*).
* Administrators can add, rename, or deactivate sections under **Admin Portal** $\rightarrow$ **Academics** $\rightarrow$ **Standards**.

### Building the Timetable
SchoolOS includes a built-in timetable builder with an automated **Collision Detection Engine**:
1. Go to **Admin Portal** $\rightarrow$ **Timetable**.
2. Select the class section and day of the week.
3. Click an empty period slot to schedule a subject, teacher, and classroom.
4. **Collision Prevention**: If the assigned teacher is already booked in another class during that exact period, SchoolOS immediately displays an alert and prevents the scheduling error.
5. When the schedule is complete, click **Publish Timetable**. The timetable becomes instantly visible on student, teacher, and parent portals.

---

## 7. Attendance Tracking

### Daily Student Attendance (Class Roll Call)
Every morning, class teachers can take attendance from their phone or laptop:
1. Go to **Teacher Portal** $\rightarrow$ **Daily Attendance**.
2. Select your assigned class. The student roster appears.
3. Mark each student as:
   * **Present** (Green)
   * **Absent** (Red)
   * **Late** (Yellow)
   * **Excused** (Blue)
   * **Half-Day** (Purple)
4. Click **Submit Attendance**.

### Granular Subject Attendance
Subject teachers can also take attendance during individual periods:
1. Go to **Teacher Portal** $\rightarrow$ **Subject Attendance**.
2. Select the class, subject, and period.
3. Enter the **Topic Taught** (e.g., *"Quadratic Equations — Lesson 3"*).
4. Record student attendance and save. This provides parents and principals with clear visibility into curriculum coverage.

### Attendance Corrections & Audit Trail
If an attendance mark was recorded in error:
1. An administrator goes to **Admin Portal** $\rightarrow$ **Attendance Corrections**.
2. Locate the date and student.
3. Change the status and enter a mandatory **Reason for Correction** (e.g., *"Student arrived late due to clinic visit"*).
4. The system logs who made the correction and when, maintaining an unalterable audit trail.

### Staff Attendance (Contactless Dynamic QR)
Staff members record their daily arrivals and departures:
1. **Manual Check-In**: Employees click **Clock In** on their staff dashboard upon arrival and **Clock Out** when departing.
2. **Rotating QR Code**: For on-campus verification, the school receptionist or admin displays the live campus QR screen (`/api/staff/attendance/qr/issue`). Staff scan the code with their mobile cameras. The QR code automatically rotates every 60 seconds to prevent off-campus sharing.

---

## 8. Results & Academics

### Setting Up Exam Components
SchoolOS accommodates various assessment structures:
1. Go to **Admin Portal** $\rightarrow$ **Exams** $\rightarrow$ **Components**.
2. Define assessment weights:
   * Example Structure:
     * Continuous Assessment 1 (CA 1): 20%
     * Continuous Assessment 2 (CA 2): 20%
     * Final Examination: 60%
     * Total: 100%

### Entering Student Marks
1. Subject teachers navigate to **Teacher Portal** $\rightarrow$ **Exams**.
2. Select the exam and subject.
3. Enter scores for each student. The system prevents entering scores higher than the component maximum.
4. Add qualitative teacher remarks (e.g., *"Excellent grasp of algebraic principles"*).
5. Click **Submit for Review**.

### Administrative Review & Publishing
To prevent unverified grades from leaking to families:
1. The exam officer or administrator reviews submitted score sheets under **Admin Portal** $\rightarrow$ **Exams** $\rightarrow$ **Mark Review**.
2. Verify totals, averages, and class rankings.
3. Click **Publish Results**. Once published, results immediately unlock in student and parent portals.

### Downloading Official PDF Report Cards
Parents, students, and administrators can generate official, printable PDF report cards at any time:
1. Navigate to the student's exam record.
2. Click **Download Report Card (PDF)**.
3. The server generates a clean, branded PDF report card containing:
   * School logo, name, and official contact details.
   * Student name, registration number, and class.
   * Subject-by-subject score breakdown across all components.
   * Overall weighted total, grade, and position/ranking in class.
   * Attendance summary (days present vs total school days).
   * Class teacher and Principal/Proprietor remarks.

---

## 9. Fees & Payments

### Configuring Fee Categories & Invoices
1. Go to **Admin Portal** $\rightarrow$ **Finance** $\rightarrow$ **Categories**.
2. Create fee types (e.g., *First Term Tuition*, *School Bus Service*, *Uniform Set*, *Science Laboratory Levy*).
3. Under **Fee Assessments**, generate invoices for an entire class or specific students.

### Online Payments via Paystack (Debit Card / Transfer)
Parents can pay school fees securely from home without visiting a bank:
1. The parent logs into **Parent Portal** $\rightarrow$ **Fees & Payments**.
2. Review the invoice breakdown and outstanding balance.
3. Click **Pay with Paystack**.
4. A secure Paystack window opens allowing payment via **Debit Card**, **Bank Transfer**, or **USSD**.
5. Upon successful payment, SchoolOS instantly updates the invoice balance to **Paid** and generates a digital receipt.

### Recording Offline / Manual Payments
For parents who pay via cash, school POS machine, or direct bank teller deposit:
1. The bursar goes to **Admin Portal** $\rightarrow$ **Finance** $\rightarrow$ **Record Payment**.
2. Search for the student and select the fee invoice.
3. Enter the amount paid, payment method (Cash, POS, Bank Deposit), and bank teller/reference number.
4. Click **Submit Payment**.
5. Once approved by the head administrator, the payment is posted to the school ledger.

### Official PDF Payment Receipts
Every completed transaction generates an official, tamper-proof payment receipt:
* Parents can click **Download Receipt** directly from their payment history.
* Receipts feature the school's branding, unique transaction reference number, student registration details, fee breakdown, and cashier stamp.

---

## 10. Reports & Exports

SchoolOS allows school administrators to download institutional data for offline backups or government reporting:

### Generating Data Exports
1. Go to **Admin Portal** $\rightarrow$ **Exports**.
2. Select the dataset you wish to export:
   * **Student Roster**: Complete demographic and enrollment directory.
   * **Attendance Records**: Term-wide or date-range attendance logs.
   * **Examination Results**: Comprehensive term mark sheets.
3. Choose the export range (Current Term, Full Academic Session, or Custom Date Range).
4. Click **Generate Export Archive**.

### Storage & Download Safety
* Exports are packaged into standard CSV files and compressed into a secure ZIP file.
* Files are stored in secure cloud storage and accessed via **15-minute temporary download links**.
* For data privacy and storage hygiene, generated export files are automatically deleted after **7 days**.

---

## 11. Files & Documents

SchoolOS eliminates physical filing cabinets by organizing documents in secure cloud storage.

### Public Assets vs Private Records
* **Public Assets**: Your school logo and public website images are stored in a public repository so they can load on brochures and login screens.
* **Private Documents**: Staff credentials, student photos, and sensitive receipts are stored in encrypted, private cloud vaults. They can only be accessed by authenticated users holding active session permissions.

### Uploading Teaching Materials
Teachers can distribute syllabus materials, past questions, and reading notes:
1. Go to **Teacher Portal** $\rightarrow$ **Learning Materials**.
2. Click **Upload Material**.
3. Select the file (PDF, Word, or presentation), choose the target class and subject, and add a brief description.
4. Students and parents in that class will receive an in-app notification and can download the resource directly.

---

## 12. Notifications & Communication

### School Announcements
Broadcast important updates to your school community:
1. Go to **Admin Portal** $\rightarrow$ **Announcements** $\rightarrow$ **Create Announcement**.
2. Compose your title and announcement message.
3. Select your audience:
   * **All School** (Staff, Parents, and Students)
   * **Staff Only**
   * **Parents Only**
   * **Specific Class Sections** (e.g., *Primary 6 Parents Only*)
4. Click **Publish**.

### Read Receipts Tracking
SchoolOS provides accountability through digital read receipts:
* When an administrator opens a published announcement, they can click **View Read Receipts**.
* The system displays an exact list of which parents or staff have opened the announcement and the exact date and time it was read.

### Parent-School Feedback Channel
Parents can submit questions, suggestions, or concerns through their portal:
* Messages are delivered directly to school administrators under **Admin Portal** $\rightarrow$ **Feedback**.
* Administrators can reply within the thread, maintaining a documented, respectful communication log.

---

## 13. Understanding Your Dashboard

Your home dashboard provides a real-time pulse of your institution's daily operations:

### Key Metrics (Top Cards)
* **Total Enrolled Students**: Active student count across all grade levels.
* **Staff on Duty**: Number of teachers and employees checked in today.
* **Today's Attendance Rate**: Percentage of students present in morning roll call.
* **Outstanding Fee Balances**: Total pending tuition balance across all active assessments.

### Interactive Widgets
* **Quick Actions**: One-click shortcuts to register a student, record a payment, take attendance, or post an announcement.
* **Recent Activity Feed**: Real-time log of recent admissions, fee payments, and attendance submissions.
* **Upcoming Academic Events**: Highlights approaching term breaks, PTA meetings, and examination schedules.

---

## 14. Account & Security Settings

### Updating Your Profile
1. Click on your name or profile picture in the top-right corner and select **Profile**.
2. You can update your contact phone number, emergency contact, and profile photo.

### Changing Your Password
1. In your **Profile** settings, navigate to the **Security** tab.
2. Enter your current password, followed by your new password (minimum 8 characters).
3. Click **Update Password**.

### Signing Out
Always sign out when using a shared computer (e.g., in a staff room or computer lab):
* Click your profile menu in the top-right corner.
* Select **Sign Out**. This immediately destroys your session cookie and secures your portal.

---

## 15. Using SchoolOS on Mobile Devices

SchoolOS is fully responsive and functions smoothly on mobile smartphones and tablets.

### Accessing on Mobile
1. Open Google Chrome on your Android phone or Apple Safari on your iPhone.
2. Navigate to your school's SchoolOS URL.
3. The interface automatically adapts to your screen size with mobile-friendly menus, clear buttons, and easy roll-call toggles.

### Adding SchoolOS to Your Home Screen (Shortcut)
You can add SchoolOS to your phone's home screen so it opens like an app:
* **On iPhone (Safari)**: Tap the **Share** icon (square with an arrow pointing up) $\rightarrow$ scroll down and tap **Add to Home Screen** $\rightarrow$ tap **Add**.
* **On Android (Chrome)**: Tap the **Three Dots Menu** in the top-right corner $\rightarrow$ tap **Add to Home screen** $\rightarrow$ tap **Add**.

---

## 16. Recommended Daily Workflows

To ensure smooth school operations, we recommend following these standard daily routines:

### Morning Routine (7:30 AM – 9:00 AM)
1. **Staff Arrival**: Staff members check in via manual clock-in or by scanning the campus QR code at the reception desk.
2. **Student Roll Call**: Form teachers take morning attendance in their classrooms using their mobile phone or laptop.
3. **Absence Review**: The school administrator checks the morning dashboard to identify absent students and follow up on unexcused absences.

### Daytime Operations (9:00 AM – 2:00 PM)
1. **Subject Teaching**: Teachers record attendance and curriculum topics during their assigned subject periods.
2. **Bursary Operations**: Bursars record walk-in cash or POS payments, issuing digital receipts to parents.
3. **Inquiries & Admissions**: Admissions officers review incoming online applications submitted through the public portal (`/apply`).

### Afternoon & End-of-Day Routine (2:00 PM – 4:00 PM)
1. **Payment Reconciliation**: The bursar reviews the daily finance summary, comparing physical cash and POS receipts against recorded transactions.
2. **Publishing Announcements**: Administrators publish notices regarding upcoming school activities, homework, or event reminders.
3. **Staff Clock-Out**: Staff members log their check-out before departing campus.

### End-of-Term Routine
1. **Mark Entry & Review**: Subject teachers enter Continuous Assessment and exam scores. The Exam Officer verifies grade distributions.
2. **Publishing Results**: The administrator approves and publishes exam marks.
3. **Promotions & Report Cards**: Parents receive notification that official PDF report cards are available for download. At year-end, the administrative team executes automated promotions into the upcoming academic session.

---

*SchoolOS — Empowering schools with organized, transparent, and modern educational management.*
