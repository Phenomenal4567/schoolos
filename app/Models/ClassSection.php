<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Design ref: 13-schoolos-database-schema-v2.md §3
 * Decision ref: 16-schoolos-decisions-register.md D3
 *
 * GegoK12's `standards_link` 4-way join (05-database-map.md §2), renamed
 * for clarity per 12 §7's naming-discipline rule.
 *
 * `class_teacher_id` is deliberately NOT in $fillable. D3 makes
 * ClassSectionRepository::assignClassTeacher() the only permitted write
 * path for that column, specifically so the "must be a role='teacher'
 * user" check can't be bypassed by a mass-assignment call (`ClassSection::
 * create([...])` / `->update([...])`) that includes the column directly.
 * Leaving it fillable here would quietly reopen the second write path D3
 * exists to close.
 */
class ClassSection extends Model
{
    use HasFactory;

    protected $fillable = [
        'school_id',
        'academic_year_id',
        'standard_id',
        'section_id',
    ];

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function standard(): BelongsTo
    {
        return $this->belongsTo(Standard::class);
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class);
    }

    /**
     * Read-only from this model's side. Written exclusively by
     * ClassSectionRepository::assignClassTeacher() (D3), via direct
     * attribute assignment + save() inside a transaction — never via
     * this relationship or mass assignment.
     */
    public function classTeacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'class_teacher_id');
    }

    public function classTeacherAssignments(): HasMany
    {
        return $this->hasMany(ClassTeacherAssignment::class);
    }

    public function studentEnrollments(): HasMany
    {
        return $this->hasMany(StudentEnrollment::class);
    }
}
