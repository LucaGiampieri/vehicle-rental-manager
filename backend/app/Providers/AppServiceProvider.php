<?php

namespace App\Providers;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password as PasswordRule;

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

        // Tutte le nuove password devono contenere almeno 12 caratteri
        PasswordRule::defaults(
            fn () => PasswordRule::min(12)
        );

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
