<?php

namespace Tests\Feature\ParentPortal;

use App\Notifications\AnnouncementPublishedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesSchoolOsFixtures;
use Tests\TestCase;

/**
 * Design ref: 14-schoolos-implementation-plan.md §5,
 * 18-schoolos-communication-domain-map.md §7 (F27)
 *
 * ParentPortal\NotificationController's HTTP-layer coverage — see
 * Phase5TestGateTest::test_notification_inbox_is_session_scoped_with_no_id_parameter()
 * for the route-table half of this coverage (no {id} parameter exists at
 * all). This is the ordinary per-endpoint HTTP confirmation.
 */
class NotificationControllerTest extends TestCase
{
    use RefreshDatabase;
    use CreatesSchoolOsFixtures;

    public function test_guest_is_redirected_to_login(): void
    {
        $response = $this->get('/parent/notifications');

        $response->assertRedirect('/login');
    }

    public function test_non_parent_role_is_forbidden(): void
    {
        $school = $this->makeSchool();
        $admin = $this->makeUser(['role_id' => $this->makeRole('school_admin')->id, 'school_id' => $school->id]);

        $response = $this->actingAs($admin)->get('/parent/notifications');

        $response->assertForbidden();
    }

    public function test_a_parent_sees_only_their_own_notifications(): void
    {
        $school = $this->makeSchool();
        $parent = $this->makeUser(['role_id' => $this->makeRole('parent')->id, 'school_id' => $school->id]);
        $otherParent = $this->makeUser(['role_id' => $this->makeRole('parent')->id, 'school_id' => $school->id]);

        $announcement = $this->makeAnnouncement($school, $parent, 'school');
        $parent->notify(new AnnouncementPublishedNotification($announcement));
        $otherParent->notify(new AnnouncementPublishedNotification($announcement));

        $response = $this->actingAs($parent)->get('/parent/notifications');

        $response->assertOk();
        $response->assertViewIs('parent.notifications.index');
        $response->assertViewHas('notifications', fn ($notifications) => $notifications->count() === 1);
    }
}
