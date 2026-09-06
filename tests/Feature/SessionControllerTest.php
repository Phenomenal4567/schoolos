<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\Concerns\CreatesSchoolOsFixtures;
use Tests\TestCase;

/**
 * Design ref: 12-schoolos-architecture.md §2, 14-schoolos-implementation-plan.md §0
 *
 * Covers the shared web login/logout controller built for Phase 2's parent
 * dashboard. test_successful_login_actually_authenticates_the_session below
 * is the regression test for the bug found while building this: previously
 * AuthenticationService::issueSession()'s web branch called
 * session()->regenerate() but never Auth::login(), so a "successful" login
 * never actually attached a user to the session — any 'auth'-gated route
 * would have rejected the very user who just logged in.
 */
class SessionControllerTest extends TestCase
{
    use RefreshDatabase;
    use CreatesSchoolOsFixtures;

    public function test_successful_login_actually_authenticates_the_session(): void
    {
        $school = $this->makeSchool();
        $parentRole = $this->makeRole('parent');
        $parent = $this->makeUser([
            'school_id' => $school->id,
            'role_id' => $parentRole->id,
            'email' => 'login-check-parent@example.test',
        ]);

        $this->assertGuest();

        $response = $this->post('/login', [
            'identifier' => 'login-check-parent@example.test',
            'password' => 'correct-password',
        ]);

        // The actual regression assertion: Auth::check() must be true and
        // must resolve to the user who just logged in, not just "the
        // response redirected somewhere that looked like success."
        $this->assertAuthenticatedAs($parent);
        $this->assertTrue(Auth::check());

        $response->assertRedirect(route('parent.dashboard'));
    }

    public function test_wrong_password_does_not_authenticate_and_shows_error(): void
    {
        $this->makeUser(['email' => 'wrongpass-web@example.test']);

        $response = $this->post('/login', [
            'identifier' => 'wrongpass-web@example.test',
            'password' => 'not-the-right-password',
        ]);

        $this->assertGuest();
        $response->assertSessionHasErrors('identifier');
    }

    public function test_suspended_school_login_shows_typed_error_not_generic_credentials_message(): void
    {
        $school = $this->makeSchool(['status' => 'suspended']);
        $this->makeUser(['school_id' => $school->id, 'email' => 'suspended-web@example.test']);

        $response = $this->post('/login', [
            'identifier' => 'suspended-web@example.test',
            'password' => 'correct-password',
        ]);

        $this->assertGuest();
        $response->assertSessionHasErrors('identifier');
        $this->assertStringContainsString(
            'suspended',
            session('errors')->first('identifier')
        );
    }

    /**
     * Design ref: SchoolOS Account Creation & Onboarding plan, §12
     * (Security Requirements — "disabled accounts cannot authenticate").
     */
    public function test_disabled_account_login_shows_typed_error_not_generic_credentials_message(): void
    {
        $this->makeUser(['email' => 'disabled-web@example.test', 'password' => 'correct-password', 'status' => 'inactive']);

        $response = $this->post('/login', [
            'identifier' => 'disabled-web@example.test',
            'password' => 'correct-password',
        ]);

        $this->assertGuest();
        $response->assertSessionHasErrors('identifier');
        $this->assertStringContainsString('disabled', session('errors')->first('identifier'));
    }

    public function test_invited_account_login_tells_the_person_to_check_their_invitation(): void
    {
        $this->makeUser(['email' => 'invited-web@example.test', 'status' => 'invited']);

        $response = $this->post('/login', [
            'identifier' => 'invited-web@example.test',
            'password' => 'anything',
        ]);

        $this->assertGuest();
        $response->assertSessionHasErrors('identifier');
        $this->assertStringContainsString('invitation', session('errors')->first('identifier'));
    }

    public function test_logout_clears_the_authenticated_session(): void
    {
        $parent = $this->makeUser(['role_id' => $this->makeRole('parent')->id]);
        $this->actingAs($parent);

        $this->assertAuthenticatedAs($parent);

        $response = $this->post('/logout');

        $this->assertGuest();
        $response->assertRedirect(route('login'));
    }
}
