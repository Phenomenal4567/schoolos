<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Design ref: 26-discovery-hierarchy-status.md ("Rich Teacher/Staff
 * Enrollment And Profiles").
 *
 * See the 2026_09_03_020001 migration's doc comment for why this is a
 * separate many-per-staff table rather than a column on StaffProfile.
 * file_path is on the 'local' (private) disk, same as
 * Admin\LessonPlanController's document uploads — never a
 * directly-servable public URL, per StaffProfileRepository's own doc
 * comment.
 */
class StaffDocument extends Model
{
    protected $fillable = [
        'school_id',
        'user_id',
        'label',
        'file_path',
        'uploaded_by',
    ];

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
