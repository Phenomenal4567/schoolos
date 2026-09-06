<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Design ref: 22-schoolos-finance-schema.md §4
 * Decision ref: 16-schoolos-decisions-register.md D13
 *
 * One ledger row per payment attempt, online or manual. Carries no
 * student_id of its own — Parent/StudentPortal fee-view controllers
 * resolve access through the owning fee_assessment (already
 * ScopeService-checked), not through a Payment-specific scope branch
 * (22 §6's obligations table).
 */
class Payment extends Model
{
    protected $fillable = [
        'school_id',
        'fee_assessment_id',
        'amount',
        'processor',
        'paystack_reference',
        'receipt_upload_path',
        'status',
        'recorded_by',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
        ];
    }

    public function feeAssessment(): BelongsTo
    {
        return $this->belongsTo(FeeAssessment::class);
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
