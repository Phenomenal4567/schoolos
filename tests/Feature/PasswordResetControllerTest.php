<?php

namespace Tests\Feature;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\Concerns\CreatesSchoolOsFixtures;
use Tests\TestCase;

class PasswordResetControllerTest extends TestCase
{
    use RefreshDatabase;
    use CreatesSchoolOsFixtures;

    public function test_forgot_password_screen_is_available(): void
    {
        $this->get('/forgot-password')
            ->assertOk()
            ->assertSee('Forgot Password?')
            ->assertSee('Send Reset Link');
    }

    public function test_password_reset_link_can_be_requested(): void
    {
        Notification::fake();

        $user = $this->makeUser(['email' => 'reset-me@example.test']);

        $this->post('/forgot-password', ['email' => 'reset-me@example.test'])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status');

        Notification::assertSentTo($user, ResetPassword::class);
    }

    public function test_reset_password_uses_query_string_token_route(): void
    {
        $user = $this->makeUser(['email' => 'token-route@example.test']);
        $token = Password::createToken($user);

        $this->get('/reset-password?token=' . urlencode($token) . '&email=token-route@example.test')
            ->assertOk()
            ->assertSee('Reset Password')
            ->assertSee('token-route@example.test');
    }
}
