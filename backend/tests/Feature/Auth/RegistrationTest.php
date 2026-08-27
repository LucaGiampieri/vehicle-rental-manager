<?php

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    //Prepara un database pulito per il test
    use RefreshDatabase;

    public function test_public_registration_is_disabled(): void
    {
        //Prova a registrare un utente con dati validi di esempio
        $response = $this->postJson('/register', [
            'name' => 'Utente di prova',
            'email' => 'utente@example.test',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ]);

        //La rotta non deve essere disponibile
        $response->assertNotFound();

        //La richiesta non deve aver creato un account
        $this->assertDatabaseMissing('users', [
            'email' => 'utente@example.test',
        ]);
    }
}
