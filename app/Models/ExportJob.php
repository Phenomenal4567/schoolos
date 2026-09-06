<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Design ref: 19-discovery-hierarchy-gap-closure-plan.md §11,
 * 20-phase-6-8-execution-prompt.md §2.
 * Decision ref: 16-schoolos-decisions-register.md D11.
 *
 * Tracks a requested export (per-term, per-session, or custom date
 * range). create() (ExportJobRepository) only records the request;
 * Services\ExportFileGenerator (run via Jobs\ProcessExportJob) is what
 * writes the file and sets file_path/expires_at/status.
 *
 * Download access (D11 item 3) is tenant-scoped, not requester-scoped
 * — any school_admin at this row's school can download it, not only
 * the admin in requested_by. See Admin\ExportJobController::download().
 *
 * school_id is the only column ScopeService::tenantScope() needs;
 * there is no class_section_id/student_id here, so
 * ScopeService::relationshipScope() is never called for this model —
 * matches CalendarEvent's own reasoning for the same omission. Access
 * to this resource is admin-only in the first place (school_admin
 * requests exports of their own school's data), so there's no
 * parent/student/teacher visibility question here at all, unlike
 * CalendarEvent.
 *
 * Nothing here is written outside ExportJobRepository::create() (this
 * bundle's "one repository method owns a resource's creation" shape),
 * so mass-assignment guarding isn't load-bearing — listed anyway for
 * consistency with every other model in this bundle.
 */
class ExportJob extends Model
{
    use HasFactory;

    protected $fillable = [
        'school_id',
        'requested_by',
        'range_type',
        'academic_term_id',
        'academic_year_id',
        'start_date',
        'end_date',
        'status',
        'file_path',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'expires_at' => 'datetime',
        ];
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function academicTerm(): BelongsTo
    {
        return $this->belongsTo(AcademicTerm::class);
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }
}
