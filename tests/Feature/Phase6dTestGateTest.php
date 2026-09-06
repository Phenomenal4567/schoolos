<?php

namespace Tests\Feature;

use App\Contracts\PaystackClient;
use App\Models\FeeAssessment;
use App\Repositories\FeeAssessmentRepository;
use App\Repositories\FeeRolloverRepository;
use App\Repositories\PaymentRepository;
use App\Services\ScopeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesSchoolOsFixtures;
use Tests\Support\FakePaystackClient;
use Tests\TestCase;

/**
 * Design ref: 19-discovery-hierarchy-gap-closure-plan.md §9,
 * 20-phase-6-8-execution-prompt.md §5,
 * 21-schoolos-finance-architecture.md, 22-schoolos-finance-schema.md §6.
 * Decision refs: 16-schoolos-decisions-register.md D11, D12, D13.
 *
 * One test per row of 22 §6's "test-gate design for this schema" note
 * (tenant + relationship scope on every fee/payment endpoint, D11's
 * amount-due immutability, D12's rollover idempotency, D13's
 * Paystack-confirmation-before-write), in the same order that note
 * lists them, matching Phase6a/Phase6b/Phase6c's own "one test per gate
 * row" shape.
 *
 * Each row here is a single summary assertion against the real
 * repository/model this track built — not the full regression surface.
 * The fuller suites live alongside this gate, the same division
 * Phase5TestGateTest draws for its own row 6: FeeAssessmentRepositoryTest
 * (D11), FeeRolloverRepositoryTest (D12), PaymentRepositoryTest and
 * PaystackWebhookControllerTest (D13), and the Admin/ParentPortal/
 * StudentPortal *ControllerTest files (the full HTTP-layer flows,
 * including 404-not-403 scope enforcement) for rows 1-2. If this file's
 * row fails, the fuller file named in that row's doc comment is where to
 * find the detailed regression.
 *
 * Row 7 (the automated route-table lint — "every controller action that
 * resolves a fee_assessment_id/payment_id by ID carries 'scope.checked'")
 * has no dedicated test in this file at all: it's already covered for
 * free by Phase1TestGateTest::test_every_show_by_id_route_has_a_registered_scope_check(),
 * which walks the whole route table generically and therefore already
 * lints every fee-assessments/{feeAssessment}, fee-assessments/{feeAssessment}/adjust,
 * fee-assessments/{feeAssessment}/payments, parent/student
 * fees/{feeAssessment}, and parent fees/{feeAssessment}/payments route
 * this track added — see that test's own doc comment. If it fails, the
 * gap is a missing ->middleware('scope.checked') in routes/web.php, not
 * a test-writing problem here.
 */
class Phase6dTestGateTest extends TestCase
{
    use RefreshDatabase;
    use CreatesSchoolOsFixtures;

    /**
     * Row 1 — admin-side fee_assessments/payments reads are tenant-
     * scoped only (22 §6: "tenantScope only" for the admin-facing row) —
     * an admin never sees another school's rows, even though their own
     * authority is school-wide. Full HTTP coverage:
     * Admin\FeeAssessmentControllerTest::test_admin_index_only_lists_their_own_schools_assessments()
     * and ::test_admin_cannot_view_another_schools_fee_assessment().
     */
    public function test_admin_fee_assessment_reads_are_tenant_scoped(): void
    {
        $scope = app(ScopeService::class);

        $schoolA = $this->makeSchool();
        $schoolB = $this->makeSchool();
        $yearA = $this->makeAcademicYear($schoolA);
        $yearB = $this->makeAcademicYear($schoolB);
        $studentA = $this->makeRoleUser('student', $schoolA);
        $studentB = $this->makeRoleUser('student', $schoolB);
        $categoryA = $this->makeFeeCategory($schoolA);
        $categoryB = $this->makeFeeCategory($schoolB);
        $adminA = $this->makeRoleUser('school_admin', $schoolA);

        $assessmentA = $this->makeFeeAssessment($schoolA, $yearA, $studentA, $categoryA);
        $this->makeFeeAssessment($schoolB, $yearB, $studentB, $categoryB);

        $visible = $scope->tenantScope(FeeAssessment::query(), $adminA)->pluck('id')->all();

        $this->assertSame([$assessmentA->id], $visible);
    }

    /**
     * Row 2 — parent/student relationshipScope: "a parent must never
     * view or pay against another student's fee assessment" (21 §5),
     * own child/self only, 404-not-403. Full HTTP coverage:
     * ParentPortal\FeeControllerTest::test_a_parent_cannot_view_a_fee_assessment_for_a_student_who_is_not_their_linked_child()
     * and StudentPortal\FeeControllerTest::test_a_student_cannot_view_another_students_fee_assessment().
     */
    public function test_parent_and_student_relationship_scope_admits_own_child_and_denies_others(): void
    {
        $scope = app(ScopeService::class);

        $school = $this->makeSchool();
        $academicYear = $this->makeAcademicYear($school);
        $category = $this->makeFeeCategory($school);

        $parent = $this->makeRoleUser('parent', $school);
        $ownChild = $this->makeRoleUser('student', $school);
        $unrelatedStudent = $this->makeRoleUser('student', $school);
        $this->makeStudentParentLink($school, $parent, $ownChild);

        $ownAssessment = $this->makeFeeAssessment($school, $academicYear, $ownChild, $category);
        $othersAssessment = $this->makeFeeAssessment($school, $academicYear, $unrelatedStudent, $category);

        $visibleToParent = $scope
            ->relationshipScope($scope->tenantScope(FeeAssessment::query(), $parent), $parent, FeeAssessment::class)
            ->pluck('id')->all();
        $this->assertSame([$ownAssessment->id], $visibleToParent);

        $visibleToUnrelatedStudent = $scope
            ->relationshipScope($scope->tenantScope(FeeAssessment::query(), $unrelatedStudent), $unrelatedStudent, FeeAssessment::class)
            ->pluck('id')->all();
        $this->assertSame([$othersAssessment->id], $visibleToUnrelatedStudent);
        $this->assertNotContains($ownAssessment->id, $visibleToUnrelatedStudent);
    }

    /**
     * Row 3 — the write-side scope obligation 21 §5 calls out as
     * strictly worse than a read-only gap: a scoped-out fee_assessment
     * must not become payable just because the caller knows its id.
     * Full HTTP coverage:
     * ParentPortal\PaymentControllerTest::test_a_parent_cannot_pay_against_an_assessment_for_a_student_who_is_not_their_linked_child().
     */
    public function test_payment_repository_still_requires_the_assessment_to_belong_to_the_calling_school(): void
    {
        $this->app->instance(PaystackClient::class, new FakePaystackClient());
        $repository = $this->app->make(PaymentRepository::class);

        $school = $this->makeSchool();
        $otherSchool = $this->makeSchool();
        $academicYear = $this->makeAcademicYear($otherSchool);
        $student = $this->makeRoleUser('student', $otherSchool);
        $category = $this->makeFeeCategory($otherSchool);
        $assessmentInOtherSchool = $this->makeFeeAssessment($otherSchool, $academicYear, $student, $category, ['base_amount' => '500.00']);

        $this->expectException(\InvalidArgumentException::class);

        $repository->recordManualPayment($school->id, $assessmentInOtherSchool->id, '100.00', $this->makeRoleUser('school_admin', $school));
    }

    /**
     * Row 4 — D11: amount_due is fixed at assessment time and never
     * rewritten once payments exist against the row; a later discount
     * is a new, separate line item instead. Full regression suite:
     * FeeAssessmentRepositoryTest.
     */
    public function test_d11_amount_due_is_immutable_once_payments_exist(): void
    {
        $repository = app(FeeAssessmentRepository::class);

        $school = $this->makeSchool();
        $academicYear = $this->makeAcademicYear($school);
        $student = $this->makeRoleUser('student', $school);
        $category = $this->makeFeeCategory($school);
        $admin = $this->makeRoleUser('school_admin', $school);

        $assessment = $repository->assess($school->id, $academicYear->id, $student->id, $category->id, '1000.00', $admin);
        $this->makePayment($school, $assessment, ['amount' => '250.00', 'status' => 'confirmed']);

        $repository->adjust($assessment->id, $admin, ['type' => 'fixed', 'value' => '100.00']);

        $this->assertSame('1000.00', $assessment->fresh()->amount_due);
        $this->assertDatabaseCount('fee_assessments', 2);
    }

    /**
     * Row 5 — D12: rolling debt over for the same (school, fromYear,
     * toYear) pair twice must not double-create rollover assessments.
     * Full regression suite: FeeRolloverRepositoryTest.
     */
    public function test_d12_rollover_is_idempotent(): void
    {
        $repository = app(FeeRolloverRepository::class);

        $school = $this->makeSchool();
        $fromYear = $this->makeAcademicYear($school);
        $toYear = $this->makeAcademicYear($school);
        $student = $this->makeRoleUser('student', $school);
        $category = $this->makeFeeCategory($school);

        $this->makeFeeAssessment($school, $fromYear, $student, $category, ['base_amount' => '300.00']);

        $repository->rollOver($school->id, $fromYear->id, $toYear->id);
        $repository->rollOver($school->id, $fromYear->id, $toYear->id);

        $this->assertSame(
            1,
            FeeAssessment::where('academic_year_id', $toYear->id)->count(),
            'Running rollOver() twice for the same year pair must not double-create the rollover assessment.'
        );
    }

    /**
     * Row 6 — D13: a Paystack payment is only ever confirmed after
     * PaystackClient::verifyTransaction() itself confirms the reference
     * — never based on a claimed webhook status alone. Full regression
     * suite: PaymentRepositoryTest, PaystackWebhookControllerTest.
     */
    public function test_d13_online_payment_confirmation_requires_paystack_verification(): void
    {
        $fake = (new FakePaystackClient())->verifyAs('gate-ref-denied', false);
        $this->app->instance(PaystackClient::class, $fake);
        $repository = $this->app->make(PaymentRepository::class);

        $school = $this->makeSchool();
        $academicYear = $this->makeAcademicYear($school);
        $student = $this->makeRoleUser('student', $school);
        $category = $this->makeFeeCategory($school);
        $assessment = $this->makeFeeAssessment($school, $academicYear, $student, $category, ['base_amount' => '1000.00']);
        $repository->initiateOnlinePayment($school->id, $assessment->id, '1000.00', 'gate-ref-denied');

        try {
            $repository->confirmOnlinePayment('gate-ref-denied');
            $this->fail('Expected PaystackVerificationFailure — confirmation must never proceed without verification.');
        } catch (\App\Exceptions\Payment\PaystackVerificationFailure) {
            // Expected.
        }

        $this->assertDatabaseHas('payments', ['paystack_reference' => 'gate-ref-denied', 'status' => 'pending']);
        $this->assertSame('1000.00', $assessment->fresh()->amountRemaining());
    }
}