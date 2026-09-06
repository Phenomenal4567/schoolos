<?php

namespace Tests\Feature;

use App\Notifications\FeeDeadlineUpcomingNotification;
use App\Notifications\FeeOverdueNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\Concerns\CreatesSchoolOsFixtures;
use Tests\TestCase;

/**
 * Design ref: 26-discovery-hierarchy-status.md ("Fee / Debt Notifications").
 *
 * fees:send-reminders (SendFeeReminders) is the date-driven half of fee/
 * debt notifications — FeeNotificationsTest covers the event-driven half
 * (FeeAssessed/PaymentConfirmed). Central assertions here: an assessment
 * exactly 3 days from its due_date gets one upcoming-deadline reminder
 * and never a second one; an overdue assessment gets a reminder, then
 * no repeat until the 7-day cooldown has actually elapsed; a paid-off
 * assessment is never reminded regardless of its due_date.
 */
class SendFeeRemindersTest extends TestCase
{
    use RefreshDatabase;
    use CreatesSchoolOsFixtures;

    public function test_an_assessment_three_days_from_due_date_gets_one_upcoming_deadline_reminder(): void
    {
        Notification::fake();

        $school = $this->makeSchool();
        $academicYear = $this->makeAcademicYear($school);
        $student = $this->makeRoleUser('student', $school);
        $category = $this->makeFeeCategory($school);
        $parent = $this->makeRoleUser('parent', $school);
        $this->makeStudentParentLink($school, $parent, $student);
        $this->makeFeeAssessment($school, $academicYear, $student, $category, [
            'base_amount' => '1000.00',
            'due_date' => now()->addDays(3)->toDateString(),
        ]);

        $this->artisan('fees:send-reminders')->assertExitCode(0);

        Notification::assertSentToTimes($parent, FeeDeadlineUpcomingNotification::class, 1);

        $this->artisan('fees:send-reminders')->assertExitCode(0);

        Notification::assertSentToTimes($parent, FeeDeadlineUpcomingNotification::class, 1);
    }

    public function test_an_overdue_assessment_is_reminded_again_only_after_the_cooldown_elapses(): void
    {
        Notification::fake();

        $school = $this->makeSchool();
        $academicYear = $this->makeAcademicYear($school);
        $student = $this->makeRoleUser('student', $school);
        $category = $this->makeFeeCategory($school);
        $parent = $this->makeRoleUser('parent', $school);
        $this->makeStudentParentLink($school, $parent, $student);
        $assessment = $this->makeFeeAssessment($school, $academicYear, $student, $category, [
            'base_amount' => '1000.00',
            'due_date' => now()->subDays(10)->toDateString(),
        ]);

        $this->artisan('fees:send-reminders')->assertExitCode(0);
        Notification::assertSentToTimes($parent, FeeOverdueNotification::class, 1);

        // Still within the 7-day cooldown — no second reminder yet.
        $this->artisan('fees:send-reminders')->assertExitCode(0);
        Notification::assertSentToTimes($parent, FeeOverdueNotification::class, 1);

        $assessment->update(['overdue_reminder_sent_at' => now()->subDays(8)]);

        $this->artisan('fees:send-reminders')->assertExitCode(0);
        Notification::assertSentToTimes($parent, FeeOverdueNotification::class, 2);
    }

    public function test_a_school_admin_configured_repeat_cadence_is_honored(): void
    {
        Notification::fake();

        $school = $this->makeSchool(['fee_overdue_reminder_days' => 2]);
        $academicYear = $this->makeAcademicYear($school);
        $student = $this->makeRoleUser('student', $school);
        $category = $this->makeFeeCategory($school);
        $parent = $this->makeRoleUser('parent', $school);
        $this->makeStudentParentLink($school, $parent, $student);
        $assessment = $this->makeFeeAssessment($school, $academicYear, $student, $category, [
            'base_amount' => '1000.00',
            'due_date' => now()->subDays(10)->toDateString(),
        ]);

        $this->artisan('fees:send-reminders')->assertExitCode(0);
        Notification::assertSentToTimes($parent, FeeOverdueNotification::class, 1);

        // The school's own 2-day cadence, not the 7-day default, governs
        // when a second reminder is due.
        $assessment->update(['overdue_reminder_sent_at' => now()->subDays(3)]);

        $this->artisan('fees:send-reminders')->assertExitCode(0);
        Notification::assertSentToTimes($parent, FeeOverdueNotification::class, 2);
    }

    public function test_a_fully_paid_assessment_is_never_reminded(): void
    {
        Notification::fake();

        $school = $this->makeSchool();
        $academicYear = $this->makeAcademicYear($school);
        $student = $this->makeRoleUser('student', $school);
        $category = $this->makeFeeCategory($school);
        $parent = $this->makeRoleUser('parent', $school);
        $this->makeStudentParentLink($school, $parent, $student);
        $this->makeFeeAssessment($school, $academicYear, $student, $category, [
            'base_amount' => '1000.00',
            'due_date' => now()->subDays(10)->toDateString(),
            'status' => 'paid',
        ]);

        $this->artisan('fees:send-reminders')->assertExitCode(0);

        Notification::assertNothingSentTo($parent);
    }
}
