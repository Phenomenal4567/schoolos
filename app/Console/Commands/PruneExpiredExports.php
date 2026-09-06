<?php

namespace App\Console\Commands;

use App\Models\ExportJob;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Design ref: 19-discovery-hierarchy-gap-closure-plan.md §11,
 * 20-phase-6-8-execution-prompt.md §2.
 * Decision ref: 16-schoolos-decisions-register.md D11 item 2 — 7-day
 * retention, no renewal path.
 *
 * Deletes the physical file on the 'local' disk for any ExportJob whose
 * expires_at has passed, and clears file_path. Deliberately does not
 * introduce a new `status` enum value (e.g. 'expired') — the
 * export_jobs.status column stays 'completed' for the historical
 * record; a null file_path combined with an expires_at in the past is
 * what Admin\ExportJobController::download() checks to refuse a
 * download and point the admin at re-requesting a fresh export, per
 * D11's "no renewal path" line. Rows are never deleted here — only the
 * file. The request record (who asked for what date range, when) stays
 * for audit purposes.
 *
 * Scheduled daily in routes/console.php. Also safe to run manually
 * (php artisan exports:prune-expired) or in a test.
 */
class PruneExpiredExports extends Command
{
    protected $signature = 'exports:prune-expired';

    protected $description = 'Delete files for expired export jobs, per the 7-day retention window (16-schoolos-decisions-register.md D11).';

    public function handle(): int
    {
        $expired = ExportJob::query()
            ->whereNotNull('file_path')
            ->where('expires_at', '<=', now())
            ->get();

        $count = 0;

        foreach ($expired as $job) {
            if (Storage::disk('local')->exists($job->file_path)) {
                Storage::disk('local')->delete($job->file_path);
            }

            $job->update(['file_path' => null]);
            $count++;
        }

        $this->info("Pruned {$count} expired export file(s).");

        return self::SUCCESS;
    }
}
