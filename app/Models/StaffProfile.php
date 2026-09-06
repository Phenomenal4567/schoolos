<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Design ref: 26-discovery-hierarchy-status.md ("Rich Teacher/Staff
 * Enrollment And Profiles").
 *
 * See the 2026_09_03_020000 migration's doc comment for why this is a
 * peer table to `users` (one row per staff user_id) rather than more
 * columns on that shared table. Written by StaffProfileRepository — its
 * own doc comment covers the write shape.
 */
class StaffProfile extends Model
{
    protected $fillable = [
        'school_id',
        'user_id',
        'qualifications',
        'responsibilities',
        'cv_path',
        'rules_acknowledged_at',
        'can_manage_timetable',
    ];

    protected function casts(): array
    {
        return [
            'qualifications' => 'array',
            'rules_acknowledged_at' => 'datetime',
            'can_manage_timetable' => 'boolean',
        ];
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
