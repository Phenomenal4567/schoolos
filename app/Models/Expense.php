<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Design ref: 22-schoolos-finance-schema.md §5 (discovery §13.2)
 *
 * Admin/accountant-only, no parent visibility, no student relationship —
 * tenantScope() only (21 §7).
 */
class Expense extends Model
{
    protected $fillable = [
        'school_id',
        'category',
        'amount',
        'description',
        'incurred_on',
        'recorded_by',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'incurred_on' => 'date',
        ];
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
