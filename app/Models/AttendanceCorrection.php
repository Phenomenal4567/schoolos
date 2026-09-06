<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Design ref: 13-schoolos-database-schema-v2.md §5
 *
 * 09-attendance-map.md §4: GegoK12 has no correction capability at all.
 * This table is the dedicated, typed correction history for a single
 * attendance_records row — distinct from the generic audit_logs entry
 * AttendanceRepository::mark() also writes (see that class's doc
 * comment for why both exist).
 *
 * Append-only, same discipline as AuditLog: no `updated_at`, no
 * soft-delete, no update() call anywhere against an existing row — a
 * correction is a fact about what changed, not a record that itself
 * gets corrected. No HasFactory/factory() for the same reason AuditLog
 * has none — a correction row is always a side effect of
 * AttendanceRepository::mark(), never constructed standalone.
 */
class AttendanceCorrection extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'attendance_record_id',
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

    public function attendanceRecord(): BelongsTo
    {
        return $this->belongsTo(AttendanceRecord::class);
    }

    public function correctedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'corrected_by');
    }
}
