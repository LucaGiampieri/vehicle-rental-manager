<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    // Permette di richiedere il link di recupero
    public function test_reset_password_link_can_be_requested(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        $this->post('/forgot-password', [
            'email' => $user->email,
        ]);

        Notification::assertSentTo(
            $user,
            ResetPassword::class
        );
    }

    // Permette di reimpostare una password valida
    public function test_password_can_be_reset_with_valid_token(): void
    {
        Notification::fake();

        $user = User::factory()->create();
        $newPassword = 'PasswordDemo!2026';

        $this->post('/forgot-password', [
            'email' => $user->email,
        ]);

        Notification::assertSentTo(
            $user,
            ResetPassword::class,
            function (object $notification) use (
                $user,
                $newPassword
            ): bool {
                $response = $this->post('/reset-password', [
                    'token' => $notification->token,
                    'email' => $user->email,
                    'password' => $newPassword,
                    'password_confirmation' => $newPassword,
                ]);

                $response
                    ->assertSessionHasNoErrors()
                    ->assertOk();

                $this->assertTrue(
                    Hash::check(
                        $newPassword,
                        $user->fresh()->password
                    )
                );

                return true;
            }
        );
    }

    // Rifiuta una nuova password con meno di 12 caratteri
    public function test_password_reset_rejects_short_password(): void
    {
        Notification::fake();

        $user = User::factory()->create();
        $originalPassword = $user->password;

        $this->post('/forgot-password', [
            'email' => $user->email,
        ]);

        Notification::assertSentTo(
            $user,
            ResetPassword::class,
            function (object $notification) use (
                $user,
                $originalPassword
            ): bool {
                $response = $this->post('/reset-password', [
                    'token' => $notification->token,
                    'email' => $user->email,
                    'password' => 'short',
                    'password_confirmation' => 'short',
                ]);

                $response->assertSessionHasErrors([
                    'password',
                ]);

                $this->assertSame(
                    $originalPassword,
                    $user->fresh()->password
                );

                return true;
            }
        );
    }
}
