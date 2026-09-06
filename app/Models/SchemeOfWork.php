<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Design ref: 19-discovery-hierarchy-gap-closure-plan.md §6 (discovery
 * §7.1), 20-phase-6-8-execution-prompt.md §3.
 *
 * The class_section_id column is what lets
 * ScopeService::relationshipScope() cover reads of this model
 * generically — same property LessonPlan's own model doc comment
 * documents (17 §3), no ScopeService change needed to scope "which
 * scheme-of-work rows can this teacher/parent/student see."
 *
 * Admin-authored, not teacher-authored: uploaded_by is a plain FK to
 * users (school_admin, per SchemeOfWorkRepository::create()'s role
 * check), matching LessonPlan.reviewed_by's precedent that a
 * role-specific author/reviewer column stays a plain users FK rather
 * than a specialized subclass or table (17 §4's school_admin-as-
 * principal-stand-in settlement).
 */
class SchemeOfWork extends Model
{
    use HasFactory;

    protected $table = 'scheme_of_work';

    protected $fillable = [
        'school_id',
        'academic_term_id',
        'class_section_id',
        'subject_id',
        'content',
        'uploaded_by',
    ];

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function academicTerm(): BelongsTo
    {
        return $this->belongsTo(AcademicTerm::class);
    }

    public function classSection(): BelongsTo
    {
        return $this->belongsTo(ClassSection::class);
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
