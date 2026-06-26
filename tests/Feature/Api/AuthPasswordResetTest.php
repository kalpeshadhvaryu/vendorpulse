<?php

namespace Tests\Feature\Api;

use App\Models\User;
use App\Services\Mail\ManagementEmailService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Password;
use Mockery;
use Tests\TestCase;

class AuthPasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_forgot_password_returns_success_without_leaking_unknown_email(): void
    {
        $response = $this->postJson('/api/v1/auth/forgot-password', [
            'email' => 'missing@example.test',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath(
                'message',
                'If an account exists for that email, a password reset link has been sent.'
            );
    }

    public function test_forgot_password_sends_reset_email_for_existing_user(): void
    {
        $user = User::factory()->create([
            'email' => 'member@example.test',
        ]);

        $mail = Mockery::mock(ManagementEmailService::class);
        $mail->shouldReceive('sendPasswordResetEmail')
            ->once()
            ->with(
                Mockery::on(fn (User $candidate) => $candidate->is($user)),
                Mockery::type('string'),
            );

        $this->app->instance(ManagementEmailService::class, $mail);

        $response = $this->postJson('/api/v1/auth/forgot-password', [
            'email' => $user->email,
        ]);

        $response->assertOk()->assertJsonPath('success', true);
    }

    public function test_reset_password_updates_credentials_and_revokes_tokens(): void
    {
        $user = User::factory()->create([
            'email' => 'reset@example.test',
            'password' => 'old-password-1',
        ]);

        $user->createToken('web');
        $this->assertSame(1, $user->tokens()->count());

        $token = Password::broker()->createToken($user);

        $response = $this->postJson('/api/v1/auth/reset-password', [
            'email' => 'reset@example.test',
            'token' => $token,
            'password' => 'new-password-1',
            'password_confirmation' => 'new-password-1',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Password reset successfully. You can sign in with your new password.');

        $user->refresh();
        $this->assertTrue(password_verify('new-password-1', (string) $user->password));
        $this->assertSame(0, $user->tokens()->count());

        $this->postJson('/api/v1/auth/login', [
            'email' => 'reset@example.test',
            'password' => 'new-password-1',
        ])->assertOk();
    }

    public function test_reset_password_rejects_invalid_token(): void
    {
        User::factory()->create([
            'email' => 'reset@example.test',
        ]);

        $response = $this->postJson('/api/v1/auth/reset-password', [
            'email' => 'reset@example.test',
            'token' => 'invalid-token',
            'password' => 'new-password-1',
            'password_confirmation' => 'new-password-1',
        ]);

        $response->assertStatus(422)->assertJsonPath('success', false);
    }
}
