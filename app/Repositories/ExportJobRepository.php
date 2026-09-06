<?php

namespace App\Repositories;

use App\Models\AcademicTerm;
use App\Models\AcademicYear;
use App\Models\ExportJob;
use App\Models\User;

/**
 * Design ref: 19-discovery-hierarchy-gap-closure-plan.md §11,
 * 20-phase-6-8-execution-prompt.md §2.
 * Decision ref: 16-schoolos-decisions-register.md D11 — storage,
 * retention, and access are closed; the working export logic is built.
 *
 * What this class does: records that an export was requested
 * (create()), school_admin-only, school-scoped, validated against the
 * requesting school's own academic_terms/academic_years. It still
 * deliberately does not write a file or set file_path/expires_at
 * itself — that's Services\ExportFileGenerator's job, run by
 * Jobs\ProcessExportJob after create() returns (see
 * Admin\ExportJobController::store() and
 * Console\Commands\RunScheduledExports for the two call sites that
 * dispatch it). Every row this creates starts at status = 'queued';
 * the job transitions it to 'processing' → 'completed'/'failed'.
 *
 * expires_at is left null by create() on purpose — it's computed at
 * completion time in ExportFileGenerator (created_at + 7 days from
 * when the file actually exists, not from when it was requested), per
 * D11's item 2.
 */
class ExportJobRepository
{
    private const RANGE_TYPES = ['term', 'session', 'custom'];

    /**
     * @param  array{
     *     range_type: string,
     *     academic_term_id?: int|null,
     *     academic_year_id?: int|null,
     *     start_date?: string|null,
     *     end_date?: string|null,
     * }  $data
     *
     * @throws \InvalidArgumentException if $actor isn't 'school_admin',
     *         if range_type isn't one of self::RANGE_TYPES, if
     *         range_type = 'term' without an academic_term_id belonging
     *         to $schoolId, if range_type = 'session' without an
     *         academic_year_id belonging to $schoolId, or if
     *         range_type = 'custom' without both start_date and
     *         end_date (with end_date not before start_date).
     */
    public function create(int $schoolId, User $actor, array $data): ExportJob
    {
        $this->assertActorMayRequest($actor);
        $this->assertValidShape($schoolId, $data);

        return ExportJob::create([
            'school_id' => $schoolId,
            'requested_by' => $actor->id,
            'range_type' => $data['range_type'],
            'academic_term_id' => $data['academic_term_id'] ?? null,
            'academic_year_id' => $data['academic_year_id'] ?? null,
            'start_date' => $data['start_date'] ?? null,
            'end_date' => $data['end_date'] ?? null,
            'status' => 'queued',
            'file_path' => null,
            'expires_at' => null,
        ]);
    }

    private function assertActorMayRequest(User $actor): void
    {
        $roleKey = $actor->role->key ?? null;

        if ($roleKey !== 'school_admin') {
            throw new \InvalidArgumentException(
                "ExportJobRepository: role '{$roleKey}' may not request exports."
            );
        }
    }

    private function assertValidShape(int $schoolId, array $data): void
    {
        if (! in_array($data['range_type'], self::RANGE_TYPES, true)) {
            throw new \InvalidArgumentException(
                "ExportJobRepository: invalid range_type '{$data['range_type']}'."
            );
        }

        if ($data['range_type'] === 'term') {
            if (empty($data['academic_term_id'])) {
                throw new \InvalidArgumentException(
                    "ExportJobRepository: range_type 'term' requires academic_term_id."
                );
            }

            $term = AcademicTerm::findOrFail($data['academic_term_id']);

            if ($term->school_id !== $schoolId) {
                throw new \InvalidArgumentException(
                    "ExportJobRepository: academic_term #{$term->id} belongs to school "
                    . "#{$term->school_id}, not the requested school #{$schoolId}."
                );
            }
        }

        if ($data['range_type'] === 'session') {
            if (empty($data['academic_year_id'])) {
                throw new \InvalidArgumentException(
                    "ExportJobRepository: range_type 'session' requires academic_year_id."
                );
            }

            $year = AcademicYear::findOrFail($data['academic_year_id']);

            if ($year->school_id !== $schoolId) {
                throw new \InvalidArgumentException(
                    "ExportJobRepository: academic_year #{$year->id} belongs to school "
                    . "#{$year->school_id}, not the requested school #{$schoolId}."
                );
            }
        }

        if ($data['range_type'] === 'custom') {
            if (empty($data['start_date']) || empty($data['end_date'])) {
                throw new \InvalidArgumentException(
                    "ExportJobRepository: range_type 'custom' requires both start_date and end_date."
                );
            }

            if ($data['end_date'] < $data['start_date']) {
                throw new \InvalidArgumentException(
                    'ExportJobRepository: end_date must not fall before start_date.'
                );
            }
        }
    }
}
