<?php

namespace App\Console\Commands;

use App\Models\FeeAssessment;
use App\Models\StudentParentLink;
use App\Models\User;
use App\Notifications\FeeDeadlineUpcomingNotification;
use App\Notifications\FeeOverdueNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Notification;

/**
 * Design ref: 26-discovery-hierarchy-status.md ("Fee / Debt Notifications").
 *
 * The date-driven half of fee/debt notifications — FeeAssessed and
 * PaymentConfirmed (see those events) fire from a specific write, but
 * "this is now overdue" and "this is due soon" are true because a day
 * passed, not because anyone acted, so they need their own daily sweep,
 * the same shape as exports:run-scheduled.
 *
 * Upcoming-deadline reminders fire once, three days before due_date
 * (deadline_reminder_sent_at is a one-shot flag, never cleared).
 * Overdue reminders repeat every schools.fee_overdue_reminder_days days
 * (default 7, editable per school by that school's own school_admin
 * through Admin\SchoolProfileController) for as long as a balance
 * remains, so a parent who misses the first reminder keeps hearing
 * about it rather than going silent after one notification. Both
 * columns live on fee_assessments (see that migration) so this command
 * is the one write path for them, matching FeeAssessment's existing
 * cached-column discipline.
 */
class SendFeeReminders extends Command
{
    protected $signature = 'fees:send-reminders';

    protected $description = 'Notify parents of fee assessments approaching their due date or already overdue (26-discovery-hierarchy-status.md).';

    private const DEADLINE_WARNING_DAYS = 3;

    // Fallback only — every school has its own fee_overdue_reminder_days
    // column (NOT NULL, defaulted at the migration), so this is never
    // actually reached in practice.
    private const DEFAULT_OVERDUE_REPEAT_DAYS = 7;

    public function handle(): int
    {
        $upcoming = $this->sendUpcomingDeadlineReminders();
        $overdue = $this->sendOverdueReminders();

        $this->info("Sent {$upcoming} upcoming-deadline reminder(s) and {$overdue} overdue reminder(s).");

        return self::SUCCESS;
    }

    private function sendUpcomingDeadlineReminders(): int
    {
        $targetDate = now()->addDays(self::DEADLINE_WARNING_DAYS)->toDateString();

        $assessments = FeeAssessment::whereDate('due_date', $targetDate)
            ->whereNull('deadline_reminder_sent_at')
            ->whereNotIn('status', ['paid', 'void'])
            ->get();

        $sent = 0;

        foreach ($assessments as $assessment) {
            if (bccomp($assessment->amountRemaining(), '0', 2) <= 0) {
                continue;
            }

            if ($this->notifyParents($assessment, new FeeDeadlineUpcomingNotification($assessment))) {
                $sent++;
            }

            $assessment->update(['deadline_reminder_sent_at' => now()]);
        }

        return $sent;
    }

    /**
     * The repeat cadence is per-school (schools.fee_overdue_reminder_days),
     * so it can't be pushed into the SQL date filter the way
     * sendUpcomingDeadlineReminders()'s fixed 3-day window can. Instead
     * the query over-fetches (anything never reminded, or reminded more
     * than a day ago — the minimum any school can configure) and the
     * exact per-school cooldown check happens in PHP below.
     */
    private function sendOverdueReminders(): int
    {
        $assessments = FeeAssessment::with('school')
            ->whereDate('due_date', '<', now()->toDateString())
            ->whereNotIn('status', ['paid', 'void'])
            ->where(function ($query) {
                $query->whereNull('overdue_reminder_sent_at')
                    ->orWhereDate('overdue_reminder_sent_at', '<=', now()->subDay()->toDateString());
            })
            ->get();

        $sent = 0;

        foreach ($assessments as $assessment) {
            $repeatDays = $assessment->school->fee_overdue_reminder_days ?? self::DEFAULT_OVERDUE_REPEAT_DAYS;

            if ($assessment->overdue_reminder_sent_at !== null
                && $assessment->overdue_reminder_sent_at->gt(now()->subDays($repeatDays))) {
                continue;
            }

            if (bccomp($assessment->amountRemaining(), '0', 2) <= 0) {
                continue;
            }

            if ($this->notifyParents($assessment, new FeeOverdueNotification($assessment))) {
                $sent++;
            }

            $assessment->update(['overdue_reminder_sent_at' => now()]);
        }

        return $sent;
    }

    private function notifyParents(FeeAssessment $assessment, $notification): bool
    {
        $parentIds = StudentParentLink::where('student_id', $assessment->student_id)
            ->where('status', 'active')
            ->pluck('parent_id');

        if ($parentIds->isEmpty()) {
            return false;
        }

        $parents = User::whereIn('id', $parentIds)->get();

        Notification::send($parents, $notification);

        return true;
    }
}
