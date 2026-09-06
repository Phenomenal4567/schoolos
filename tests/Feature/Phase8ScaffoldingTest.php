<?php

namespace Tests\Feature;

use App\Repositories\ExportJobRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesSchoolOsFixtures;
use Tests\TestCase;

/**
 * Design ref: 19-discovery-hierarchy-gap-closure-plan.md §11,
 * 20-phase-6-8-execution-prompt.md §2.
 *
 * NOT the full Track 8 test gate. 19 §11's gate has three items:
 * (a) exports are correctly school-scoped, (b) expired exports are
 * actually deleted per the retention window, (c) manual and automatic
 * triggers produce identical output. (b) and (c) both require the job
 * logic that's blocked on the storage/retention decision — this class
 * covers only what's buildable right now, the create() write path's
 * own scoping and validation, and is deliberately named
 * "Scaffolding", not "TestGate", so it isn't mistaken for a closed
 * Track 8 in a future `16` register entry. Do not mark Track 8 closed
 * off the strength of this file passing.
 */
class Phase8ScaffoldingTest extends TestCase
{
    use RefreshDatabase;
    use CreatesSchoolOsFixtures;

    /**
     * Partial coverage of gate item (a): a school_admin can only
     * request an export against their own school's academic_year/
     * academic_term — a cross-tenant id is rejected, not silently
     * reassigned to the requester's school.
     */
    public function test_export_job_creation_is_school_scoped(): void
    {
        $repository = app(ExportJobRepository::class);

        $schoolA = $this->makeSchool();
        $schoolB = $this->makeSchool();

        $yearB = $this->makeAcademicYear($schoolB);
        $adminA = $this->makeRoleUser('school_admin', $schoolA);

        $this->expectException(\InvalidArgumentException::class);

        $repository->create($schoolA->id, $adminA, [
            'range_type' => 'session',
            'academic_year_id' => $yearB->id,
        ]);
    }

    public function test_export_job_creation_rejects_cross_tenant_term(): void
    {
        $repository = app(ExportJobRepository::class);

        $schoolA = $this->makeSchool();
        $schoolB = $this->makeSchool();

        $yearB = $this->makeAcademicYear($schoolB);
        $termB = $this->makeAcademicTerm($schoolB, $yearB);
        $adminA = $this->makeRoleUser('school_admin', $schoolA);

        $this->expectException(\InvalidArgumentException::class);

        $repository->create($schoolA->id, $adminA, [
            'range_type' => 'term',
            'academic_term_id' => $termB->id,
        ]);
    }

    /**
     * Only school_admin may request an export — matches
     * CalendarEventRepository's own actor-gating precedent for an
     * admin-authored resource.
     */
    public function test_only_school_admin_may_request_an_export(): void
    {
        $repository = app(ExportJobRepository::class);

        $school = $this->makeSchool();
        $year = $this->makeAcademicYear($school);
        $teacher = $this->makeRoleUser('teacher', $school);

        $this->expectException(\InvalidArgumentException::class);

        $repository->create($school->id, $teacher, [
            'range_type' => 'session',
            'academic_year_id' => $year->id,
        ]);
    }

    /**
     * All three range_type shapes are accepted for a same-tenant
     * request, and each created row lands at status = 'queued' with
     * file_path/expires_at both null — documenting, not just asserting
     * in a doc comment, that create() performs no job logic.
     */
    public function test_valid_same_tenant_requests_are_recorded_as_queued_with_no_file_or_expiry(): void
    {
        $repository = app(ExportJobRepository::class);

        $school = $this->makeSchool();
        $year = $this->makeAcademicYear($school);
        $term = $this->makeAcademicTerm($school, $year);
        $admin = $this->makeRoleUser('school_admin', $school);

        $sessionJob = $repository->create($school->id, $admin, [
            'range_type' => 'session',
            'academic_year_id' => $year->id,
        ]);

        $termJob = $repository->create($school->id, $admin, [
            'range_type' => 'term',
            'academic_term_id' => $term->id,
        ]);

        $customJob = $repository->create($school->id, $admin, [
            'range_type' => 'custom',
            'start_date' => '2026-01-01',
            'end_date' => '2026-01-31',
        ]);

        foreach ([$sessionJob, $termJob, $customJob] as $job) {
            $this->assertSame('queued', $job->status);
            $this->assertNull($job->file_path);
            $this->assertNull($job->expires_at);
            $this->assertSame($school->id, $job->school_id);
            $this->assertSame($admin->id, $job->requested_by);
        }
    }

    /**
     * range_type = 'custom' requires both dates and a non-inverted
     * range, matching CalendarEventRepository's end_date-before-
     * start_date guard.
     */
    public function test_custom_range_requires_valid_dates(): void
    {
        $repository = app(ExportJobRepository::class);

        $school = $this->makeSchool();
        $admin = $this->makeRoleUser('school_admin', $school);

        $this->expectException(\InvalidArgumentException::class);

        $repository->create($school->id, $admin, [
            'range_type' => 'custom',
            'start_date' => '2026-02-01',
            'end_date' => '2026-01-01',
        ]);
    }
}
