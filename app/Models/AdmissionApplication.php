<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Design ref: 19-discovery-hierarchy-gap-closure-plan.md §10, discovery
 * doc §12.
 * Decision ref: 16-schoolos-decisions-register.md D16.
 *
 * The one row per prospective applicant, from submission through
 * decision. `applicant_data` carries name/DOB/parent-guardian/medical
 * info as JSON, not a `users` FK — a prospective applicant isn't a User
 * yet (see AdmissionApplicationRepository::accept()'s doc comment for
 * the conversion). `resulting_user_id`/`resulting_student_enrollment_id`
 * stay null until (and unless) accept() runs.
 */
class AdmissionApplication extends Model
{
    use HasFactory;

    protected $fillable = [
        'school_id',
        'status',
        'applicant_data',
        'fee_category_acknowledgments',
        'documents',
        'submitted_at',
        'decided_by',
        'decided_at',
        'decision_reason',
        'resulting_user_id',
        'resulting_student_enrollment_id',
    ];

    protected function casts(): array
    {
        return [
            'applicant_data' => 'array',
            'fee_category_acknowledgments' => 'array',
            'documents' => 'array',
            'submitted_at' => 'datetime',
            'decided_at' => 'datetime',
        ];
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function decidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }

    public function resultingUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resulting_user_id');
    }

    public function resultingStudentEnrollment(): BelongsTo
    {
        return $this->belongsTo(StudentEnrollment::class, 'resulting_student_enrollment_id');
    }
}
