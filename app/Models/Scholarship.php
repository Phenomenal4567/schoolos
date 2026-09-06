<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Design ref: 22-schoolos-finance-schema.md §3 (discovery §10.5)
 *
 * Enrollment/session-scoped policy: "this student has a scholarship for
 * this academic year," not a per-assessment fact. Written by
 * ScholarshipRepository::grant() (an admin action, independent of any
 * specific fee_assessment), then looked up by
 * FeeAssessmentRepository::assess() when a new assessment is created for
 * that student/year to compute the assessment's scholarship_amount
 * snapshot.
 */
class Scholarship extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'school_id',
        'student_id',
        'academic_year_id',
        'type',
        'value',
        'fee_category_id',
        'granted_by',
        'reason',
    ];

    protected function casts(): array
    {
        return [
            'value' => 'decimal:2',
            'created_at' => 'datetime',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function feeCategory(): BelongsTo
    {
        return $this->belongsTo(FeeCategory::class);
    }

    public function grantedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'granted_by');
    }
}
