<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Design ref: 26-discovery-hierarchy-status.md ("Subject Attendance" gap)
 *
 * The subject-level analogue of AttendanceCorrection — see that model's
 * doc comment. Append-only for the same reason: no `updated_at`, no
 * soft-delete, no factory — always a side effect of
 * SubjectAttendanceRepository::mark(), never constructed standalone.
 */
class SubjectAttendanceCorrection extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'subject_attendance_record_id',
        'previous_status',
        'new_status',
        'corrected_by',
        'corrected_at',
        'reason',
    ];

    protected function casts(): array
    {
        return [
            'previous_status' => 'string',
            'new_status' => 'string',
            'corrected_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    public function subjectAttendanceRecord(): BelongsTo
    {
        return $this->belongsTo(SubjectAttendanceRecord::class);
    }

    public function correctedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'corrected_by');
    }
}
