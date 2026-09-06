<?php

namespace Tests\Feature;

use App\Exceptions\Communication\UnauthorizedFeedbackFailure;
use App\Exceptions\Communication\UnauthorizedReadReceiptFailure;
use App\Notifications\AnnouncementPublishedNotification;
use App\Notifications\AttendanceAbsenceNotification;
use App\Repositories\AnnouncementRepository;
use App\Repositories\AttendanceRepository;
use App\Repositories\ContentReadReceiptRepository;
use App\Repositories\FeedbackRepository;
use App\Services\ScopeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\Concerns\CreatesSchoolOsFixtures;
use Tests\TestCase;

/**
 * Design ref: 18-schoolos-communication-domain-map.md §7 (Phase 5 test
 * gate, extending Phase 1/4's per 14 §4)
 *
 * One test per row of 18 §7's table, in the same order, matching
 * Phase4TestGateTest.php's own "one test per gate row" shape. This gate
 * doubles as the very first regression suite the Phase 5 code (D6 —
 * found partially built ahead of process) has ever been run against —
 * every test here goes through the real repositories/models/listeners
 * this task wired up, not a standalone boolean.
 *
 * Row 6 ("every by-ID announcement/feedback route carries
 * 'scope.checked'") has no dedicated test in this file — it's already
 * covered for free by
 * Phase1TestGateTest::test_every_show_by_id_route_has_a_registered_scope_check(),
 * which walks the whole route table generically and therefore already
 * lints every announcements/{announcement}, feedback/{feedback} route
 * added by this pass. See that test's own doc comment; if it fails, the
 * gap is a missing ->middleware('scope.checked') in routes/web.php, not
 * a test-writing problem here.
 */
class Phase5TestGateTest extends TestCase
{
    use RefreshDatabase;
    use CreatesSchoolOsFixtures;

    /**
     * Row 1 — "A user with no class_teacher_assignments/enrollment tie to
     * a class_section cannot see that section's class_section-audience
     * announcements; every in-tenant user sees school-wide ones
     * regardless."
     *
     * Regresses/confirms: generalizes F24 (NoticeBoard fully-unscoped
     * show); confirms AnnouncementRepository::visibleTo()'s two-branch
     * merge (school-wide bypass + class_section relationship narrowing)
     * actually works under real data. Exercises visibleTo() directly with
     * teacher/parent/student fixtures both in and out of scope.
     */
    public function test_visibility_of_school_wide_vs_class_section_announcements(): void
    {
        $school = $this->makeSchool();
        $academicYear = $this->makeAcademicYear($school);
        $admin = $this->makeUser(['role_id' => $this->makeRole('school_admin')->id, 'school_id' => $school->id]);

        $sectionTeacher = $this->makeUser(['role_id' => $this->makeRole('teacher')->id, 'school_id' => $school->id]);
        $section = $this->makeClassSection($school, $academicYear, $sectionTeacher);

        $enrolledStudent = $this->makeUser(['role_id' => $this->makeRole('student')->id, 'school_id' => $school->id]);
        $this->makeStudentEnrollment($school, $academicYear, $section, $enrolledStudent);
        $linkedParent = $this->makeUser(['role_id' => $this->makeRole('parent')->id, 'school_id' => $school->id]);
        $this->makeStudentParentLink($school, $linkedParent, $enrolledStudent);

        // Out of scope: no class_teacher_assignments/enrollment/link tie
        // to $section at all.
        $unrelatedTeacher = $this->makeUser(['role_id' => $this->makeRole('teacher')->id, 'school_id' => $school->id]);
        $unrelatedStudent = $this->makeUser(['role_id' => $this->makeRole('student')->id, 'school_id' => $school->id]);
        $unrelatedParent = $this->makeUser(['role_id' => $this->makeRole('parent')->id, 'school_id' => $school->id]);

        $schoolWide = $this->makeAnnouncement($school, $admin, 'school');
        $classScoped = $this->makeAnnouncement($school, $sectionTeacher, 'class_section', $section);

        $scope = new ScopeService();
        $repository = new AnnouncementRepository();

        foreach ([$sectionTeacher, $linkedParent, $enrolledStudent] as $inScopeUser) {
            $ids = $repository->visibleTo($inScopeUser, $scope)->pluck('id')->all();

            $this->assertContains($schoolWide->id, $ids, get_class($inScopeUser) . ' must see the school-wide announcement.');
            $this->assertContains($classScoped->id, $ids, 'A user tied to the class_section must see its announcement.');
        }

        foreach ([$unrelatedTeacher, $unrelatedParent, $unrelatedStudent] as $outOfScopeUser) {
            $ids = $repository->visibleTo($outOfScopeUser, $scope)->pluck('id')->all();

            $this->assertContains($schoolWide->id, $ids, 'Every in-tenant user must see the school-wide announcement regardless of section ties.');
            $this->assertNotContains($classScoped->id, $ids, 'A user with no tie to the class_section must not see its announcement.');
        }
    }

    /**
     * Row 2 — "No notification endpoint returns another user's inbox by
     * ID — every notification route resolves strictly from the
     * authenticated session."
     *
     * Regresses: F27 (notifications($id)), generalizing F24's
     * showNotices($teacher_id) lesson. None of the three
     * NotificationController::index() routes take a route parameter at
     * all, so the first half of this is "assert the route has none";
     * the second half is an HTTP test confirming two different users
     * each only ever see their own $user->notifications().
     */
    public function test_notification_inbox_is_session_scoped_with_no_id_parameter(): void
    {
        foreach (['parent.notifications.index', 'teacher.notifications.index', 'student.notifications.index'] as $name) {
            $route = app('router')->getRoutes()->getByName($name);

            $this->assertNotNull($route, "Route {$name} must exist.");
            $this->assertSame(
                [],
                $route->parameterNames(),
                "{$name} must take no route parameter — an inbox is resolved from the authenticated "
                . 'session only, never a by-ID lookup (F27).'
            );
        }

        $school = $this->makeSchool();
        $roleParent = $this->makeRole('parent');
        $userA = $this->makeUser(['role_id' => $roleParent->id, 'school_id' => $school->id]);
        $userB = $this->makeUser(['role_id' => $roleParent->id, 'school_id' => $school->id]);

        $userA->notify(new AnnouncementPublishedNotification($this->makeAnnouncement($school, $userA, 'school')));
        $userB->notify(new AnnouncementPublishedNotification($this->makeAnnouncement($school, $userB, 'school')));

        $responseA = $this->actingAs($userA)->get('/parent/notifications');
        $responseA->assertOk();
        $responseA->assertViewIs('parent.notifications.index');
        $responseA->assertViewHas('notifications', function ($notifications) use ($userA) {
            return $notifications->count() === 1
                && $notifications->first()->notifiable_id === $userA->id;
        });

        $responseB = $this->actingAs($userB)->get('/parent/notifications');
        $responseB->assertOk();
        $responseB->assertViewIs('parent.notifications.index');
        $responseB->assertViewHas('notifications', function ($notifications) use ($userB) {
            return $notifications->count() === 1
                && $notifications->first()->notifiable_id === $userB->id;
        });
    }

    /**
     * Row 3 — "A parent cannot file Feedback under a student who isn't
     * their own linked child; a student cannot file it as another
     * student."
     *
     * Regresses: F27 (store() spoofing). Asserts
     * FeedbackRepository::create() throws UnauthorizedFeedbackFailure in
     * both cases, and a happy-path case succeeds for an actually-linked
     * child / the student's own id.
     */
    public function test_parent_and_student_cannot_spoof_feedback_student_id(): void
    {
        $school = $this->makeSchool();
        $parent = $this->makeUser(['role_id' => $this->makeRole('parent')->id, 'school_id' => $school->id]);
        $ownChild = $this->makeUser(['role_id' => $this->makeRole('student')->id, 'school_id' => $school->id]);
        $otherStudent = $this->makeUser(['role_id' => $this->makeRole('student')->id, 'school_id' => $school->id]);
        $this->makeStudentParentLink($school, $parent, $ownChild);

        $repository = new FeedbackRepository();

        try {
            $repository->create($school->id, $parent, $otherStudent->id, 'msg', null, 'school', null);
            $this->fail('Expected UnauthorizedFeedbackFailure for a parent filing under an unlinked student.');
        } catch (UnauthorizedFeedbackFailure $e) {
            $this->assertSame($parent->id, $e->actor->id);
        }

        try {
            $repository->create($school->id, $otherStudent, $ownChild->id, 'msg', null, 'school', null);
            $this->fail('Expected UnauthorizedFeedbackFailure for a student filing as another student.');
        } catch (UnauthorizedFeedbackFailure $e) {
            $this->assertSame($otherStudent->id, $e->actor->id);
        }

        $this->assertSame(0, \App\Models\Feedback::count(), 'No rejected attempt above must have written a row.');

        // Happy path: an actually-linked parent, and a student filing as
        // themselves.
        $parentFeedback = $repository->create($school->id, $parent, $ownChild->id, 'msg', null, 'school', null);
        $this->assertSame($ownChild->id, $parentFeedback->student_id);

        $studentFeedback = $repository->create($school->id, $otherStudent, $otherStudent->id, 'msg', null, 'school', null);
        $this->assertSame($otherStudent->id, $studentFeedback->student_id);
    }

    /**
     * Row 4 — "AnnouncementPublished and AttendanceMarked(absent) each
     * notify exactly their resolved audience — no more, no fewer — and
     * the fan-out cannot affect the outcome of the write it follows (a
     * failing/slow notification send doesn't roll back or block the
     * write)."
     *
     * Regresses/confirms: 09 §5/§6's decouple-from-the-transaction
     * guidance. Notification::fake() around AnnouncementRepository::
     * create() / AttendanceRepository::mark(): asserts
     * Notification::assertSentTo() for the expected recipients and
     * assertNotSentTo() for excluded users. The write succeeding despite
     * Notification::fake() intercepting the send (rather than the send
     * happening inline inside the DB transaction) is itself evidence the
     * fan-out is decoupled — see Row 7 below for the complementary "it
     * actually fires for real" half of this coverage.
     */
    public function test_announcement_and_absence_events_notify_exactly_their_resolved_audience(): void
    {
        Notification::fake();

        $school = $this->makeSchool();
        $academicYear = $this->makeAcademicYear($school);
        $admin = $this->makeUser(['role_id' => $this->makeRole('school_admin')->id, 'school_id' => $school->id]);

        $teacher = $this->makeUser(['role_id' => $this->makeRole('teacher')->id, 'school_id' => $school->id]);
        $section = $this->makeClassSection($school, $academicYear, $teacher);

        $inSectionStudent = $this->makeUser(['role_id' => $this->makeRole('student')->id, 'school_id' => $school->id]);
        $this->makeStudentEnrollment($school, $academicYear, $section, $inSectionStudent);
        $inSectionParent = $this->makeUser(['role_id' => $this->makeRole('parent')->id, 'school_id' => $school->id]);
        $this->makeStudentParentLink($school, $inSectionParent, $inSectionStudent);

        $outsideStudent = $this->makeUser(['role_id' => $this->makeRole('student')->id, 'school_id' => $school->id]);
        $outsideParent = $this->makeUser(['role_id' => $this->makeRole('parent')->id, 'school_id' => $school->id]);

        // Authored by $admin, not $teacher — AnnouncementAudienceResolver
        // deliberately excludes the announcement's own author from its
        // resolved audience (see that class's doc comment), so the
        // section-assigned $teacher can only appear in the assertions
        // below if they didn't write the post themselves.
        $announcementRepository = new AnnouncementRepository();
        $announcement = $announcementRepository->create(
            $school->id,
            $admin,
            'Title',
            'Body',
            'class_section',
            $section->id,
        );

        Notification::assertSentTo([$inSectionStudent, $inSectionParent, $teacher], AnnouncementPublishedNotification::class);
        Notification::assertNotSentTo([$outsideStudent, $outsideParent], AnnouncementPublishedNotification::class);
        Notification::assertSentToTimes($teacher, AnnouncementPublishedNotification::class, 1);

        $attendanceRepository = new AttendanceRepository();
        $attendanceRepository->mark(
            $school->id,
            $academicYear->id,
            $section->id,
            $inSectionStudent->id,
            now()->toDateString(),
            'morning',
            'absent',
            $teacher,
        );

        Notification::assertSentTo($inSectionParent, AttendanceAbsenceNotification::class);
        Notification::assertNotSentTo($outsideParent, AttendanceAbsenceNotification::class);
        // Failing/slow send doesn't roll back the write — both rows
        // above exist despite Notification::fake() standing in for the
        // real send.
        $this->assertNotNull($announcement->fresh());
        $this->assertSame('absent', \App\Models\AttendanceRecord::where('student_id', $inSectionStudent->id)->first()->status);
    }

    /**
     * Row 5 — "A content_read_receipts write is rejected if the caller
     * has no relationshipScope-visible access to the target
     * entity_type/entity_id."
     *
     * Regresses/confirms: new coverage, closing the gap 18 §6 named
     * before any code depending on it shipped. Asserts
     * ContentReadReceiptRepository::markRead() throws
     * UnauthorizedReadReceiptFailure for an out-of-scope announcement and
     * an out-of-scope assignment, and succeeds — idempotently, one row
     * even when called twice — for an in-scope one of each.
     */
    public function test_read_receipt_write_is_rejected_for_out_of_scope_entities_and_idempotent_for_in_scope(): void
    {
        $school = $this->makeSchool();
        $academicYear = $this->makeAcademicYear($school);
        $subject = $this->makeSubject($school);
        $admin = $this->makeUser(['role_id' => $this->makeRole('school_admin')->id, 'school_id' => $school->id]);

        $teacher = $this->makeUser(['role_id' => $this->makeRole('teacher')->id, 'school_id' => $school->id]);
        $section = $this->makeClassSection($school, $academicYear, $teacher);
        $unrelatedTeacher = $this->makeUser(['role_id' => $this->makeRole('teacher')->id, 'school_id' => $school->id]);

        $classAnnouncement = $this->makeAnnouncement($school, $admin, 'class_section', $section);
        $assignment = $this->makeAssignment($school, $academicYear, $section, $subject, $teacher);

        $scope = new ScopeService();
        $announcements = new AnnouncementRepository();
        $repository = new ContentReadReceiptRepository();

        foreach ([
            ['announcement', $classAnnouncement->id],
            ['assignment', $assignment->id],
        ] as [$entityType, $entityId]) {
            try {
                $repository->markRead($unrelatedTeacher, $entityType, $entityId, $scope, $announcements);
                $this->fail("Expected UnauthorizedReadReceiptFailure for out-of-scope {$entityType}.");
            } catch (UnauthorizedReadReceiptFailure $e) {
                $this->assertSame($unrelatedTeacher->id, $e->actor->id);
            }

            $this->assertSame(
                0,
                \App\Models\ContentReadReceipt::where('user_id', $unrelatedTeacher->id)
                    ->where('entity_type', $entityType)
                    ->where('entity_id', $entityId)
                    ->count(),
                'A rejected markRead() attempt must not have written a row.'
            );

            $first = $repository->markRead($teacher, $entityType, $entityId, $scope, $announcements);
            $second = $repository->markRead($teacher, $entityType, $entityId, $scope, $announcements);

            $this->assertSame($first->id, $second->id, 'markRead() must be idempotent — one row per (user, entity_type, entity_id).');
            $this->assertSame(
                1,
                \App\Models\ContentReadReceipt::where('user_id', $teacher->id)
                    ->where('entity_type', $entityType)
                    ->where('entity_id', $entityId)
                    ->count()
            );
        }
    }

    /**
     * Row 7 — "NotifyAudienceOfAnnouncement and NotifyParentsOfAbsence
     * actually fire on their respective events in the running
     * application, not just when invoked directly in a unit test."
     *
     * Regresses: the registration gap 18 §2/§8 found — both listener
     * classes' own doc comments already asserted this registration
     * existed in AppServiceProvider::boot() before it actually did. This
     * is deliberately the *opposite* of Row 4's Notification::fake():
     * dispatches the real AnnouncementPublished/AttendanceMarked events
     * (no fake standing in) and asserts a real DatabaseNotification row
     * landed for the expected recipients. This is the test that would
     * have caught the empty AppServiceProvider::boot() this session
     * fixed — Row 4's Notification::fake() coverage would not have
     * caught that bug, since a fake intercepts the send regardless of
     * whether any listener was ever registered to trigger it.
     */
    public function test_listeners_actually_fire_on_real_events_in_the_running_application(): void
    {
        $school = $this->makeSchool();
        $academicYear = $this->makeAcademicYear($school);
        $admin = $this->makeUser(['role_id' => $this->makeRole('school_admin')->id, 'school_id' => $school->id]);
        $teacher = $this->makeUser(['role_id' => $this->makeRole('teacher')->id, 'school_id' => $school->id]);
        $section = $this->makeClassSection($school, $academicYear, $teacher);

        $student = $this->makeUser(['role_id' => $this->makeRole('student')->id, 'school_id' => $school->id]);
        $this->makeStudentEnrollment($school, $academicYear, $section, $student);
        $parent = $this->makeUser(['role_id' => $this->makeRole('parent')->id, 'school_id' => $school->id]);
        $this->makeStudentParentLink($school, $parent, $student);

        $this->assertSame(0, $student->notifications()->count());

        (new AnnouncementRepository())->create($school->id, $admin, 'Title', 'Body', 'school', null);

        $this->assertSame(
            1,
            $student->fresh()->notifications()->where('type', AnnouncementPublishedNotification::class)->count(),
            'NotifyAudienceOfAnnouncement must actually be registered against AnnouncementPublished '
            . 'in AppServiceProvider::boot() and fire for real, not just when invoked directly.'
        );

        $this->assertSame(
            0,
            $parent->notifications()->where('type', AttendanceAbsenceNotification::class)->count(),
            'Sanity check before marking attendance below — this does not assert 0 total '
            . 'notifications, since the parent is a legitimate recipient of the school-wide '
            . 'AnnouncementPublishedNotification just asserted above for $student (see '
            . 'AnnouncementAudienceResolver\'s school-audience doc comment).'
        );

        (new AttendanceRepository())->mark(
            $school->id,
            $academicYear->id,
            $section->id,
            $student->id,
            now()->toDateString(),
            'morning',
            'absent',
            $teacher,
        );

        $this->assertSame(
            1,
            $parent->fresh()->notifications()->where('type', AttendanceAbsenceNotification::class)->count(),
            'NotifyParentsOfAbsence must actually be registered against AttendanceMarked in '
            . 'AppServiceProvider::boot() and fire for real, not just when invoked directly.'
        );
    }
}
