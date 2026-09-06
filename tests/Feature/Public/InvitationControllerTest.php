<?php

namespace Tests\Feature\Public;

use App\Repositories\InvitationRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\Concerns\CreatesSchoolOsFixtures;
use Tests\TestCase;

/**
 * Design ref: SchoolOS Account Creation & Onboarding plan, "New:
 * Invitation mechanism"
 *
 * HTTP-layer coverage of the public accept page — request validation,
 * the try/catch that turns InvitationRepository::accept()'s typed
 * failures into a form error instead of a 500, and that a successful
 * accept logs the person in and lands them on the right portal.
 */
class InvitationControllerTest extends TestCase
{
    use RefreshDatabase;
    use CreatesSchoolOsFixtures;

    public function test_accept_page_loads_for_a_guest(): void
    {
        $response = $this->get('/invitations/accept?token=whatever');

        $response->assertOk();
    }

    public function test_an_authenticated_user_is_redirected_away_from_the_accept_page(): void
    {
        $school = $this->makeSchool();
        $teacher = $this->makeUser(['role_id' => $this->makeRole('teacher')->id, 'school_id' => $school->id]);

        $response = $this->actingAs($teacher)->get('/invitations/accept?token=whatever');

        $response->assertRedirect();
        $response->assertStatus(302);
    }

    public function test_setting_a_password_activates_the_account_and_logs_the_teacher_in(): void
    {
        Notification::fake();

        $school = $this->makeSchool();
        $admin = $this->makeUser(['role_id' => $this->makeRole('school_admin')->id, 'school_id' => $school->id]);
        $teacher = $this->makeUser(['role_id' => $this->makeRole('teacher')->id, 'school_id' => $school->id]);

        $issued = (new InvitationRepository())->issue($teacher, $admin);

        $response = $this->post('/invitations/accept', [
            'token' => $issued['plainToken'],
            'password' => 'a-brand-new-password',
            'password_confirmation' => 'a-brand-new-password',
        ]);

        $response->assertRedirect(route('teacher.dashboard'));
        $this->assertAuthenticatedAs($teacher->fresh());
        $this->assertSame('active', $teacher->fresh()->status);
    }

    public function test_a_bad_token_returns_a_form_error_not_a_500(): void
    {
        $response = $this->post('/invitations/accept', [
            'token' => 'not-a-real-token',
            'password' => 'a-brand-new-password',
            'password_confirmation' => 'a-brand-new-password',
        ]);

        $response->assertSessionHasErrors('token');
        $this->assertGuest();
    }

    public function test_password_confirmation_mismatch_fails_validation(): void
    {
        Notification::fake();

        $school = $this->makeSchool();
        $admin = $this->makeUser(['role_id' => $this->makeRole('school_admin')->id, 'school_id' => $school->id]);
        $teacher = $this->makeUser(['role_id' => $this->makeRole('teacher')->id, 'school_id' => $school->id]);

        $issued = (new InvitationRepository())->issue($teacher, $admin);

        $response = $this->post('/invitations/accept', [
            'token' => $issued['plainToken'],
            'password' => 'a-brand-new-password',
            'password_confirmation' => 'does-not-match',
        ]);

        $response->assertSessionHasErrors('password');
        $this->assertGuest();
    }
}
