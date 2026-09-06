<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Design ref: 19-discovery-hierarchy-gap-closure-plan.md §6 (discovery
 * §7.3), 20-phase-6-8-execution-prompt.md §3.
 *
 * class_section_id/subject_id are both nullable (a school-wide material
 * carries neither) — this is why the model's read side is resolved
 * through LearningMaterialRepository::visibleTo()/findVisibleTo()
 * rather than ResolvesScopedAcademicResource's generic dispatch; see
 * that repository's own doc comment, and this table's migration
 * comment, for why a plain relationshipScope() call isn't sufficient
 * here the same way it isn't for Announcement.
 */
class LearningMaterial extends Model
{
    use HasFactory;

    protected $fillable = [
        'school_id',
        'class_section_id',
        'subject_id',
        'title',
        'file_path',
        'material_type',
    ];

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function classSection(): BelongsTo
    {
        return $this->belongsTo(ClassSection::class);
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }
}
