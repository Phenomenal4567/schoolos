<?php

namespace App\Repositories;

use App\Events\AnnouncementPublished;
use App\Exceptions\Academic\TeacherNotAssignedToSectionFailure;
use App\Models\Announcement;
use App\Models\ClassSection;
use App\Models\User;
use App\Repositories\Concerns\AssertsTeacherAssignedToSection;
use App\Services\ScopeService;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Design ref: 14-schoolos-implementation-plan.md §5
 *
 * The one write path for announcements — no controller writes an
 * Announcement row directly, matching this bundle's "one repository
 * method owns a resource's creation" shape (LessonPlanRepository::create(),
 * AssignmentRepository::create(), etc.). No draft state: create()
 * publishes immediately (published_at = now() at insert time) — see the
 * announcements migration's own doc comment for why a draft/scheduled
 * workflow is out of scope for this pass.
 *
 * Two distinct authoring rules, both checked before any write happens
 * (this bundle's "reject loudly before any transaction opens" posture):
 * - school_admin may post 'school'-wide announcements, or 'class_section'
 *   announcements for any class_section in their own school.
 * - teacher may only post 'class_section' announcements, and only for a
 *   class_section they're actually assigned to — reuses
 *   AssertsTeacherAssignedToSection, the same check LessonPlanRepository/
 *   AssignmentRepository/ExamRepository/TimetableRepository already use
 *   for the identical "may this teacher author content for this section"
 *   question, rather than a fifth near-copy of it.
 *
 * No audit_logs entry — ordinary content creation, same reasoning
 * LessonPlanRepository::create()'s doc comment gives for its own
 * create() (audit_logs is scoped to D2/D3-shaped workflow-integrity
 * transitions, not first-time content authoring).
 */
class AnnouncementRepository
{
    use AssertsTeacherAssignedToSection;

    /**
     * Default page size for visibleTo() — see that method's doc comment.
     * A plain class constant rather than config()-driven: nothing else
     * in this bundle externalizes per-list page sizes into config, and
     * a caller that genuinely needs a different size can already pass
     * one explicitly.
     */
    private const PER_PAGE = 20;

    /**
     * @throws TeacherNotAssignedToSectionFailure if $actor is a teacher
     *         with no class_teacher_assignments row for $classSectionId
     *         and is not its class_teacher_id.
     * @throws \InvalidArgumentException if $actor's role may not author
     *         announcements at all, if a teacher supplies audience_type
     *         'school', if $classSectionId is required but missing (or
     *         vice versa), or if $classSectionId doesn't belong to
     *         $schoolId.
     */
    public function create(
        int $schoolId,
        User $actor,
        string $title,
        string $body,
        string $audienceType,
        ?int $classSectionId,
    ): Announcement {
        $roleKey = $actor->role->key ?? null;

        if (! in_array($roleKey, ['school_admin', 'teacher'], true)) {
            throw new \InvalidArgumentException(
                "AnnouncementRepository::create(): role '{$roleKey}' may not author announcements."
            );
        }

        if (! in_array($audienceType, ['school', 'class_section'], true)) {
            throw new \InvalidArgumentException(
                "AnnouncementRepository::create(): invalid audience_type '{$audienceType}'."
            );
        }

        if ($roleKey === 'teacher' && $audienceType !== 'class_section') {
            throw new \InvalidArgumentException(
                'AnnouncementRepository::create(): a teacher may only post class_section announcements.'
            );
        }

        if ($audienceType === 'class_section') {
            if ($classSectionId === null) {
                throw new \InvalidArgumentException(
                    "AnnouncementRepository::create(): audience_type 'class_section' requires a class_section_id."
                );
            }

            $classSection = ClassSection::findOrFail($classSectionId);

            if ($classSection->school_id !== $schoolId) {
                throw new \InvalidArgumentException(
                    "AnnouncementRepository::create(): class_section #{$classSectionId} belongs to "
                    . "school #{$classSection->school_id}, not the requested school #{$schoolId}."
                );
            }

            if ($roleKey === 'teacher') {
                $this->assertTeacherAssignedToSection($actor, $classSectionId);
            }
        } else {
            // 'school' audience carries no class_section_id — a value
            // here would be silently ignored by ScopeService's read-side
            // dispatch (a null column short-circuits relationship
            // narrowing), which is exactly the kind of "accepted but
            // meaningless" input this bundle avoids elsewhere (e.g.
            // LessonPlanRepository rejecting a mismatched school_id
            // rather than silently overriding it).
            if ($classSectionId !== null) {
                throw new \InvalidArgumentException(
                    "AnnouncementRepository::create(): audience_type 'school' must not carry a class_section_id."
                );
            }
        }

        $announcement = Announcement::create([
            'school_id' => $schoolId,
            'author_id' => $actor->id,
            'title' => $title,
            'body' => $body,
            'audience_type' => $audienceType,
            'class_section_id' => $classSectionId,
            'published_at' => now(),
        ]);

        // Dispatched after the write, matching AttendanceRepository::
        // mark()'s "announce the committed fact, let a listener react"
        // shape — Announcement::create() isn't wrapped in an explicit
        // transaction (a single-row insert, nothing to roll back
        // alongside), so there's no commit boundary to wait for beyond
        // create() itself having returned.
        event(new AnnouncementPublished($announcement));

        return $announcement;
    }

    /**
     * The announcements a given actor may see: every 'school'-wide
     * announcement in their tenant, plus every 'class_section'
     * announcement their role's existing ScopeService::relationshipScope()
     * branch would already surface for that resource type. Two queries
     * merged by id rather than one, because 'school' rows (null
     * class_section_id) and 'class_section' rows need different
     * treatment at the relationship-scope step — a null column would
     * otherwise fall through relationshipScope()'s generic dispatch to
     * its final "no defined join, deny" branch, which is correct for an
     * unrelated model shape but wrong here, since 'school' rows are
     * supposed to be visible tenant-wide.
     *
     * Paginated (default 20/page, per self::PER_PAGE) rather than
     * ->get(): an announcement feed grows without bound over a school's
     * lifetime, unlike the bounded-by-construction lists elsewhere in
     * this bundle (e.g. a teacher's own class_teacher_assignments row
     * count), so this is the one visibleTo()-shaped list in the
     * communication domain that needs a page boundary rather than a
     * full scan on every request. The id-merge above still runs
     * unpaginated (it's an id-only pluck() over indexed columns, not
     * the full row fetch paginate() below performs), so the page
     * boundary only bites where the actual row/relationship hydration
     * cost is.
     */
    public function visibleTo(User $actor, ScopeService $scope, int $perPage = self::PER_PAGE): LengthAwarePaginator
    {
        $schoolWideIds = $scope
            ->tenantScope(Announcement::query(), $actor)
            ->where('audience_type', 'school')
            ->pluck('id');

        $classScopedIds = $scope
            ->relationshipScope(
                $scope->tenantScope(Announcement::query(), $actor)->where('audience_type', 'class_section'),
                $actor,
                Announcement::class,
            )
            ->pluck('id');

        return Announcement::whereIn('id', $schoolWideIds->merge($classScopedIds))
            ->with(['author', 'classSection.standard', 'classSection.section'])
            ->orderByDesc('published_at')
            ->paginate($perPage);
    }

    /**
     * A single announcement by id, resolved through the identical
     * visibleTo() rule set — built as its own query rather than
     * "find in visibleTo()'s result" so a single-row lookup doesn't pay
     * for two full collection scans. Returns null on a miss (wrong
     * tenant, or an in-tenant class_section announcement the caller has
     * no relationship to) rather than aborting itself, matching
     * ResolvesScopedAcademicResource::scopedFind()'s same contract — the
     * calling controller's own abort(404) call site keeps 404-not-403
     * discipline in one place.
     */
    public function findVisibleTo(int $id, User $actor, ScopeService $scope): ?Announcement
    {
        $schoolWideMatch = $scope
            ->tenantScope(Announcement::query(), $actor)
            ->where('audience_type', 'school')
            ->where('id', $id)
            ->with(['author', 'classSection.standard', 'classSection.section'])
            ->first();

        if ($schoolWideMatch !== null) {
            return $schoolWideMatch;
        }

        return $scope
            ->relationshipScope(
                $scope->tenantScope(Announcement::query(), $actor)->where('audience_type', 'class_section'),
                $actor,
                Announcement::class,
            )
            ->where('id', $id)
            ->with(['author', 'classSection.standard', 'classSection.section'])
            ->first();
    }
}
