<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    //Prepara un database pulito per ogni test
    use RefreshDatabase;

    public function test_users_can_login_with_valid_credentials(): void
    {
        //Crea un utente nel database dei test
        $user = User::factory()
            ->create();

        //Invia le credenziali valide tramite una richiesta JSON
        $response = $this->postJson('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        //Verifica che il login sia riuscito senza contenuto nella risposta
        $response->assertNoContent();

        //Verifica che sia stato autenticato proprio questo utente
        $this->assertAuthenticatedAs($user, 'web');
    }

    public function test_users_cannot_login_with_invalid_password(): void
    {
        //Crea un utente nel database dei test
        $user = User::factory()
            ->create();

        //Prova ad accedere con una password sbagliata
        $response = $this->postJson('/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        //Verifica la risposta 422 e l'errore associato alle credenziali
        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);

        //Verifica che l'utente non sia stato autenticato
        $this->assertGuest('web');
    }

    public function test_users_can_logout(): void
    {
        //Crea un utente nel database dei test
        $user = User::factory()
            ->create();

        //Simula un utente autenticato e invia la richiesta di logout
        $response = $this->actingAs($user, 'web')
            ->postJson('/logout');

        //Verifica che il logout sia riuscito
        $response->assertNoContent();

        //Verifica che l'utente non sia più autenticato
        $this->assertGuest('web');
    }

    public function test_guests_cannot_access_the_user_endpoint(): void
    {
        //Richiede i dati dell'utente senza essere autenticati
        $response = $this->getJson('/api/user');

        //Verifica che l'accesso venga rifiutato con risposta 401
        $response->assertUnauthorized();
    }

    public function test_authenticated_users_can_access_the_user_endpoint(): void
    {
        //Crea un utente nel database dei test
        $user = User::factory()
            ->create();

        //Simula un utente autenticato e richiede i suoi dati
        $response = $this->actingAs($user, 'web')
            ->getJson('/api/user');

        //Verifica che la risposta contenga i dati dell'utente corretto
        $response->assertOk()
            ->assertJsonPath('id', $user->id)
            ->assertJsonPath('email', $user->email);

        //Verifica che password e token di accesso persistente non siano esposti
        $response->assertJsonMissingPath('password')
            ->assertJsonMissingPath('remember_token');
    }
}
