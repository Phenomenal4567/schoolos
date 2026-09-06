<?php

namespace App\Jobs;

use App\Models\ExportJob;
use App\Services\ExportFileGenerator;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Design ref: 19-discovery-hierarchy-gap-closure-plan.md §11,
 * 20-phase-6-8-execution-prompt.md §2.
 * Decision ref: 16-schoolos-decisions-register.md D11.
 *
 * Deliberately takes an int id, not the ExportJob model — standard
 * queued-job practice (re-fetches fresh state when the worker actually
 * runs, rather than serializing a potentially-stale model instance into
 * the queue payload). Dispatched from exactly two call sites:
 * Admin\ExportJobController::store() (manual) and
 * Console\Commands\RunScheduledExports (automatic) — see
 * ExportFileGenerator's doc comment for why routing both through this
 * one job class is what makes the test gate's "identical output"
 * requirement hold structurally, not by convention.
 */
class ProcessExportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public int $exportJobId)
    {
    }

    public function handle(ExportFileGenerator $generator): void
    {
        $job = ExportJob::find($this->exportJobId);

        if ($job === null) {
            // Row was deleted out from under us between dispatch and
            // execution — nothing to do, and nothing to retry.
            return;
        }

        $generator->generate($job);
    }
}
