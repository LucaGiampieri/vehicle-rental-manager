<?php

namespace Tests\Feature\Api;

use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class VehicleApiTest extends TestCase
{
    use RefreshDatabase;

    //Verifica che un utente non autenticato non possa leggere i veicoli
    public function test_guest_cannot_access_vehicles(): void
    {
        //Invia una richiesta senza effettuare il login
        $response = $this->getJson('/api/vehicles');

        //La richiesta deve essere respinta con il codice 401 Unauthorized
        $response->assertUnauthorized();
    }

    //Verifica che un utente autenticato possa visualizzare i veicoli
    public function test_authenticated_user_can_list_vehicles(): void
    {
        //Crea un utente fittizio nel database di test
        $user = User::factory()->create();

        //Autentica l'utente attraverso Sanctum
        Sanctum::actingAs($user);

        //Crea tre veicoli fittizi nel database di test
        Vehicle::factory()
            ->count(3)
            ->create();

        //Richiede l'elenco dei veicoli attraverso l'API
        $response = $this->getJson('/api/vehicles');

        //Verifica che la richiesta sia riuscita
        $response->assertOk();

        //Verifica che la proprietà data contenga tre veicoli
        $response->assertJsonCount(3, 'data');

        //Verifica la struttura della risposta JSON
        $response->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'license_plate',
                    'brand',
                    'model',
                    'type',
                    'parking_units',
                    'year',
                    'mileage',
                    'daily_rate',
                    'is_active',
                    'rentals_count',
                    'expenses_count',
                    'parking_spaces_count',
                    'created_at',
                    'updated_at',
                ],
            ],
            'links',
            'meta',
        ]);
    }
}
