# School Management System — Discovery Hierarchy

## 1. School Setup & Administration

### 1.1 School Profile
- School name
- School logo
- School initials
- School location
- Google Maps location
- Contact information
- School type:
  - Crèche
  - Primary
  - Secondary
  - College/Tertiary section, if applicable

### 1.2 Academic Structure
- Academic session
- Terms
- Classes
- Arms
- Subjects
- Departments
- Class teachers
- Subject teachers

### 1.3 School Calendar / Agenda
School management should be able to define the entire session calendar:
- Resumption date
- Term dates
- Mid-term break
- Examination periods
- School activities
- Holidays
- Closing date
- Other important events

Parents and students should be able to see the relevant calendar.

---

## 2. Student Management

### 2.1 Student Registration
- Student application
- Admission
- Student profile
- Class assignment
- Session history
- Medical information
- Parent/guardian information

### 2.2 Student ID
Generate a unique school ID.

Example:

**SCH + ADE + SS2 + 001**

Where:
- `SCH` = school initials
- `ADE` = first 3 letters of surname
- `SS2` = class
- `001` = assigned student number

### 2.3 Student ID Card
- Student photograph
- Student ID
- Name
- Class
- School information
- QR code

The QR code can potentially be used for:
- Attendance
- Student identification
- Verification

### 2.4 Student Records
Replace manually written student records with digital records.

---

## 3. Parent & Student Portal

### Parent can see
- Student profile
- Attendance
- Results
- Fees
- Amount paid
- Outstanding balance
- Transaction history
- School calendar
- Announcements
- Learning materials
- Other school information

### Parent can
- Download results
- Receive attendance notifications
- Receive fee/debt notifications
- Make fee payments
- Upload payment receipts where applicable

### Student can
- View results
- Download results
- View attendance
- View learning materials
- View timetable
- View school calendar

---

## 4. Student Attendance

### 4.1 Daily Attendance
The school indicated that attendance is taken **twice daily**:
- Morning attendance
- Afternoon attendance

### 4.2 Class Attendance
Teachers mark students present/absent.

### 4.3 Subject Attendance
For college/subject-based teaching:
- Teacher records students who attended the particular subject/class
- Attendance is connected to the subject and teacher
- Teacher can record the topic taught

### 4.4 Parent Notifications
When attendance is recorded, the system can notify the parent.

Example:

> Your child, John Doe, has been marked present at school today.

This should ideally be configurable because some schools may not want instant notifications for every attendance event.

---

## 5. Staff / Teacher Attendance

This is different from student attendance.

### Possible attendance methods
The school chooses which method(s) to use:
- QR code scanning
- School-generated daily QR code
- Geolocation verification
- Camera verification
- Face/liveness verification
- Other verification methods

**Important discovery insight:** Do not force one attendance method on every school. The school should be able to configure its preferred method.

### Staff ID Card
Teachers/staff can have:
- Staff photo
- Staff ID
- Name
- Position
- QR code

---

## 6. Teacher & Staff Management

### 6.1 Teacher Enrollment
Application should include:
- Application form
- CV upload
- Qualifications
- Supporting documents
- School rules & regulations
- Teacher responsibilities/role
- School agenda
- Possible fees/payment, if applicable

### 6.2 Teacher Profile
- Personal information
- Qualifications
- Subjects
- Classes
- Attendance
- Assigned responsibilities

### 6.3 Teacher Dashboard
Teachers should see only what applies to their role:
- My classes
- My subjects
- Student attendance
- Lesson notes
- Scheme of work
- Results
- Remarks
- Timetable

---

## 7. Academic Management

### 7.1 Scheme of Work
School/admin can upload:
- Scheme of work
- Subject
- Class
- Term/session

Teachers can then see the relevant scheme.

### 7.2 Lesson Management
Teachers can:
- View scheme of work
- Mark topic taught
- Upload lesson notes
- Upload handwritten lesson notes
- Potentially create digital lesson notes

### 7.3 E-Textbooks
School can provide:
- E-textbooks
- Digital learning materials
- Online lesson notes
- Other educational resources

---

## 8. Examination & Results

### 8.1 Examination Type
The system should support different school levels.

**Secondary**
- CBT examinations

**Primary / Crèche**
- Written examinations

The platform should not assume every school uses CBT.

### 8.2 Result Processing
Results can include:
- Scores
- Grades
- Aggregate
- Teacher's remark
- Proprietor/proprietress remark
- Attendance
- Number of times school opened
- Number of times student attended
- Overall performance

### 8.3 Result Delivery
Instead of manually sending results through WhatsApp:

**Parent/Student Portal → View Result → Download Result**

Potentially:
- PDF result
- Printable result
- Digital result

---

## 9. Student Promotion

### 9.1 Automatic Promotion
School defines its promotion criteria.

Example:

> Students with aggregate ≤ X are promoted.

The system automatically identifies students eligible for promotion.

### 9.2 Manual Promotion
Admins can override automatic promotion for special circumstances.

Human control should remain available for exceptional cases.

---

## 10. School Fees & Payments

### 10.1 Fee Categories
School can create different fees:
- Tuition/school fees
- Medical fees
- Uniform fees
- Examination fees
- Other school-specific fees

### 10.2 Flexible Payment
Parents should be able to:
- Pay online
- Pay manually
- Make flexible/part payments
- Upload payment receipts

### 10.3 Online Payment
- Paystack integration

### 10.4 Discounts
School can apply:
- Individual discounts
- Percentage discounts
- Fixed amount discounts

### 10.5 Scholarships
Support:
- Full scholarship
- Partial scholarship
- Specific fee exemptions

---

## 11. Parent Fee Portal

Parents should be able to see:

### Current Session
- Total fee
- Amount paid
- Amount remaining
- Payment status

### Previous Sessions
- Previous payments
- Outstanding debt
- Transaction history
- Rolled-over balance

### Notifications
Automatic alerts for:
- Outstanding fees
- Overdue payments
- Payment confirmation
- New fees
- Upcoming deadlines

---

## 12. Enrollment / Admission

### 12.1 Student Enrollment
The enrollment process can contain:
1. Application form
2. Student information
3. Parent/guardian information
4. Medical information
5. School fee
6. Medical fee
7. Uniform fee
8. School rules & regulations
9. Session agenda
10. Timetable
11. Other required documents

This could become a digital admission/enrollment workflow.

---

## 13. School Finance / Admin Dashboard

This turns the platform into more than an academic system.

### 13.1 Revenue
- School fees
- Other payments
- Amount received
- Outstanding payments
- Overdue payments

### 13.2 Expenses
- Staff payments
- Operational expenses
- Other school expenses

### 13.3 Cash Flow
- Money coming in
- Money going out
- Current balance
- Outstanding debt
- Previous-session debt
- Amount rolled over into next session

### 13.4 Transactions
- Payment history
- Expense history
- Transaction records
- Payment receipts

---

## 14. Communication

### 14.1 Current Communication Method
Schools currently rely heavily on:
- WhatsApp

### 14.2 School → Parent
- Announcements
- Fee alerts
- Attendance alerts
- Result notifications
- School activities

### 14.3 School → Teacher/Staff
- Announcements
- Instructions
- School activities
- Internal communication

### 14.4 Teacher → School
- Communication with administration

### 14.5 Potential Future Feature
- Internal school messaging system

The discovery finding is:

> Schools currently rely heavily on WhatsApp, but would value integrated communication.

---

## 15. Backup & Data Management

### 15.1 Automatic Backup
Admin should be able to back up school data:
- Per term
- Per session
- Custom date range

### 15.2 Backup Options
- Automatic backup
- Manual backup
- Downloadable backup

This is especially important because schools may have years of student records.

---

## 16. Security & Access

### 16.1 Student / Parent
Possible login:
- Student ID
- Email
- Password

### 16.2 Teacher / Staff
Possible login:
- Staff ID
- Email
- Password

### 16.3 Admin / Principal
Possible login:
- Email
- Password

**Note:** These authentication formats are ideas from the discovery interviews and should not yet be treated as final technical requirements.

---

## 17. Role-Based Access Control

Potential roles:

### Super Admin
- Platform owner

### School Admin
- Full school management

### Principal
- School oversight

### Teacher
- Assigned academic functions

### Accountant / Bursar
- Fees and finance

### Staff
- Assigned administrative functions

### Parent
- Their children's information

### Student
- Their own academic information

The exact roles should be validated through additional school interviews.

---

# Major Discovery Pillars

The interviews have revealed five major pillars:

## 1. School Administration
- School setup
- Staff
- Calendar
- Enrollment
- Records

## 2. Student & Academic Management
- Students
- Teachers
- Classes
- Subjects
- Attendance
- Lessons
- Examinations
- Results

## 3. Finance
- Fees
- Payments
- Discounts
- Scholarships
- Debts
- Expenses
- Transactions

## 4. Communication
- Parents
- Students
- Teachers
- Administration
- Notifications
- WhatsApp integration

## 5. Infrastructure
- Authentication
- IDs
- QR codes
- Attendance verification
- Backups
- Permissions

---

# Key Discovery Insight

The interviews suggest that schools do not simply need a **Student Management System**.

They potentially need a:

**Complete School Management Operating System**

However, these findings should not all immediately become product features.

Each item should later be classified as:

- **Must Have**
- **Important**
- **Nice to Have**
- **Not Validated Yet**

It should also be tracked against the specific school that requested it. This prevents the product from being built around assumptions from only a small number of schools.
