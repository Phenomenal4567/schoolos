<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Design ref: 14-schoolos-implementation-plan.md §5
 *
 * `class_section_id` is the same generic scoping column TimetableSlot/
 * LessonPlan/Assignment/Exam already carry — ScopeService's existing
 * relationshipScope() branches narrow on it without any change to that
 * class. `audience_type = 'school'` announcements carry a null
 * class_section_id and are visible tenant-wide; see
 * AnnouncementRepository::visibleTo() for how the two audience types
 * combine into one query.
 *
 * Not in $fillable: nothing here is written outside
 * AnnouncementRepository::create() (the single write path, matching this
 * bundle's "one repository method owns a resource's creation" shape), so
 * mass-assignment guarding isn't load-bearing the way ClassSection's
 * class_teacher_id exclusion is — listed anyway for consistency with
 * every other model in this bundle.
 */
class Announcement extends Model
{
    use HasFactory;

    protected $fillable = [
        'school_id',
        'author_id',
        'title',
        'body',
        'audience_type',
        'class_section_id',
        'published_at',
    ];

    protected function casts(): array
    {
        return [
            'published_at' => 'datetime',
        ];
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function classSection(): BelongsTo
    {
        return $this->belongsTo(ClassSection::class);
    }
}
