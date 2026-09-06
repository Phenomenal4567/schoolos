<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Design ref: 22-schoolos-finance-schema.md §2
 * Decision refs: 16-schoolos-decisions-register.md D11, D12
 *
 * Carries student_id directly, so ScopeService::relationshipScope()'s
 * existing parent/student branches (13 §"student_id column" dispatch)
 * apply to this model with no new ScopeService code — the same reason
 * StudentEnrollment needed none.
 *
 * amount_due is fixed at write time (D11) — see
 * FeeAssessmentRepository::assess(). amount_remaining is deliberately
 * not a column; it's always computed from confirmed payments (see
 * amountRemaining() below), so it can never drift from the payments it
 * summarizes.
 */
class FeeAssessment extends Model
{
    protected $fillable = [
        'school_id',
        'academic_year_id',
        'student_id',
        'fee_category_id',
        'base_amount',
        'discount_amount',
        'scholarship_amount',
        'amount_due',
        'due_date',
        'rolled_over_from_assessment_id',
        'status',
        'overdue_reminder_sent_at',
        'deadline_reminder_sent_at',
    ];

    protected function casts(): array
    {
        return [
            'base_amount' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'scholarship_amount' => 'decimal:2',
            'amount_due' => 'decimal:2',
            'due_date' => 'date',
            'overdue_reminder_sent_at' => 'datetime',
            'deadline_reminder_sent_at' => 'datetime',
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

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function feeCategory(): BelongsTo
    {
        return $this->belongsTo(FeeCategory::class);
    }

    public function rolledOverFrom(): BelongsTo
    {
        return $this->belongsTo(self::class, 'rolled_over_from_assessment_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function discounts(): HasMany
    {
        return $this->hasMany(Discount::class);
    }

    /**
     * amount_due minus the sum of confirmed payments against this
     * assessment — the single definition every read surface (parent
     * portal, admin dashboard) must use, per 21 §2. Never stored.
     */
    public function amountRemaining(): string
    {
        $confirmedPaid = $this->payments()
            ->where('status', 'confirmed')
            ->sum('amount');

        return bcsub((string) $this->amount_due, (string) $confirmedPaid, 2);
    }
}
