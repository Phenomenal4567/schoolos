<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
 * Backup & Data Management (19-discovery-hierarchy-gap-closure-plan.md
 * §11, 20-phase-6-8-execution-prompt.md §2). Decision ref: 16-schoolos-
 * decisions-register.md D11.
 *
 * exports:run-scheduled is the "automatic" trigger (creates+dispatches
 * a term export for any school whose term ends today) — daily is
 * sufficient since it only needs to catch each end_date once.
 * exports:prune-expired enforces D11's 7-day retention window — also
 * daily, since sub-day precision on expiry isn't a product requirement
 * here.
 */
Schedule::command('exports:run-scheduled')->daily();
Schedule::command('exports:prune-expired')->daily();

/*
 * Fee / Debt Notifications (26-discovery-hierarchy-status.md). Daily is
 * sufficient — SendFeeReminders matches assessments by calendar date
 * (due_date - today), so sub-day precision isn't a product requirement
 * here either, same reasoning as the export commands above.
 */
Schedule::command('fees:send-reminders')->daily();
