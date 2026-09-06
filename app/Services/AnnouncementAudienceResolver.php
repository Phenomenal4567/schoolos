<?php

namespace App\Services;

use App\Models\Announcement;
use App\Models\ClassSection;
use App\Models\ClassTeacherAssignment;
use App\Models\StudentEnrollment;
use App\Models\StudentParentLink;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

/**
 * Design ref: 14-schoolos-implementation-plan.md §5
 *
 * Computes *who gets notified* for an announcement — a distinct question
 * from *who can see it in a listing*, even though both draw on the same
 * underlying relationship tables (class_teacher_assignments,
 * student_enrollments, student_parent_links) ScopeService already uses.
 * Kept as its own service rather than a ScopeService method because its
 * output is a concrete list of User rows to notify, not a query
 * constraint to intersect — a different shape of answer than
 * tenantScope()/relationshipScope() give, per those methods' own
 * doc comments.
 *
 * 'school' audience: every teacher, parent, and student in the school —
 * platform-level roles (super_admin, school_admin) are deliberately
 * excluded, since an announcement is content *for* the school community,
 * not an internal platform event.
 *
 * 'class_section' audience: the section's actively-enrolled students,
 * those students' actively-linked parents, and the section's assigned
 * teachers (class_teacher_assignments rows plus the homeroom
 * class_teacher_id, same two sources ScopeService::
 * teacherRelationshipScope() already merges) — a teacher posting to their
 * own class still notifies any co-assigned teacher, matching "everyone
 * with a stake in this section should know," not just the parent side.
 *
 * The announcement's own author is excluded from the result in both
 * cases — nobody needs to be notified of their own post.
 */
class AnnouncementAudienceResolver
{
    /**
     * @return Collection<int, User>
     */
    public function resolve(Announcement $announcement): Collection
    {
        $userIds = $announcement->audience_type === 'school'
            ? $this->schoolAudienceIds($announcement)
            : $this->classSectionAudienceIds($announcement);

        $userIds = $userIds->reject(fn (int $id) => $id === $announcement->author_id)->unique();

        return User::whereIn('id', $userIds)->get();
    }

    /**
     * @return \Illuminate\Support\Collection<int, int>
     */
    private function schoolAudienceIds(Announcement $announcement): \Illuminate\Support\Collection
    {
        return User::where('school_id', $announcement->school_id)
            ->whereHas('role', fn ($q) => $q->whereIn('key', ['teacher', 'parent', 'student']))
            ->pluck('id');
    }

    /**
     * @return \Illuminate\Support\Collection<int, int>
     */
    private function classSectionAudienceIds(Announcement $announcement): \Illuminate\Support\Collection
    {
        $classSectionId = $announcement->class_section_id;

        $studentIds = StudentEnrollment::where('class_section_id', $classSectionId)
            ->where('status', 'active')
            ->pluck('student_id');

        $parentIds = StudentParentLink::whereIn('student_id', $studentIds)
            ->where('status', 'active')
            ->pluck('parent_id');

        $teacherIds = ClassTeacherAssignment::where('class_section_id', $classSectionId)
            ->pluck('teacher_id')
            ->merge(
                ClassSection::where('id', $classSectionId)->pluck('class_teacher_id')->filter()
            );

        return $studentIds->merge($parentIds)->merge($teacherIds);
    }
}
