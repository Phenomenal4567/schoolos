<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Design ref: 13-schoolos-database-schema-v2.md §4
 *
 * Direct fix for F14: GegoK12 mixed medical/health fields directly into
 * `student_academics`, the yearly academic-enrollment table, giving
 * medical data the same access-control surface as roll number/academic
 * status. Split into its own table with its own, independently
 * configurable access policy.
 *
 * TODO(Phase 1): this model's default visibility is intentionally left
 * unrestricted at the Eloquent layer — the access policy itself (default:
 * school nurse/admin roles only, per 13 §4) belongs in a Policy class once
 * the authorization middleware (12 §3) is wired up, not baked into the
 * model. Do not expose this resource via a route/controller without that
 * policy attached.
 */
class StudentHealthProfile extends Model
{
    use HasFactory;

    protected $fillable = [
        'school_id',
        'student_id',
        'medication_problems',
        'medication_needs',
        'medication_allergies',
        'food_allergies',
        'other_allergies',
        'other_medical_info',
        'height',
        'weight',
    ];

    protected function casts(): array
    {
        return [
            'height' => 'decimal:2',
            'weight' => 'decimal:2',
        ];
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }
}
