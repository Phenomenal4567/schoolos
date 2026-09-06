<?php

namespace Tests\Feature;

use App\Contracts\PaystackClient;
use App\Notifications\FeeAssessedNotification;
use App\Notifications\PaymentConfirmedNotification;
use App\Repositories\FeeAssessmentRepository;
use App\Repositories\PaymentRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\Concerns\CreatesSchoolOsFixtures;
use Tests\Support\FakePaystackClient;
use Tests\TestCase;

/**
 * Design ref: 26-discovery-hierarchy-status.md ("Fee / Debt Notifications").
 *
 * FeeAssessmentRepository::assess() and PaymentRepository's confirm
 * paths are the only write paths for their tables (those classes' own
 * doc comments) — this file's central assertions are that FeeAssessed/
 * PaymentConfirmed fire from exactly those paths, reach every active
 * parent of the assessed/paid-for student, and stay silent in the
 * specific cases carved out for them (a fully-covered assessment, an
 * already-confirmed payment re-verified by a webhook retry).
 */
class FeeNotificationsTest extends TestCase
{
    use RefreshDatabase;
    use CreatesSchoolOsFixtures;

    private function paymentRepositoryWithFakePaystack(FakePaystackClient $fake): PaymentRepository
    {
        $this->app->instance(PaystackClient::class, $fake);

        return $this->app->make(PaymentRepository::class);
    }

    public function test_assessing_a_fee_notifies_every_active_parent_of_the_student(): void
    {
        Notification::fake();

        $repository = app(FeeAssessmentRepository::class);

        $school = $this->makeSchool();
        $academicYear = $this->makeAcademicYear($school);
        $student = $this->makeRoleUser('student', $school);
        $category = $this->makeFeeCategory($school);
        $admin = $this->makeRoleUser('school_admin', $school);
        $parentOne = $this->makeRoleUser('parent', $school);
        $parentTwo = $this->makeRoleUser('parent', $school);
        $this->makeStudentParentLink($school, $parentOne, $student);
        $this->makeStudentParentLink($school, $parentTwo, $student);

        $repository->assess($school->id, $academicYear->id, $student->id, $category->id, '1000.00', $admin);

        Notification::assertSentTo([$parentOne, $parentTwo], FeeAssessedNotification::class);
    }

    public function test_assessing_a_fee_does_not_notify_an_inactive_parent_link(): void
    {
        Notification::fake();

        $repository = app(FeeAssessmentRepository::class);

        $school = $this->makeSchool();
        $academicYear = $this->makeAcademicYear($school);
        $student = $this->makeRoleUser('student', $school);
        $category = $this->makeFeeCategory($school);
        $admin = $this->makeRoleUser('school_admin', $school);
        $inactiveParent = $this->makeRoleUser('parent', $school);
        $this->makeStudentParentLink($school, $inactiveParent, $student, ['status' => 'inactive']);

        $repository->assess($school->id, $academicYear->id, $student->id, $category->id, '1000.00', $admin);

        Notification::assertNotSentTo($inactiveParent, FeeAssessedNotification::class);
    }

    public function test_a_scholarship_that_fully_covers_the_fee_does_not_notify_parents(): void
    {
        Notification::fake();

        $repository = app(FeeAssessmentRepository::class);

        $school = $this->makeSchool();
        $academicYear = $this->makeAcademicYear($school);
        $student = $this->makeRoleUser('student', $school);
        $category = $this->makeFeeCategory($school);
        $admin = $this->makeRoleUser('school_admin', $school);
        $parent = $this->makeRoleUser('parent', $school);
        $this->makeStudentParentLink($school, $parent, $student);
        $this->makeScholarship($school, $student, $academicYear, $admin, ['type' => 'full']);

        $repository->assess($school->id, $academicYear->id, $student->id, $category->id, '1000.00', $admin);

        Notification::assertNothingSentTo($parent);
    }

    public function test_a_manual_payment_notifies_every_active_parent_immediately(): void
    {
        Notification::fake();

        $repository = $this->paymentRepositoryWithFakePaystack(new FakePaystackClient());

        $school = $this->makeSchool();
        $academicYear = $this->makeAcademicYear($school);
        $student = $this->makeRoleUser('student', $school);
        $category = $this->makeFeeCategory($school);
        $admin = $this->makeRoleUser('school_admin', $school);
        $parent = $this->makeRoleUser('parent', $school);
        $this->makeStudentParentLink($school, $parent, $student);
        $assessment = $this->makeFeeAssessment($school, $academicYear, $student, $category, ['base_amount' => '1000.00']);

        $repository->recordManualPayment($school->id, $assessment->id, '400.00', $admin);

        Notification::assertSentTo($parent, PaymentConfirmedNotification::class);
    }

    public function test_confirming_an_online_payment_notifies_parents_only_once(): void
    {
        Notification::fake();

        $fake = (new FakePaystackClient())->verifyAs('ref-notify', true);
        $repository = $this->paymentRepositoryWithFakePaystack($fake);

        $school = $this->makeSchool();
        $academicYear = $this->makeAcademicYear($school);
        $student = $this->makeRoleUser('student', $school);
        $category = $this->makeFeeCategory($school);
        $parent = $this->makeRoleUser('parent', $school);
        $this->makeStudentParentLink($school, $parent, $student);
        $assessment = $this->makeFeeAssessment($school, $academicYear, $student, $category, ['base_amount' => '1000.00']);
        $repository->initiateOnlinePayment($school->id, $assessment->id, '1000.00', 'ref-notify');

        $repository->confirmOnlinePayment('ref-notify');
        $repository->confirmOnlinePayment('ref-notify');

        Notification::assertSentToTimes($parent, PaymentConfirmedNotification::class, 1);
    }
}
