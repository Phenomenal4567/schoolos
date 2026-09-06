<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Design ref: 22-schoolos-finance-schema.md §3 (discovery §10.4)
 *
 * Always applied to a specific fee_assessment, never enrollment-wide —
 * created only from inside FeeAssessmentRepository::assess(), never a
 * standalone write path of its own (no controller writes this table
 * directly).
 */
class Discount extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'school_id',
        'fee_assessment_id',
        'type',
        'value',
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

    public function feeAssessment(): BelongsTo
    {
        return $this->belongsTo(FeeAssessment::class);
    }

    public function grantedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'granted_by');
    }
}
