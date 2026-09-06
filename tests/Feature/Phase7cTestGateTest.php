<?php

namespace Tests\Feature;

use App\Exceptions\Admission\MissingDecisionReasonFailure;
use App\Models\AdmissionApplication;
use App\Models\StudentEnrollment;
use App\Models\User;
use App\Repositories\AdmissionApplicationRepository;
use App\Repositories\EnrollmentRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesSchoolOsFixtures;
use Tests\TestCase;

/**
 * Design ref: 19-discovery-hierarchy-gap-closure-plan.md §10, discovery
 * doc §12 (11-step admission/enrollment workflow), 24-schoolos-track-7-
 * remediation-prompt.md §3.
 * Decision ref: 16-schoolos-decisions-register.md D16.
 *
 * One test per item 24 §3's build prompt lists, in the same order:
 * (a) unauthenticated submission with a valid school short_code and
 * valid fee-category ids succeeds, (b) a submission with a fee-category
 * id from a different school is rejected, (c) accept() creates exactly
 * one User (role=student) and exactly one student_enrollments row, both
 * attributed back onto the admission_applications row, (d) accept() on
 * an already-decided application is rejected, not silently re-run, (e)
 * reject()/withdraw() without a decision_reason are rejected, mirroring
 * Phase7bTestGateTest's empty-reason test for
 * PromotionRepository::override().
 *
 * Row 8's route-table lint already covers every admin
 * admission-applications/{admissionApplication} route this track
 * added — no dedicated test for that here, same reasoning
 * Phase6dTestGateTest's/Phase7bTestGateTest's own doc comments give for
 * their own equivalent rows.
 */
class Phase7cTestGateTest extends TestCase
{
    use RefreshDatabase;
    use CreatesSchoolOsFixtures;

    /**
     * (a) — an unauthenticated submission with a valid school short_code
     * and valid fee-category ids succeeds: no auth, a real
     * admission_applications row is written with status = submitted.
     */
    public function test_unauthenticated_submission_with_valid_short_code_and_fee_categories_succeeds(): void
    {
        $school = $this->makeSchool(['short_code' => 'ACME']);
        $feeCategory = $this->makeFeeCategory($school);

        $response = $this->post('/apply?school=' . $school->short_code, [
            'name' => 'Jane Applicant',
            'date_of_birth' => '2015-06-01',
            'guardian_name' => 'John Guardian',
            'guardian_relationship' => 'Father',
            'guardian_phone' => '+2340000000',
            'fee_category_acknowledgments' => [$feeCategory->id],
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('admission_applications', [
            'school_id' => $school->id,
            'status' => 'submitted',
        ]);
    }

    /**
     * (b) — a submission with a fee-category id from a *different*
     * school is rejected: the one cross-tenant path this track
     * introduces without an authenticated actor to scope from, tested
     * explicitly rather than assumed to work because it's a one-line
     * `whereIn`.
     */
    public function test_submission_with_fee_category_from_a_different_school_is_rejected(): void
    {
        $school = $this->makeSchool(['short_code' => 'ACME2']);
        $otherSchool = $this->makeSchool();
        $foreignFeeCategory = $this->makeFeeCategory($otherSchool);

        $response = $this->post('/apply?school=' . $school->short_code, [
            'name' => 'Jane Applicant',
            'date_of_birth' => '2015-06-01',
            'guardian_name' => 'John Guardian',
            'guardian_relationship' => 'Father',
            'guardian_phone' => '+2340000000',
            'fee_category_acknowledgments' => [$foreignFeeCategory->id],
        ]);

        $response->assertSessionHasErrors('fee_category_acknowledgments');
        $this->assertDatabaseMissing('admission_applications', [
            'school_id' => $school->id,
        ]);
    }

    /**
     * (c) — accept() creates exactly one User with role = student and
     * exactly one student_enrollments row, both attributed back onto
     * the admission_applications row.
     */
    public function test_accept_creates_exactly_one_student_user_and_one_enrollment(): void
    {
        $repository = app(AdmissionApplicationRepository::class);
        $enrollmentRepository = app(EnrollmentRepository::class);

        $school = $this->makeSchool();
        $year = $this->makeAcademicYear($school);
        $admin = $this->makeRoleUser('school_admin', $school);
        $teacher = $this->makeRoleUser('teacher', $school);
        $classSection = $this->makeClassSection($school, $year, $teacher);
        $application = $this->makeAdmissionApplication($school);

        // accept() resolves the 'student' Role by key rather than
        // creating one (roles are global platform vocabulary per D4,
        // seeded once by RoleSeeder in every real environment — see
        // that seeder's own doc comment). This suite never runs
        // RoleSeeder, so unlike every other accept()-adjacent fixture
        // here, which gets a 'student' Role row as a side effect of
        // makeRoleUser('student', ...), this test needs it created
        // explicitly before accept() can resolve it.
        $this->makeRole('student');

        $usersBefore = User::count();
        $enrollmentsBefore = StudentEnrollment::count();

        $accepted = $repository->accept(
            $school->id,
            $application,
            $year->id,
            $classSection->id,
            'R1',
            $admin,
            $enrollmentRepository
        );

        $this->assertSame($usersBefore + 1, User::count());
        $this->assertSame($enrollmentsBefore + 1, StudentEnrollment::count());

        $this->assertSame('accepted', $accepted->status);
        $this->assertNotNull($accepted->resulting_user_id);
        $this->assertNotNull($accepted->resulting_student_enrollment_id);

        $student = User::find($accepted->resulting_user_id);
        $this->assertSame('student', $student->role->key);

        $enrollment = StudentEnrollment::find($accepted->resulting_student_enrollment_id);
        $this->assertSame($student->id, $enrollment->student_id);
        $this->assertSame($classSection->id, $enrollment->class_section_id);
    }

    /**
     * (d) — accept() on an already-decided application (accepted/
     * rejected/withdrawn) is rejected, not silently re-run.
     */
    public function test_accept_on_an_already_decided_application_is_rejected(): void
    {
        $repository = app(AdmissionApplicationRepository::class);
        $enrollmentRepository = app(EnrollmentRepository::class);

        $school = $this->makeSchool();
        $year = $this->makeAcademicYear($school);
        $admin = $this->makeRoleUser('school_admin', $school);
        $teacher = $this->makeRoleUser('teacher', $school);
        $classSection = $this->makeClassSection($school, $year, $teacher);
        $application = $this->makeAdmissionApplication($school, ['status' => 'rejected']);

        $usersBefore = User::count();

        $this->expectException(\InvalidArgumentException::class);

        $repository->accept(
            $school->id,
            $application,
            $year->id,
            $classSection->id,
            'R1',
            $admin,
            $enrollmentRepository
        );

        $this->assertSame($usersBefore, User::count());
    }

    /**
     * (e) — reject()/withdraw() without a decision_reason are rejected,
     * mirroring Phase7bTestGateTest's empty-reason test for
     * PromotionRepository::override().
     */
    public function test_reject_and_withdraw_without_a_reason_are_rejected(): void
    {
        $repository = app(AdmissionApplicationRepository::class);

        $school = $this->makeSchool();
        $admin = $this->makeRoleUser('school_admin', $school);

        $rejectApplication = $this->makeAdmissionApplication($school);
        try {
            $repository->reject($school->id, $rejectApplication, '', $admin);
            $this->fail('Expected MissingDecisionReasonFailure to be thrown for reject().');
        } catch (MissingDecisionReasonFailure $e) {
            $this->assertSame($rejectApplication->id, $e->application->id);
        }
        $this->assertSame('submitted', $rejectApplication->refresh()->status);

        $withdrawApplication = $this->makeAdmissionApplication($school);
        try {
            $repository->withdraw($school->id, $withdrawApplication, '   ', $admin);
            $this->fail('Expected MissingDecisionReasonFailure to be thrown for withdraw().');
        } catch (MissingDecisionReasonFailure $e) {
            $this->assertSame($withdrawApplication->id, $e->application->id);
        }
        $this->assertSame('submitted', $withdrawApplication->refresh()->status);
    }
}
