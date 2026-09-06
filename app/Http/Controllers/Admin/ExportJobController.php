<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessExportJob;
use App\Models\ExportJob;
use App\Repositories\ExportJobRepository;
use App\Services\ScopeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Design ref: 19-discovery-hierarchy-gap-closure-plan.md §11,
 * 20-phase-6-8-execution-prompt.md §2.
 * Decision ref: 16-schoolos-decisions-register.md D11.
 *
 * store() is the "manual" trigger this track's test gate names — it
 * records the request (ExportJobRepository::create()) and dispatches
 * Jobs\ProcessExportJob, the same job class Console\Commands\
 * RunScheduledExports (the "automatic" trigger) dispatches. No
 * index/show here, matching this bundle's admin write-path-focused
 * convention (see Admin\CalendarEventController's doc comment) — an
 * admin listing UI still isn't part of this pass.
 *
 * download() resolves {exportJob} through ScopeService::tenantScope(),
 * not a requested_by check — per D11 item 3, any school_admin at the
 * export's school may download it, not only the admin who requested
 * it. A completed, non-expired file streams back; anything else (still
 * queued/processing, failed, or past its 7-day window and already
 * pruned by exports:prune-expired) 404s with a message pointing at
 * re-requesting rather than a raw exception, since "submit a new
 * export" is the only recovery path D11 defines — there is no renewal.
 */
class ExportJobController extends Controller
{
    public function store(Request $request, ExportJobRepository $repository): RedirectResponse
    {
        $actor = $request->user();
        $data = $this->validated($request);

        try {
            $job = $repository->create($actor->school_id, $actor, $data);
        } catch (\InvalidArgumentException $e) {
            return back()->withErrors(['export_job' => $e->getMessage()])->withInput();
        }

        ProcessExportJob::dispatch($job->id);

        return back()->with(
            'status',
            'Export requested. It will be available to download once processing finishes, '
            . 'and stays available for 7 days.'
        );
    }

    public function download(Request $request, ScopeService $scope, int $exportJob): StreamedResponse
    {
        $actor = $request->user();
        $job = $scope->tenantScope(ExportJob::query(), $actor)->findOrFail($exportJob);

        if ($job->status !== 'completed' || $job->file_path === null || !Storage::disk('local')->exists($job->file_path)) {
            abort(404, 'This export is not available to download — it may still be processing, may have failed, '
                . 'or may have passed its 7-day retention window. Please request a new export.');
        }

        return Storage::disk('local')->download(
            $job->file_path,
            "export-{$job->school_id}-{$job->id}.zip"
        );
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'range_type' => ['required', 'string', 'in:term,session,custom'],
            'academic_term_id' => ['nullable', 'integer'],
            'academic_year_id' => ['nullable', 'integer'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date'],
        ]);
    }
}
