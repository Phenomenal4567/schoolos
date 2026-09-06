<?php

namespace Tests\Feature\TeacherPortal;

use App\Notifications\AnnouncementPublishedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesSchoolOsFixtures;
use Tests\TestCase;

/**
 * Design ref: 14-schoolos-implementation-plan.md §5,
 * 18-schoolos-communication-domain-map.md §7 (F27)
 *
 * TeacherPortal\NotificationController's HTTP-layer coverage — see
 * ParentPortal\NotificationControllerTest's doc comment for the shared
 * shape.
 */
class NotificationControllerTest extends TestCase
{
    use RefreshDatabase;
    use CreatesSchoolOsFixtures;

    public function test_guest_is_redirected_to_login(): void
    {
        $response = $this->get('/teacher/notifications');

        $response->assertRedirect('/login');
    }

    public function test_non_teacher_role_is_forbidden(): void
    {
        $school = $this->makeSchool();
        $admin = $this->makeUser(['role_id' => $this->makeRole('school_admin')->id, 'school_id' => $school->id]);

        $response = $this->actingAs($admin)->get('/teacher/notifications');

        $response->assertForbidden();
    }

    public function test_a_teacher_sees_only_their_own_notifications(): void
    {
        $school = $this->makeSchool();
        $teacher = $this->makeUser(['role_id' => $this->makeRole('teacher')->id, 'school_id' => $school->id]);
        $otherTeacher = $this->makeUser(['role_id' => $this->makeRole('teacher')->id, 'school_id' => $school->id]);

        $announcement = $this->makeAnnouncement($school, $teacher, 'school');
        $teacher->notify(new AnnouncementPublishedNotification($announcement));
        $otherTeacher->notify(new AnnouncementPublishedNotification($announcement));

        $response = $this->actingAs($teacher)->get('/teacher/notifications');

        $response->assertOk();
        $response->assertViewIs('teacher.notifications.index');
        $response->assertViewHas('notifications', fn ($notifications) => $notifications->count() === 1);
    }

    /**
     * Closes the "no pagination on notifications" gap — see
     * TeacherPortal\AnnouncementControllerTest::test_the_announcements_index_is_paginated()
     * for the identical shape applied to the sibling list.
     */
    public function test_the_notifications_index_is_paginated(): void
    {
        $school = $this->makeSchool();
        $teacher = $this->makeUser(['role_id' => $this->makeRole('teacher')->id, 'school_id' => $school->id]);
        $announcement = $this->makeAnnouncement($school, $teacher, 'school');

        for ($i = 0; $i < 25; $i++) {
            $teacher->notify(new AnnouncementPublishedNotification($announcement));
        }

        $firstPage = $this->actingAs($teacher)->get('/teacher/notifications');
        $firstPage->assertOk();
        $firstPage->assertViewHas('notifications', fn ($notifications) => $notifications->count() === 20
            && $notifications->total() === 25);

        $secondPage = $this->actingAs($teacher)->get('/teacher/notifications?page=2');
        $secondPage->assertOk();
        $secondPage->assertViewHas('notifications', fn ($notifications) => $notifications->count() === 5);
    }
}
