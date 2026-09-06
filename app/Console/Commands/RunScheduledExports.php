<?php

namespace App\Console\Commands;

use App\Jobs\ProcessExportJob;
use App\Models\AcademicTerm;
use App\Models\Role;
use App\Models\User;
use App\Repositories\ExportJobRepository;
use Illuminate\Console\Command;

/**
 * Design ref: 19-discovery-hierarchy-gap-closure-plan.md §11 ("likely a
 * scheduled export job (per school, per term/session)"),
 * 20-phase-6-8-execution-prompt.md §2.
 * Decision ref: 16-schoolos-decisions-register.md D11.
 *
 * The "automatic" half of the two triggers this track's test gate
 * names (the other is Admin\ExportJobController::store(), "manual").
 * Runs daily (see routes/console.php) and, for every academic_term
 * whose end_date is today, creates a range_type = 'term' ExportJob for
 * that term's school through the same ExportJobRepository::create()
 * a manual request goes through, then dispatches the same
 * Jobs\ProcessExportJob — see ExportFileGenerator's doc comment for why
 * that shared path is what guarantees identical output between the two
 * triggers, rather than a second, separately-maintained generation
 * routine.
 *
 * requested_by: export_jobs.requested_by is a NOT NULL FK to users (see
 * the 2026_08_30_000001 migration) — there is no "system" user concept
 * in this schema, so an automatic export is attributed to the first
 * active school_admin found for that school, on that school's behalf.
 * A school with no active school_admin is skipped and logged, not
 * failed — there is no reasonable actor to attribute the row to, and
 * that school has no one who could act on the request record anyway
 * (D11's access decision scopes downloads to school_admins of that
 * school).
 */
class RunScheduledExports extends Command
{
    protected $signature = 'exports:run-scheduled';

    protected $description = 'Automatically create and dispatch a term export for every academic term ending today (16-schoolos-decisions-register.md D11).';

    public function handle(ExportJobRepository $repository): int
    {
        $schoolAdminRoleId = Role::where('key', 'school_admin')->value('id');

        $terms = AcademicTerm::whereDate('end_date', now()->toDateString())->get();

        $created = 0;

        foreach ($terms as $term) {
            $admin = User::where('school_id', $term->school_id)
                ->where('role_id', $schoolAdminRoleId)
                ->where('status', 'active')
                ->orderBy('id')
                ->first();

            if ($admin === null) {
                $this->warn("Skipping automatic export for school #{$term->school_id}: no active school_admin found.");

                continue;
            }

            $job = $repository->create($term->school_id, $admin, [
                'range_type' => 'term',
                'academic_term_id' => $term->id,
            ]);

            ProcessExportJob::dispatch($job->id);
            $created++;
        }

        $this->info("Dispatched {$created} automatic term export(s).");

        return self::SUCCESS;
    }
}
