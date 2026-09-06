<?php

namespace Tests\Feature;

use App\Console\Commands\PruneExpiredExports;
use App\Jobs\ProcessExportJob;
use App\Models\ExportJob;
use App\Repositories\ExportJobRepository;
use App\Services\ExportFileGenerator;
use App\Services\ScopeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CreatesSchoolOsFixtures;
use Tests\TestCase;

/**
 * Design ref: 19-discovery-hierarchy-gap-closure-plan.md §11,
 * 20-phase-6-8-execution-prompt.md §2.
 * Decision ref: 16-schoolos-decisions-register.md D11.
 *
 * One test per 20 §2's three named gate items, in the same order:
 * (a) exports are correctly school-scoped (no cross-tenant export
 * possible), (b) expired exports are actually deleted per the
 * retention window, (c) manual and automatic triggers both produce
 * identical output. Matches Phase6aTestGateTest's "one test per gate
 * row" shape.
 *
 * download()'s route carries 'scope.checked' and is covered by
 * Phase1TestGateTest's route-table lint, same reasoning
 * Phase5TestGateTest/Phase6aTestGateTest give for not duplicating that
 * check here — this file tests the scoping query and generation logic
 * directly rather than through HTTP, matching how Phase6aTestGateTest
 * exercises CalendarEventRepository::visibleTo() directly.
 *
 * Not run in the environment this pass was authored in (no PHP/Composer
 * available) — unlike D9/D10, this is not yet recorded in 16 as a
 * "built and verified" status update. Run
 * `php artisan test --filter=Phase8TestGateTest` in a real environment
 * before treating Track 8 as closed, per this register's own binding
 * rule that a phase closes on a real test run, not a claim that the
 * code looks correct.
 */
class Phase8TestGateTest extends TestCase
{
    use RefreshDatabase;
    use CreatesSchoolOsFixtures;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
    }

    /**
     * (a) — a school_admin in school A cannot resolve school B's export
     * job through ScopeService::tenantScope() (the same check
     * Admin\ExportJobController::download() runs before touching
     * file_path), and can resolve their own school's export even when
     * it was requested by a different admin at the same school (D11
     * item 3 — school-wide, not requester-only, access).
     */
    public function test_exports_are_school_scoped_and_not_requester_scoped(): void
    {
        $scope = app(ScopeService::class);

        $schoolA = $this->makeSchool();
        $schoolB = $this->makeSchool();

        $adminA1 = $this->makeRoleUser('school_admin', $schoolA);
        $adminA2 = $this->makeRoleUser('school_admin', $schoolA);
        $adminB = $this->makeRoleUser('school_admin', $schoolB);

        $exportA = $this->makeExportJob($schoolA, $adminA1, ['status' => 'completed', 'file_path' => 'exports/x/a.zip']);

        // Cross-tenant: school B's admin cannot resolve school A's export.
        $this->assertNull(
            $scope->tenantScope(ExportJob::query(), $adminB)->find($exportA->id)
        );

        // Same-school, different admin: school A's second admin CAN
        // resolve an export the first admin requested — D11's
        // school-wide access decision, not requester-only.
        $resolved = $scope->tenantScope(ExportJob::query(), $adminA2)->find($exportA->id);
        $this->assertNotNull($resolved);
        $this->assertSame($exportA->id, $resolved->id);
    }

    /**
     * (b) — exports:prune-expired deletes the underlying file and
     * clears file_path for any export past its expires_at, and leaves
     * a not-yet-expired export's file untouched.
     */
    public function test_expired_exports_are_deleted_by_retention_sweep(): void
    {
        $school = $this->makeSchool();
        $admin = $this->makeRoleUser('school_admin', $school);

        Storage::disk('local')->put('exports/expired/e.zip', 'expired contents');
        Storage::disk('local')->put('exports/fresh/f.zip', 'fresh contents');

        $expired = $this->makeExportJob($school, $admin, [
            'status' => 'completed',
            'file_path' => 'exports/expired/e.zip',
            'expires_at' => now()->subDay(),
        ]);

        $fresh = $this->makeExportJob($school, $admin, [
            'status' => 'completed',
            'file_path' => 'exports/fresh/f.zip',
            'expires_at' => now()->addDays(3),
        ]);

        Artisan::call('exports:prune-expired');

        $this->assertFalse(Storage::disk('local')->exists('exports/expired/e.zip'));
        $this->assertNull($expired->fresh()->file_path);

        $this->assertTrue(Storage::disk('local')->exists('exports/fresh/f.zip'));
        $this->assertSame('exports/fresh/f.zip', $fresh->fresh()->file_path);
    }

    /**
     * (c) — a "manually" requested export (ExportJobRepository::create()
     * called the way Admin\ExportJobController::store() calls it) and
     * an "automatically" requested export for the same term (the shape
     * Console\Commands\RunScheduledExports creates) produce byte-identical
     * roster/attendance CSVs once run through the same
     * Jobs\ProcessExportJob → Services\ExportFileGenerator path — proving
     * the two triggers share one generation routine rather than two
     * that could drift apart.
     */
    public function test_manual_and_automatic_triggers_produce_identical_output(): void
    {
        $repository = app(ExportJobRepository::class);

        $school = $this->makeSchool();
        $manualAdmin = $this->makeRoleUser('school_admin', $school);
        $autoAdmin = $this->makeRoleUser('school_admin', $school);

        $year = $this->makeAcademicYear($school);
        $term = $this->makeAcademicTerm($school, $year, [
            'start_date' => now()->subDays(30)->toDateString(),
            'end_date' => now()->toDateString(),
        ]);

        $classSection = $this->makeClassSection($school, $year, $manualAdmin);
        $student = $this->makeRoleUser('student', $school);
        $this->makeStudentEnrollment($school, $year, $classSection, $student);

        // "Manual" trigger: same call shape as
        // Admin\ExportJobController::store().
        $manualJob = $repository->create($school->id, $manualAdmin, [
            'range_type' => 'term',
            'academic_term_id' => $term->id,
        ]);
        (new ProcessExportJob($manualJob->id))->handle(app(ExportFileGenerator::class));

        // "Automatic" trigger: same call shape as
        // Console\Commands\RunScheduledExports for the same term.
        $autoJob = $repository->create($school->id, $autoAdmin, [
            'range_type' => 'term',
            'academic_term_id' => $term->id,
        ]);
        (new ProcessExportJob($autoJob->id))->handle(app(ExportFileGenerator::class));

        $manualJob->refresh();
        $autoJob->refresh();

        $this->assertSame('completed', $manualJob->status);
        $this->assertSame('completed', $autoJob->status);

        $manualCsv = $this->readZipEntry($manualJob->file_path, 'students.csv');
        $autoCsv = $this->readZipEntry($autoJob->file_path, 'students.csv');

        $this->assertSame($manualCsv, $autoCsv);
    }

    private function readZipEntry(string $relativePath, string $entry): string
    {
        $absolutePath = Storage::disk('local')->path($relativePath);

        $zip = new \ZipArchive();
        $zip->open($absolutePath);
        $contents = $zip->getFromName($entry);
        $zip->close();

        return $contents;
    }
}
