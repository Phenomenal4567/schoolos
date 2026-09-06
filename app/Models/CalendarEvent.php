<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Design ref: 19-discovery-hierarchy-gap-closure-plan.md §3,
 * 20-phase-6-8-execution-prompt.md §1.
 *
 * school_id is the only column ScopeService::tenantScope() needs; there
 * is deliberately no class_section_id/student_id here, so
 * ScopeService::relationshipScope() is never called for this model — a
 * calendar event is visible to every in-tenant parent/student uniformly
 * once tenantScope() and visible_to_parents both pass, not narrowed by
 * section or child (19 §3).
 *
 * Nothing here is written outside CalendarEventRepository's
 * create()/update() (this bundle's "one repository method owns a
 * resource's creation/mutation" shape, matching AnnouncementRepository),
 * so mass-assignment guarding isn't load-bearing — listed anyway for
 * consistency with every other model in this bundle.
 */
class CalendarEvent extends Model
{
    use HasFactory;

    protected $fillable = [
        'school_id',
        'academic_year_id',
        'academic_term_id',
        'title',
        'event_type',
        'start_date',
        'end_date',
        'visible_to_parents',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'visible_to_parents' => 'boolean',
        ];
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function academicTerm(): BelongsTo
    {
        return $this->belongsTo(AcademicTerm::class);
    }
}
