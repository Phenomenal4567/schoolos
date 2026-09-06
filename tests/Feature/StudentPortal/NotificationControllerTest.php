<?php

namespace Tests\Feature\StudentPortal;

use App\Notifications\AnnouncementPublishedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesSchoolOsFixtures;
use Tests\TestCase;

/**
 * Design ref: 14-schoolos-implementation-plan.md §5,
 * 18-schoolos-communication-domain-map.md §7 (F27)
 *
 * StudentPortal\NotificationController's HTTP-layer coverage — see
 * ParentPortal\NotificationControllerTest's doc comment for the shared
 * shape.
 */
class NotificationControllerTest extends TestCase
{
    use RefreshDatabase;
    use CreatesSchoolOsFixtures;

    public function test_guest_is_redirected_to_login(): void
    {
        $response = $this->get('/student/notifications');

        $response->assertRedirect('/login');
    }

    public function test_non_student_role_is_forbidden(): void
    {
        $school = $this->makeSchool();
        $admin = $this->makeUser(['role_id' => $this->makeRole('school_admin')->id, 'school_id' => $school->id]);

        $response = $this->actingAs($admin)->get('/student/notifications');

        $response->assertForbidden();
    }

    public function test_a_student_sees_only_their_own_notifications(): void
    {
        $school = $this->makeSchool();
        $student = $this->makeUser(['role_id' => $this->makeRole('student')->id, 'school_id' => $school->id]);
        $otherStudent = $this->makeUser(['role_id' => $this->makeRole('student')->id, 'school_id' => $school->id]);

        $announcement = $this->makeAnnouncement($school, $student, 'school');
        $student->notify(new AnnouncementPublishedNotification($announcement));
        $otherStudent->notify(new AnnouncementPublishedNotification($announcement));

        $response = $this->actingAs($student)->get('/student/notifications');

        $response->assertOk();
        $response->assertViewIs('student.notifications.index');
        $response->assertViewHas('notifications', fn ($notifications) => $notifications->count() === 1);
    }
}
