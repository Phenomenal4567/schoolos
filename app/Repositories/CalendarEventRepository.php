<?php

namespace App\Repositories;

use App\Models\AcademicTerm;
use App\Models\AcademicYear;
use App\Models\CalendarEvent;
use App\Models\User;
use App\Services\ScopeService;
use Illuminate\Database\Eloquent\Collection;

/**
 * Design ref: 19-discovery-hierarchy-gap-closure-plan.md §3,
 * 20-phase-6-8-execution-prompt.md §1.
 *
 * The one write path for calendar events — no controller writes a
 * CalendarEvent row directly, matching this bundle's "one repository
 * method owns a resource's creation/mutation" shape
 * (AnnouncementRepository::create(), LessonPlanRepository::create(),
 * etc.). Only 'school_admin' may author/mutate calendar events (19 §3
 * doesn't mention teachers at all here, and this bundle's convention —
 * 17-schoolos-academic-domain-map.md §4 — is that 'school_admin' is the
 * SchoolOS stand-in for GegoK12's principal role, so "school-admin/
 * principal only" per 20 §1 needs no new Role row, the same reasoning
 * LessonPlan's reviewed_by/approve()/reject() already applied).
 *
 * Read side (visibleTo()) is the parent/student portal surface: tenant
 * scope only, no relationshipScope() call at all — see CalendarEvent's
 * own doc comment for why this resource has no per-section/per-child
 * narrowing to apply.
 */
class CalendarEventRepository
{
    private const EVENT_TYPES = [
        'term_date',
        'mid_term_break',
        'exam_period',
        'activity',
        'holiday',
        'closing_date',
        'other',
    ];

    /**
     * @param  array{
     *     academic_year_id: int,
     *     academic_term_id?: int|null,
     *     title: string,
     *     event_type: string,
     *     start_date: string,
     *     end_date?: string|null,
     *     visible_to_parents?: bool,
     * }  $data
     *
     * @throws \InvalidArgumentException if $actor isn't 'school_admin',
     *         if event_type isn't one of self::EVENT_TYPES, if
     *         academic_year_id doesn't belong to $schoolId, if
     *         academic_term_id is supplied but doesn't belong to both
     *         $schoolId and the given academic_year_id, or if end_date
     *         is supplied and falls before start_date.
     */
    public function create(int $schoolId, User $actor, array $data): CalendarEvent
    {
        $this->assertActorMayAuthor($actor);
        $this->assertValidShape($schoolId, $data);

        return CalendarEvent::create([
            'school_id' => $schoolId,
            'academic_year_id' => $data['academic_year_id'],
            'academic_term_id' => $data['academic_term_id'] ?? null,
            'title' => $data['title'],
            'event_type' => $data['event_type'],
            'start_date' => $data['start_date'],
            'end_date' => $data['end_date'] ?? null,
            'visible_to_parents' => $data['visible_to_parents'] ?? true,
        ]);
    }

    /**
     * @param  array{
     *     academic_year_id: int,
     *     academic_term_id?: int|null,
     *     title: string,
     *     event_type: string,
     *     start_date: string,
     *     end_date?: string|null,
     *     visible_to_parents?: bool,
     * }  $data
     *
     * Same validation as create() — an update() can move an event to a
     * different academic_year_id/academic_term_id, so it re-checks the
     * cross-reference the same way rather than trusting the row's
     * existing school_id alone.
     *
     * @throws \InvalidArgumentException see create()'s doc comment.
     */
    public function update(CalendarEvent $event, User $actor, array $data): CalendarEvent
    {
        $this->assertActorMayAuthor($actor);
        $this->assertValidShape($event->school_id, $data);

        $event->update([
            'academic_year_id' => $data['academic_year_id'],
            'academic_term_id' => $data['academic_term_id'] ?? null,
            'title' => $data['title'],
            'event_type' => $data['event_type'],
            'start_date' => $data['start_date'],
            'end_date' => $data['end_date'] ?? null,
            'visible_to_parents' => $data['visible_to_parents'] ?? true,
        ]);

        return $event->refresh();
    }

    /**
     * @throws \InvalidArgumentException if $actor isn't 'school_admin'.
     */
    public function delete(CalendarEvent $event, User $actor): void
    {
        $this->assertActorMayAuthor($actor);

        $event->delete();
    }

    /**
     * The calendar events a given parent/student may see: every event
     * in their own tenant with visible_to_parents = true. Tenant scope
     * only — see this class's own doc comment for why relationshipScope()
     * is never called here. Ordered by start_date so a portal view reads
     * as a chronological agenda, matching discovery §1.3's framing
     * ("the entire session calendar"), not an insertion-order list.
     */
    public function visibleTo(User $actor, ScopeService $scope): Collection
    {
        return $scope->tenantScope(CalendarEvent::query(), $actor)
            ->where('visible_to_parents', true)
            ->orderBy('start_date')
            ->get();
    }

    /**
     * A single calendar event by id, resolved through the identical
     * visibleTo() rule set — a wrong-tenant id or an in-tenant event with
     * visible_to_parents = false both return null (404-not-403 at the
     * controller, same discipline as AnnouncementRepository::findVisibleTo()).
     */
    public function findVisibleTo(int $id, User $actor, ScopeService $scope): ?CalendarEvent
    {
        return $scope->tenantScope(CalendarEvent::query(), $actor)
            ->where('visible_to_parents', true)
            ->where('id', $id)
            ->first();
    }

    private function assertActorMayAuthor(User $actor): void
    {
        $roleKey = $actor->role->key ?? null;

        if ($roleKey !== 'school_admin') {
            throw new \InvalidArgumentException(
                "CalendarEventRepository: role '{$roleKey}' may not author calendar events."
            );
        }
    }

    private function assertValidShape(int $schoolId, array $data): void
    {
        if (! in_array($data['event_type'], self::EVENT_TYPES, true)) {
            throw new \InvalidArgumentException(
                "CalendarEventRepository: invalid event_type '{$data['event_type']}'."
            );
        }

        $academicYear = AcademicYear::findOrFail($data['academic_year_id']);

        if ($academicYear->school_id !== $schoolId) {
            throw new \InvalidArgumentException(
                "CalendarEventRepository: academic_year #{$academicYear->id} belongs to "
                . "school #{$academicYear->school_id}, not the requested school #{$schoolId}."
            );
        }

        if (! empty($data['academic_term_id'])) {
            $term = AcademicTerm::findOrFail($data['academic_term_id']);

            if ($term->school_id !== $schoolId || $term->academic_year_id !== $academicYear->id) {
                throw new \InvalidArgumentException(
                    "CalendarEventRepository: academic_term #{$term->id} does not belong to "
                    . "school #{$schoolId} / academic_year #{$academicYear->id}."
                );
            }
        }

        if (! empty($data['end_date']) && $data['end_date'] < $data['start_date']) {
            throw new \InvalidArgumentException(
                'CalendarEventRepository: end_date must not fall before start_date.'
            );
        }
    }
}
