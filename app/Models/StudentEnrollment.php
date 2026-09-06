<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Design ref: 13-schoolos-database-schema-v2.md §3
 *
 * GegoK12's `student_academics`, split per F14 — medical fields live in
 * StudentHealthProfile instead, not here. `UNIQUE(student_id,
 * academic_year_id)` (enforced at the schema level, see the 000007
 * migration) matches 05-database-map.md §4's positive finding that
 * GegoK12 already versions enrollment by year; kept as-is.
 */
class StudentEnrollment extends Model
{
    use HasFactory;

    protected $fillable = [
        'school_id',
        'academic_year_id',
        'student_id',
        'class_section_id',
        'roll_number',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'status' => 'string',
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

    public function classSection(): BelongsTo
    {
        return $this->belongsTo(ClassSection::class);
    }
}
