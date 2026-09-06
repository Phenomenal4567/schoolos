<?php

namespace Tests\Concerns;

use App\Models\AcademicTerm;
use App\Models\AcademicYear;
use App\Models\AdmissionApplication;
use App\Models\Announcement;
use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\AttendanceRecord;
use App\Models\CalendarEvent;
use App\Models\ClassSection;
use App\Models\ClassTeacherAssignment;
use App\Models\ContentReadReceipt;
use App\Models\Discount;
use App\Models\Exam;
use App\Models\ExamComponent;
use App\Models\ExamMark;
use App\Models\ExamRemark;
use App\Models\Expense;
use App\Models\ExportJob;
use App\Models\FeeAssessment;
use App\Models\FeeCategory;
use App\Models\Feedback;
use App\Models\LearningMaterial;
use App\Models\LessonPlan;
use App\Models\Payment;
use App\Models\Promotion;
use App\Models\PromotionRule;
use App\Models\Role;
use App\Models\Scholarship;
use App\Models\SchemeOfWork;
use App\Models\School;
use App\Models\Section;
use App\Models\Standard;
use App\Models\StaffDocument;
use App\Models\StaffProfile;
use App\Models\StudentEnrollment;
use App\Models\StudentParentLink;
use App\Models\Subject;
use App\Models\TimetableSlot;
use App\Models\User;

/**
 * Deliberately not Eloquent factories: 12/13/16 don't define any seed data
 * shape, and factories would bake in assumptions about roles/schools that
 * belong in a later phase's seeder, not this test suite. Explicit ::create()
 * calls here keep every fixture's shape visible at the call site.
 */
trait CreatesSchoolOsFixtures
{
    protected function makeRole(string $key): Role
    {
        return Role::firstOrCreate(
            ['key' => $key],
            ['label' => ucwords(str_replace('_', ' ', $key))]
        );
    }

   protected function makeSchool(array $overrides = []): School
{
    static $n = 0;
    $n++;

    $attributes = array_merge([
        'name' => "Test School {$n}",
        'email' => "school{$n}@example.test",
        'phone' => "+100000000{$n}",
        'status' => 'active',
    ], $overrides);

    // 'status' is deliberately excluded from School::$fillable (see that
    // model's doc comment). School::create() would silently discard a
    // 'status' override here, so forceFill it directly instead — the
    // same pattern makeClassSection() already uses for class_teacher_id.
    $school = new School();
    $school->forceFill($attributes);
    $school->save();

    return $school;
}

    protected function makeUser(array $overrides = []): User
    {
        static $n = 0;
        $n++;

        return User::create(array_merge([
            'school_id' => null,
            'role_id' => $this->makeRole('school_admin')->id,
            'name' => "Test User {$n}",
            'email' => "user{$n}@example.test",
            'mobile_no' => null,
            'registration_number' => null,
            'password' => 'correct-password',
            'status' => 'active',
        ], $overrides));
    }

    /**
     * Convenience wrapper around makeUser() for the common "a user with
     * this role, in this school" shape every role-filtered-list test
     * needs — avoids repeating makeRole($key)->id inline at every call
     * site the way DashboardControllerTest's own-school isolation test
     * already does for $academicYears/$subjects via makeAcademicYear()/
     * makeSubject(). $roleKey is passed straight through to makeRole(),
     * so it firstOrCreate()s the same Role row every other fixture/test
     * in the suite resolves via that same key (e.g. 'teacher',
     * 'student', 'parent', 'school_admin').
     */
    protected function makeRoleUser(string $roleKey, School $school, array $overrides = []): User
    {
        return $this->makeUser(array_merge([
            'role_id' => $this->makeRole($roleKey)->id,
            'school_id' => $school->id,
        ], $overrides));
    }

    protected function makeAcademicYear(School $school, array $overrides = []): AcademicYear
    {
        static $n = 0;
        $n++;

        return AcademicYear::create(array_merge([
            'school_id' => $school->id,
            'label' => "202{$n}-202" . ($n + 1),
            'is_current' => true,
        ], $overrides));
    }

    protected function makeAcademicTerm(School $school, AcademicYear $academicYear, array $overrides = []): AcademicTerm
    {
        static $n = 0;
        $n++;

        return AcademicTerm::create(array_merge([
            'school_id' => $school->id,
            'academic_year_id' => $academicYear->id,
            'name' => "Term {$n}",
            'start_date' => now()->addMonths($n)->toDateString(),
            'end_date' => now()->addMonths($n + 3)->toDateString(),
        ], $overrides));
    }

    /**
     * Design ref: 19-discovery-hierarchy-gap-closure-plan.md §11,
     * 20-phase-6-8-execution-prompt.md §2. SCAFFOLDING ONLY — see
     * ExportJobRepository's doc comment. Defaults to range_type =
     * 'session' since that needs the fewest additional fixtures;
     * override range_type/academic_term_id/start_date/end_date for
     * 'term'/'custom' cases.
     */
    protected function makeExportJob(School $school, User $requestedBy, array $overrides = []): ExportJob
    {
        return ExportJob::create(array_merge([
            'school_id' => $school->id,
            'requested_by' => $requestedBy->id,
            'range_type' => 'session',
            'academic_term_id' => null,
            'academic_year_id' => $this->makeAcademicYear($school)->id,
            'start_date' => null,
            'end_date' => null,
            'status' => 'queued',
            'file_path' => null,
            'expires_at' => null,
        ], $overrides));
    }

    protected function makeClassSection(School $school, AcademicYear $academicYear, User $classTeacher, array $overrides = []): ClassSection
    {
        $standard = Standard::create(['school_id' => $school->id, 'name' => 'Grade ' . random_int(1, 12)]);
        $section = Section::create(['school_id' => $school->id, 'name' => chr(random_int(65, 90))]);

                $attributes = array_merge([
            'school_id' => $school->id,
            'academic_year_id' => $academicYear->id,
            'standard_id' => $standard->id,
            'section_id' => $section->id,
            'class_teacher_id' => $classTeacher->id,
        ], $overrides);

        // class_teacher_id is deliberately excluded from ClassSection::$fillable
        // (D3 — only ClassSectionRepository::assignClassTeacher() writes it in
        // production). This fixture is trusted test setup with an already
        // role-validated $classTeacher, so forceFill() intentionally bypasses
        // the mass-assignment guard here rather than routing through the
        // repository's role-check + audit-log machinery, which this fixture
        // doesn't need.
        $classSection = new ClassSection();
        $classSection->forceFill($attributes);
        $classSection->save();

        return $classSection;
    }

    protected function makeSubject(School $school, array $overrides = []): Subject
    {
        static $n = 0;
        $n++;

        return Subject::create(array_merge([
            'school_id' => $school->id,
            'name' => "Subject {$n}",
            'code' => "SUB{$n}",
        ], $overrides));
    }

    protected function makeClassTeacherAssignment(
        School $school,
        AcademicYear $academicYear,
        ClassSection $classSection,
        Subject $subject,
        User $teacher,
        array $overrides = []
    ): ClassTeacherAssignment {
        return ClassTeacherAssignment::create(array_merge([
            'school_id' => $school->id,
            'academic_year_id' => $academicYear->id,
            'class_section_id' => $classSection->id,
            'subject_id' => $subject->id,
            'teacher_id' => $teacher->id,
        ], $overrides));
    }

    protected function makeStudentEnrollment(
        School $school,
        AcademicYear $academicYear,
        ClassSection $classSection,
        User $student,
        array $overrides = []
    ): StudentEnrollment {
        static $n = 0;
        $n++;

        return StudentEnrollment::create(array_merge([
            'school_id' => $school->id,
            'academic_year_id' => $academicYear->id,
            'class_section_id' => $classSection->id,
            'student_id' => $student->id,
            'roll_number' => "R{$n}",
            'status' => 'active',
        ], $overrides));
    }

    protected function makeStudentParentLink(
        School $school,
        User $parent,
        User $student,
        array $overrides = []
    ): StudentParentLink {
        return StudentParentLink::create(array_merge([
            'school_id' => $school->id,
            'parent_id' => $parent->id,
            'student_id' => $student->id,
            'status' => 'active',
        ], $overrides));
    }

    // --- Phase 4 (17-schoolos-academic-domain-map.md) fixtures ---
    //
    // Timetable/LessonPlan/Assignment/Exam all carry class_section_id and
    // are covered by ScopeService's existing generic dispatch (17 §3) —
    // these four fixtures exist only to give Phase4TestGateTest.php real
    // rows to query, not because any of them need special setup.
    // AssignmentSubmission/ExamMark are the two F29-shaped resources that
    // need the new teacherRelationshipScope() branch, so their fixtures
    // take an explicit $assignment/$exam rather than a class_section_id
    // directly, matching those models' own shape.

    protected function makeTimetableSlot(
        School $school,
        AcademicYear $academicYear,
        ClassSection $classSection,
        Subject $subject,
        User $teacher,
        array $overrides = []
    ): TimetableSlot {
        static $n = 0;
        $n++;

        return TimetableSlot::create(array_merge([
            'school_id' => $school->id,
            'academic_year_id' => $academicYear->id,
            'class_section_id' => $classSection->id,
            'subject_id' => $subject->id,
            'teacher_id' => $teacher->id,
            'day_of_week' => 'monday',
            'period_number' => $n,
        ], $overrides));
    }

    protected function makeLessonPlan(
        School $school,
        AcademicYear $academicYear,
        ClassSection $classSection,
        Subject $subject,
        User $teacher,
        array $overrides = []
    ): LessonPlan {
        static $n = 0;
        $n++;

        return LessonPlan::create(array_merge([
            'school_id' => $school->id,
            'academic_year_id' => $academicYear->id,
            'class_section_id' => $classSection->id,
            'subject_id' => $subject->id,
            'teacher_id' => $teacher->id,
            'title' => "Lesson Plan {$n}",
            'content' => 'Fixture content.',
            'status' => 'submitted',
        ], $overrides));
    }

    protected function makeAssignment(
        School $school,
        AcademicYear $academicYear,
        ClassSection $classSection,
        Subject $subject,
        User $teacher,
        array $overrides = []
    ): Assignment {
        static $n = 0;
        $n++;

        return Assignment::create(array_merge([
            'school_id' => $school->id,
            'academic_year_id' => $academicYear->id,
            'class_section_id' => $classSection->id,
            'subject_id' => $subject->id,
            'teacher_id' => $teacher->id,
            'title' => "Assignment {$n}",
            'description' => 'Fixture description.',
            'due_date' => null,
        ], $overrides));
    }

    protected function makeAssignmentSubmission(
        School $school,
        Assignment $assignment,
        User $student,
        array $overrides = []
    ): AssignmentSubmission {
        return AssignmentSubmission::create(array_merge([
            'school_id' => $school->id,
            'assignment_id' => $assignment->id,
            'student_id' => $student->id,
            'submitted_at' => now(),
            'obtained_marks' => null,
            'comments' => null,
            'graded_by' => null,
            'graded_at' => null,
        ], $overrides));
    }

    protected function makeExam(
        School $school,
        AcademicYear $academicYear,
        ClassSection $classSection,
        Subject $subject,
        array $overrides = []
    ): Exam {
        static $n = 0;
        $n++;

        return Exam::create(array_merge([
            'school_id' => $school->id,
            'academic_year_id' => $academicYear->id,
            'class_section_id' => $classSection->id,
            'subject_id' => $subject->id,
            'name' => "Exam {$n}",
            'exam_date' => now()->toDateString(),
        ], $overrides));
    }

    protected function makeExamComponent(School $school, array $overrides = []): ExamComponent
    {
        static $n = 0;
        $n++;

        return ExamComponent::create(array_merge([
            'school_id' => $school->id,
            'name' => "Component {$n}",
            'weight_percent' => 100,
        ], $overrides));
    }

    protected function makeExamMark(
        School $school,
        Exam $exam,
        User $student,
        User $recordedBy,
        array $overrides = []
    ): ExamMark {
        $component = $overrides['exam_component_id'] ?? $this->makeExamComponent($school)->id;

        return ExamMark::create(array_merge([
            'school_id' => $school->id,
            'exam_id' => $exam->id,
            'exam_component_id' => $component,
            'student_id' => $student->id,
            'marks_obtained' => 0,
            'max_marks' => 100,
            'status' => 'published',
            'recorded_by' => $recordedBy->id,
            'recorded_at' => now(),
        ], $overrides));
    }

    protected function makeStaffProfile(School $school, User $staff, array $overrides = []): StaffProfile
    {
        return StaffProfile::create(array_merge([
            'school_id' => $school->id,
            'user_id' => $staff->id,
            'qualifications' => ['B.Sc. Education'],
            'responsibilities' => null,
            'cv_path' => null,
            'rules_acknowledged_at' => null,
        ], $overrides));
    }

    protected function makeStaffDocument(School $school, User $staff, array $overrides = []): StaffDocument
    {
        static $n = 0;
        $n++;

        return StaffDocument::create(array_merge([
            'school_id' => $school->id,
            'user_id' => $staff->id,
            'label' => "Document {$n}",
            'file_path' => "staff-documents/document-{$n}.pdf",
            'uploaded_by' => $staff->id,
        ], $overrides));
    }

    protected function makeExamRemark(School $school, Exam $exam, User $student, array $overrides = []): ExamRemark
    {
        return ExamRemark::create(array_merge([
            'school_id' => $school->id,
            'exam_id' => $exam->id,
            'student_id' => $student->id,
        ], $overrides));
    }

    protected function makeAttendanceRecord(
        School $school,
        AcademicYear $academicYear,
        ClassSection $classSection,
        User $student,
        array $overrides = []
    ): AttendanceRecord {
        static $n = 0;
        $n++;

        return AttendanceRecord::create(array_merge([
            'school_id' => $school->id,
            'academic_year_id' => $academicYear->id,
            'class_section_id' => $classSection->id,
            'student_id' => $student->id,
            'recorded_by' => $student->id,
            'date' => now()->subDays($n)->toDateString(),
            'session' => 'morning',
            'status' => 'present',
            'recorded_at' => now(),
        ], $overrides));
    }

    // --- Phase 5 (18-schoolos-communication-domain-map.md) fixtures ---
    //
    // Announcement/Feedback both carry their own scoping column
    // (class_section_id / student_id respectively) already covered by
    // ScopeService's existing generic dispatch — these fixtures exist
    // only to give Phase5TestGateTest.php real rows to query against,
    // same reasoning the Phase 4 fixtures block above gives.

    protected function makeAnnouncement(
        School $school,
        User $author,
        string $audienceType,
        ?ClassSection $classSection = null,
        array $overrides = []
    ): Announcement {
        static $n = 0;
        $n++;

        return Announcement::create(array_merge([
            'school_id' => $school->id,
            'author_id' => $author->id,
            'title' => "Announcement {$n}",
            'body' => 'Fixture body.',
            'audience_type' => $audienceType,
            'class_section_id' => $classSection?->id,
            'published_at' => now(),
        ], $overrides));
    }

    protected function makeFeedback(
        School $school,
        User $student,
        User $author,
        string $recipientType = 'school',
        ?User $recipientTeacher = null,
        array $overrides = []
    ): Feedback {
        static $n = 0;
        $n++;

        return Feedback::create(array_merge([
            'school_id' => $school->id,
            'student_id' => $student->id,
            'author_id' => $author->id,
            'category' => null,
            'message' => "Fixture feedback message {$n}.",
            'recipient_type' => $recipientType,
            'recipient_teacher_id' => $recipientTeacher?->id,
        ], $overrides));
    }

    // --- Track 6a (20-phase-6-8-execution-prompt.md §1) fixture ---
    //
    // calendar_events carries no class_section_id/student_id — see
    // CalendarEvent's own doc comment — so this fixture exists only to
    // give Phase6aTestGateTest.php real rows to query against, same
    // reasoning the Phase 4/5 fixture blocks above give.

    protected function makeCalendarEvent(
        School $school,
        AcademicYear $academicYear,
        array $overrides = []
    ): CalendarEvent {
        static $n = 0;
        $n++;

        return CalendarEvent::create(array_merge([
            'school_id' => $school->id,
            'academic_year_id' => $academicYear->id,
            'academic_term_id' => null,
            'title' => "Calendar Event {$n}",
            'event_type' => 'activity',
            'start_date' => now()->addDays($n)->toDateString(),
            'end_date' => null,
            'visible_to_parents' => true,
        ], $overrides));
    }

    // --- Track 6c (19-discovery-hierarchy-gap-closure-plan.md §6,
    // 20-phase-6-8-execution-prompt.md §3) fixtures ---
    //
    // scheme_of_work carries class_section_id NOT NULL (covered by
    // ScopeService's existing generic dispatch, no special fixture
    // shape needed beyond what Phase 4's fixtures above already
    // establish). learning_materials' class_section_id/subject_id are
    // both nullable (a school-wide material passes null for both) —
    // this fixture exists to give Phase6cTestGateTest.php real rows of
    // both shapes to query against.

    protected function makeSchemeOfWork(
        School $school,
        AcademicTerm $academicTerm,
        ClassSection $classSection,
        Subject $subject,
        User $uploadedBy,
        array $overrides = []
    ): SchemeOfWork {
        static $n = 0;
        $n++;

        return SchemeOfWork::create(array_merge([
            'school_id' => $school->id,
            'academic_term_id' => $academicTerm->id,
            'class_section_id' => $classSection->id,
            'subject_id' => $subject->id,
            'content' => "Scheme of work content {$n}.",
            'uploaded_by' => $uploadedBy->id,
        ], $overrides));
    }

    protected function makeLearningMaterial(
        School $school,
        ?ClassSection $classSection = null,
        ?Subject $subject = null,
        array $overrides = []
    ): LearningMaterial {
        static $n = 0;
        $n++;

        return LearningMaterial::create(array_merge([
            'school_id' => $school->id,
            'class_section_id' => $classSection?->id,
            'subject_id' => $subject?->id,
            'title' => "Learning Material {$n}",
            'file_path' => "materials/fixture-{$n}.pdf",
            'material_type' => 'notes',
        ], $overrides));
    }

    /**
     * Only needed if a test asserts against an existing receipt row
     * directly rather than through ContentReadReceiptRepository::markRead()
     * — that repository's own tests should prefer calling markRead()
     * itself over this fixture, so the write-time visibility check stays
     * exercised.
     */
    protected function makeContentReadReceipt(
        School $school,
        User $user,
        string $entityType,
        int $entityId,
        array $overrides = []
    ): ContentReadReceipt {
        return ContentReadReceipt::create(array_merge([
            'school_id' => $school->id,
            'user_id' => $user->id,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'read_at' => now(),
        ], $overrides));
    }

    // --- Phase 6d (21-schoolos-finance-architecture.md,
    // 22-schoolos-finance-schema.md) fixtures ---
    //
    // Direct ::create() calls, same as every fixture above — none of
    // these five tables carry a mass-assignment-guarded invariant column
    // the way ClassSection::class_teacher_id does (D3), so no forceFill()
    // is needed anywhere in this block. Tests exercising the actual
    // write-path invariants (D11's amount_due-fixed-at-creation, D12's
    // rollover, D13's Paystack-verify-before-confirm) call
    // FeeAssessmentRepository/PaymentRepository/FeeRolloverRepository
    // directly rather than relying on these fixtures to encode that
    // logic — these exist only to give read-path/scope tests real rows
    // to query, the same division FeeAssessment's own doc comment draws
    // between "the one write path" and everything else.

    protected function makeFeeCategory(School $school, array $overrides = []): FeeCategory
    {
        static $n = 0;
        $n++;

        return FeeCategory::create(array_merge([
            'school_id' => $school->id,
            'key' => "fee-category-{$n}",
            'label' => "Fee Category {$n}",
            'is_system_reserved' => false,
        ], $overrides));
    }

    /**
     * amount_due defaults to base_amount − discount_amount −
     * scholarship_amount, mirroring D11's formula, so a caller that only
     * overrides base_amount still gets a self-consistent row. Pass
     * 'amount_due' explicitly in $overrides to deliberately construct an
     * inconsistent row for a test that needs one.
     */
    protected function makeFeeAssessment(
        School $school,
        AcademicYear $academicYear,
        User $student,
        FeeCategory $feeCategory,
        array $overrides = []
    ): FeeAssessment {
        $baseAmount = (string) ($overrides['base_amount'] ?? '1000.00');
        $discountAmount = (string) ($overrides['discount_amount'] ?? '0.00');
        $scholarshipAmount = (string) ($overrides['scholarship_amount'] ?? '0.00');

        $defaults = [
            'school_id' => $school->id,
            'academic_year_id' => $academicYear->id,
            'student_id' => $student->id,
            'fee_category_id' => $feeCategory->id,
            'base_amount' => $baseAmount,
            'discount_amount' => $discountAmount,
            'scholarship_amount' => $scholarshipAmount,
            'amount_due' => bcsub(bcsub($baseAmount, $discountAmount, 2), $scholarshipAmount, 2),
            'due_date' => null,
            'rolled_over_from_assessment_id' => null,
            'status' => 'open',
        ];

        return FeeAssessment::create(array_merge($defaults, $overrides));
    }

    protected function makePayment(School $school, FeeAssessment $assessment, array $overrides = []): Payment
    {
        return Payment::create(array_merge([
            'school_id' => $school->id,
            'fee_assessment_id' => $assessment->id,
            'amount' => '100.00',
            'processor' => 'manual',
            'paystack_reference' => null,
            'receipt_upload_path' => null,
            'status' => 'confirmed',
            'recorded_by' => null,
        ], $overrides));
    }

    protected function makeDiscount(
        School $school,
        FeeAssessment $assessment,
        User $grantedBy,
        array $overrides = []
    ): Discount {
        return Discount::create(array_merge([
            'school_id' => $school->id,
            'fee_assessment_id' => $assessment->id,
            'type' => 'fixed',
            'value' => '0.00',
            'granted_by' => $grantedBy->id,
            'reason' => null,
        ], $overrides));
    }

    protected function makeScholarship(
        School $school,
        User $student,
        AcademicYear $academicYear,
        User $grantedBy,
        array $overrides = []
    ): Scholarship {
        return Scholarship::create(array_merge([
            'school_id' => $school->id,
            'student_id' => $student->id,
            'academic_year_id' => $academicYear->id,
            'type' => 'full',
            'value' => null,
            'fee_category_id' => null,
            'granted_by' => $grantedBy->id,
            'reason' => null,
        ], $overrides));
    }

    protected function makeExpense(School $school, User $recordedBy, array $overrides = []): Expense
    {
        static $n = 0;
        $n++;

        return Expense::create(array_merge([
            'school_id' => $school->id,
            'category' => 'operational',
            'amount' => '500.00',
            'description' => "Expense {$n}",
            'incurred_on' => now()->toDateString(),
            'recorded_by' => $recordedBy->id,
        ], $overrides));
    }

    // --- Track 7b (19-discovery-hierarchy-gap-closure-plan.md §8,
    // 20-phase-6-8-execution-prompt.md §6) fixtures ---

    protected function makePromotionRule(
        School $school,
        AcademicYear $academicYear,
        Standard $standard,
        array $overrides = []
    ): PromotionRule {
        return PromotionRule::create(array_merge([
            'school_id' => $school->id,
            'academic_year_id' => $academicYear->id,
            'standard_id' => $standard->id,
            'criteria' => ['min_aggregate' => 40],
        ], $overrides));
    }

    protected function makePromotion(
        School $school,
        StudentEnrollment $enrollment,
        ClassSection $fromClassSection,
        ?ClassSection $toClassSection,
        array $overrides = []
    ): Promotion {
        return Promotion::create(array_merge([
            'school_id' => $school->id,
            'student_enrollment_id' => $enrollment->id,
            'student_id' => $enrollment->student_id,
            'from_class_section_id' => $fromClassSection->id,
            'to_class_section_id' => $toClassSection?->id,
            'method' => 'automatic',
            'decided_by' => null,
            'reason' => null,
            'decided_at' => now(),
        ], $overrides));
    }

    /**
     * Track 7c (19 §10, discovery §12). Defaults to a single acknowledged
     * fee category belonging to $school — pass 'fee_category_acknowledgments'
     * in $overrides to construct a specific (including cross-tenant,
     * invalid) set for a test that needs one.
     */
    protected function makeAdmissionApplication(School $school, array $overrides = []): AdmissionApplication
    {
        static $n = 0;
        $n++;

        $feeCategoryAcknowledgments = $overrides['fee_category_acknowledgments']
            ?? [$this->makeFeeCategory($school)->id];

        return AdmissionApplication::create(array_merge([
            'school_id' => $school->id,
            'status' => 'submitted',
            'applicant_data' => [
                'name' => "Applicant {$n}",
                'date_of_birth' => '2015-01-01',
                'email' => null,
                'mobile_no' => null,
                'guardian_name' => "Guardian {$n}",
                'guardian_relationship' => 'Parent',
                'guardian_phone' => "+200000000{$n}",
                'medical_info' => null,
            ],
            'fee_category_acknowledgments' => $feeCategoryAcknowledgments,
            'documents' => null,
            'submitted_at' => now(),
        ], $overrides));
    }
}
