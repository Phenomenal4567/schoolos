<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Design ref: 17-schoolos-academic-domain-map.md §2, §3, §7
 *
 * One row per class_section per subject — see the
 * 2026_08_27_000004 migration's doc comment for why (resolves 17 §7's
 * open "exam granularity" question). The class_section_id column is
 * what lets ScopeService::relationshipScope() cover this model
 * generically, the same shape as Assignment/TimetableSlot/LessonPlan.
 * ExamMark (via the marks() relation) is the resource that needed the
 * new ScopeService branch, not this one.
 */
class Exam extends Model
{
    use HasFactory;

    protected $fillable = [
        'school_id',
        'academic_year_id',
        'class_section_id',
        'subject_id',
        'name',
        'exam_date',
    ];

    protected function casts(): array
    {
        return [
            'exam_date' => 'date:Y-m-d',
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

    public function classSection(): BelongsTo
    {
        return $this->belongsTo(ClassSection::class);
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function marks(): HasMany
    {
        return $this->hasMany(ExamMark::class);
    }

    public function publishedMarks(): HasMany
    {
        return $this->marks()->where('status', ExamMark::STATUS_PUBLISHED);
    }
}
