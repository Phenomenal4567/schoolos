<?php

namespace Tests\Feature;

use App\Repositories\CalendarEventRepository;
use App\Services\ScopeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesSchoolOsFixtures;
use Tests\TestCase;

/**
 * Design ref: 19-discovery-hierarchy-gap-closure-plan.md §3,
 * 20-phase-6-8-execution-prompt.md §1.
 *
 * One test per item 20 §1's build prompt lists, in the same order:
 * (a) tenant scoping, (b) parent/student cross-tenant isolation,
 * (c) visible_to_parents = false is actually hidden. Matches
 * Phase4TestGateTest.php/Phase5TestGateTest.php's own "one test per gate
 * row" shape.
 *
 * Row 8's route-table lint (Phase1TestGateTest::
 * test_every_show_by_id_route_has_a_registered_scope_check()) already
 * covers every calendar-events/{calendarEvent} route this track added —
 * no dedicated test for that here, same reasoning Phase5TestGateTest's
 * own doc comment gives for its row 6.
 */
class Phase6aTestGateTest extends TestCase
{
    use RefreshDatabase;
    use CreatesSchoolOsFixtures;

    /**
     * (a) — events are tenant-scoped correctly: a school_admin in
     * school A sees only school A's events through
     * CalendarEventRepository::visibleTo(), never school B's, even
     * though both schools have events in the same academic-year shape.
     * (school_admin passes relationshipScope()'s default branch, i.e.
     * tenant-scope-only, so this exercises the tenantScope() call
     * visibleTo() makes directly.)
     */
    public function test_calendar_events_are_tenant_scoped(): void
    {
        $scope = app(ScopeService::class);
        $repository = app(CalendarEventRepository::class);

        $schoolA = $this->makeSchool();
        $schoolB = $this->makeSchool();

        $yearA = $this->makeAcademicYear($schoolA);
        $yearB = $this->makeAcademicYear($schoolB);

        $eventA = $this->makeCalendarEvent($schoolA, $yearA, ['title' => 'School A Event']);
        $this->makeCalendarEvent($schoolB, $yearB, ['title' => 'School B Event']);

        $adminA = $this->makeRoleUser('school_admin', $schoolA);

        $visible = $repository->visibleTo($adminA, $scope);

        $this->assertCount(1, $visible);
        $this->assertSame($eventA->id, $visible->first()->id);
    }

    /**
     * (b) — a parent/student can only see events for their own school:
     * both roles exercised against the same two-school fixture as (a),
     * confirming visibleTo() and findVisibleTo() agree (a cross-tenant
     * id is a 404-shaped null, not an exception or a leaked row).
     */
    public function test_parent_and_student_only_see_their_own_school_events(): void
    {
        $scope = app(ScopeService::class);
        $repository = app(CalendarEventRepository::class);

        $schoolA = $this->makeSchool();
        $schoolB = $this->makeSchool();

        $yearA = $this->makeAcademicYear($schoolA);
        $yearB = $this->makeAcademicYear($schoolB);

        $eventA = $this->makeCalendarEvent($schoolA, $yearA, ['title' => 'School A Event']);
        $eventB = $this->makeCalendarEvent($schoolB, $yearB, ['title' => 'School B Event']);

        $parentA = $this->makeRoleUser('parent', $schoolA);
        $studentA = $this->makeRoleUser('student', $schoolA);

        foreach ([$parentA, $studentA] as $actor) {
            $visible = $repository->visibleTo($actor, $scope);
            $this->assertCount(1, $visible);
            $this->assertSame($eventA->id, $visible->first()->id);

            $this->assertNotNull($repository->findVisibleTo($eventA->id, $actor, $scope));
            $this->assertNull($repository->findVisibleTo($eventB->id, $actor, $scope));
        }
    }

    /**
     * (c) — visible_to_parents = false events don't appear in
     * parent/student views: an admin-internal event (e.g. a staff-only
     * planning day) is excluded from both visibleTo() and
     * findVisibleTo() for parent/student actors, even though it's in
     * their own tenant and would otherwise pass tenant scoping cleanly.
     */
    public function test_visible_to_parents_false_is_hidden_from_parent_and_student(): void
    {
        $scope = app(ScopeService::class);
        $repository = app(CalendarEventRepository::class);

        $school = $this->makeSchool();
        $year = $this->makeAcademicYear($school);

        $publicEvent = $this->makeCalendarEvent($school, $year, [
            'title' => 'Mid-Term Break',
            'visible_to_parents' => true,
        ]);
        $internalEvent = $this->makeCalendarEvent($school, $year, [
            'title' => 'Staff Planning Day',
            'visible_to_parents' => false,
        ]);

        $parent = $this->makeRoleUser('parent', $school);
        $student = $this->makeRoleUser('student', $school);

        foreach ([$parent, $student] as $actor) {
            $visible = $repository->visibleTo($actor, $scope);

            $this->assertCount(1, $visible);
            $this->assertSame($publicEvent->id, $visible->first()->id);
            $this->assertFalse($visible->pluck('id')->contains($internalEvent->id));

            $this->assertNotNull($repository->findVisibleTo($publicEvent->id, $actor, $scope));
            $this->assertNull($repository->findVisibleTo($internalEvent->id, $actor, $scope));
        }
    }
}
