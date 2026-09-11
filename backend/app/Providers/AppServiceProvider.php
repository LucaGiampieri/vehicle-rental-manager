<?php

namespace App\Providers;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        ResetPassword::createUrlUsing(
            function (object $notifiable, string $token): string {
                $frontendUrl = rtrim(
                    (string) config('app.frontend_url'),
                    '/'
                );

                // Codifica correttamente caratteri come + e @ nell'email
                $email = rawurlencode(
                    $notifiable->getEmailForPasswordReset()
                );

                return "{$frontendUrl}/password-reset/{$token}?email={$email}";
            }
        );
    }
}
