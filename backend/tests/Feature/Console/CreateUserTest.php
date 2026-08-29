<?php

namespace Tests\Feature\Console;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CreateUserTest extends TestCase
{
    //Prepara un database temporaneo pulito per ogni test
    use RefreshDatabase;

    public function test_it_creates_a_user_with_a_hashed_password(): void
    {
        //Simula le risposte inserite nel terminale
        $this->artisan('app:create-user')
            ->expectsQuestion('Nome', '  Gestore demo  ')
            ->expectsQuestion('Email', '  GESTORE@example.test  ')
            ->expectsQuestion(
                'Password (almeno 12 caratteri)',
                'PasswordDemo!2026'
            )
            ->expectsQuestion(
                'Conferma password',
                'PasswordDemo!2026'
            )
            ->expectsOutput('Account di gestione creato.')
            ->assertExitCode(0);

        //Rilegge l'utente attraverso l'email normalizzata
        $user = User::query()
            ->where('email', 'gestore@example.test')
            ->firstOrFail();

        //Verifica che esista un solo utente e che il nome sia stato ripulito
        $this->assertDatabaseCount('users', 1);
        $this->assertSame('Gestore demo', $user->name);

        //Verifica che l'hash salvato corrisponda alla password fittizia
        $this->assertTrue(
            Hash::check('PasswordDemo!2026', $user->password)
        );
    }

    public function test_it_rejects_duplicate_emails_without_changing_the_user(): void
    {
        //Crea un account già esistente nel database temporaneo
        $user = User::factory()
            ->create([
                'name' => 'Gestore originale',
                'email' => 'gestore@example.test',
            ]);

        //Conserva l'hash originale per verificare che non venga sostituito
        $originalPassword = $user->password;

        //Prova a creare un altro account con la stessa email
        $this->artisan('app:create-user')
            ->expectsQuestion('Nome', 'Altro gestore')
            ->expectsQuestion('Email', 'GESTORE@example.test')
            ->expectsQuestion(
                'Password (almeno 12 caratteri)',
                'PasswordDiversa!2026'
            )
            ->expectsQuestion(
                'Conferma password',
                'PasswordDiversa!2026'
            )
            ->expectsOutput('Esiste già un account con questa email.')
            ->assertExitCode(1);

        //Rilegge dal database l'account esistente
        $user->refresh();

        //Verifica che non siano stati creati duplicati
        $this->assertDatabaseCount('users', 1);

        //Verifica che nome e password originali non siano stati modificati
        $this->assertSame('Gestore originale', $user->name);
        $this->assertSame($originalPassword, $user->password);
    }

    public function test_it_rejects_passwords_that_do_not_match(): void
    {
        //Simula una conferma diversa dalla password scelta
        $this->artisan('app:create-user')
            ->expectsQuestion('Nome', 'Gestore demo')
            ->expectsQuestion('Email', 'gestore@example.test')
            ->expectsQuestion(
                'Password (almeno 12 caratteri)',
                'PasswordDemo!2026'
            )
            ->expectsQuestion(
                'Conferma password',
                'PasswordDiversa!2026'
            )
            ->expectsOutput('Le due password non coincidono.')
            ->assertExitCode(1);

        //Verifica che non sia stato creato alcun account
        $this->assertDatabaseCount('users', 0);
    }

    public function test_it_rejects_passwords_longer_than_72_bytes(): void
    {
        //Crea una password fittizia di 37 caratteri ma 74 byte in UTF-8
        $password = str_repeat('è', 37);

        //Prova a creare un account con la password troppo lunga
        $this->artisan('app:create-user')
            ->expectsQuestion('Nome', 'Gestore demo')
            ->expectsQuestion('Email', 'gestore@example.test')
            ->expectsQuestion(
                'Password (almeno 12 caratteri)',
                $password
            )
            ->expectsQuestion(
                'Conferma password',
                $password
            )
            ->expectsOutput('La password non può superare 72 byte.')
            ->assertExitCode(1);

        //Verifica che non sia stato creato alcun account
        $this->assertDatabaseCount('users', 0);
    }
}
