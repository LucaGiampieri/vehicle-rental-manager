<?php

namespace Tests\Feature\Api;

use App\Models\Expense;
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

    //Verifica che un utente autenticato possa creare un veicolo
    public function test_authenticated_user_can_create_vehicle(): void
    {
        //Crea e autentica un utente fittizio
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        //Prepara i dati da inviare all'API
        $vehicleData = [
            'license_plate' => 'AB123CD',
            'brand' => 'Fiat',
            'model' => 'Panda',
            'type' => 'car',
            'parking_units' => 1,
            'year' => 2022,
            'mileage' => 25000,
            'daily_rate' => 45.50,
            'is_active' => true,
        ];

        //Invia una richiesta POST per creare il veicolo
        $response = $this->postJson('/api/vehicles', $vehicleData);

        //Verifica che l'API risponda con 201 Created
        $response->assertCreated();

        //Verifica i dati restituiti dall'API
        $response->assertJsonPath('data.license_plate', 'AB123CD');
        $response->assertJsonPath('data.brand', 'Fiat');
        $response->assertJsonPath('data.model', 'Panda');
        $response->assertJsonPath('data.daily_rate', '45.50');

        //Verifica che il veicolo esista realmente nel database di test
        $this->assertDatabaseHas('vehicles', [
            'license_plate' => 'AB123CD',
            'brand' => 'Fiat',
            'model' => 'Panda',
            'type' => 'car',
            'parking_units' => 1,
            'year' => 2022,
            'mileage' => 25000,
            'daily_rate' => 45.50,
            'is_active' => true,
        ]);
    }

    //Verifica che un utente autenticato possa visualizzare un singolo veicolo
    public function test_authenticated_user_can_view_vehicle(): void
    {
        //Crea e autentica un utente fittizio
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        //Crea il veicolo che verrà richiesto
        $vehicle = Vehicle::factory()->create([
            'license_plate' => 'CD456EF',
            'brand' => 'Ford',
            'model' => 'Transit',
        ]);

        //Invia una richiesta GET usando l'ID del veicolo
        $response = $this->getJson("/api/vehicles/{$vehicle->id}");

        //Verifica che la richiesta sia riuscita
        $response->assertOk();

        //Verifica i dati del veicolo restituito
        $response->assertJsonPath('data.id', $vehicle->id);
        $response->assertJsonPath('data.license_plate', 'CD456EF');
        $response->assertJsonPath('data.brand', 'Ford');
        $response->assertJsonPath('data.model', 'Transit');

        //Verifica che siano presenti anche i conteggi delle relazioni
        $response->assertJsonStructure([
            'data' => [
                'rentals_count',
                'expenses_count',
                'parking_spaces_count',
            ],
        ]);
    }

    //Verifica che un utente autenticato possa modificare un veicolo
    public function test_authenticated_user_can_update_vehicle(): void
    {
        //Crea e autentica un utente fittizio
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        //Crea il veicolo iniziale
        $vehicle = Vehicle::factory()->create([
            'license_plate' => 'EF789GH',
            'brand' => 'Renault',
            'model' => 'Clio',
            'mileage' => 30000,
            'daily_rate' => 40.00,
        ]);

        //Invia solamente i campi che vogliamo modificare
        $response = $this->patchJson(
            "/api/vehicles/{$vehicle->id}",
            [
                'mileage' => 45000,
                'daily_rate' => 48.50,
                'is_active' => false,
            ]
        );

        //Verifica che la modifica sia riuscita
        $response->assertOk();

        //Verifica i nuovi valori restituiti dall'API
        $response->assertJsonPath('data.mileage', 45000);
        $response->assertJsonPath('data.daily_rate', '48.50');
        $response->assertJsonPath('data.is_active', false);

        //Verifica che i campi non inviati siano rimasti invariati
        $response->assertJsonPath('data.license_plate', 'EF789GH');
        $response->assertJsonPath('data.brand', 'Renault');
        $response->assertJsonPath('data.model', 'Clio');

        //Verifica i valori realmente salvati nel database
        $this->assertDatabaseHas('vehicles', [
            'id' => $vehicle->id,
            'license_plate' => 'EF789GH',
            'brand' => 'Renault',
            'model' => 'Clio',
            'mileage' => 45000,
            'daily_rate' => 48.50,
            'is_active' => false,
        ]);
    }

    //Verifica che un utente autenticato possa eliminare un veicolo senza dati collegati
    public function test_authenticated_user_can_delete_vehicle_without_related_data(): void
    {
        //Crea e autentica un utente fittizio
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        //Crea un veicolo senza noleggi, spese o parcheggi collegati
        $vehicle = Vehicle::factory()->create([
            'license_plate' => 'GH123IJ',
        ]);

        //Invia la richiesta DELETE usando l'ID del veicolo
        $response = $this->deleteJson("/api/vehicles/{$vehicle->id}");

        //Verifica che l'eliminazione restituisca 204 No Content
        $response->assertNoContent();

        //Verifica che il veicolo non esista più nel database
        $this->assertDatabaseMissing('vehicles', [
            'id' => $vehicle->id,
            'license_plate' => 'GH123IJ',
        ]);
    }

    //Verifica che non sia possibile creare un veicolo con dati non validi
    public function test_vehicle_creation_requires_valid_data(): void
    {
        //Crea e autentica un utente fittizio
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        //Invia dati volutamente non validi
        $response = $this->postJson('/api/vehicles', [
            'license_plate' => '',
            'brand' => '',
            'model' => '',
            'type' => '',
            'parking_units' => 0,
            'year' => 1800,
            'mileage' => -1,
            'daily_rate' => -10,
            'is_active' => 'non-booleano',
        ]);

        //Verifica che Laravel risponda con 422 Unprocessable Entity
        $response->assertUnprocessable();

        //Verifica gli errori di validazione restituiti
        $response->assertJsonValidationErrors([
            'license_plate',
            'brand',
            'model',
            'type',
            'parking_units',
            'year',
            'mileage',
            'daily_rate',
            'is_active',
        ]);

        //Verifica che nessun veicolo sia stato inserito
        $this->assertDatabaseCount('vehicles', 0);
    }

    //Verifica che un veicolo con dati collegati non possa essere eliminato
    public function test_vehicle_with_related_expense_cannot_be_deleted(): void
    {
        //Crea e autentica un utente fittizio
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        //Crea il veicolo che proveremo a eliminare
        $vehicle = Vehicle::factory()->create([
            'license_plate' => 'IJ456KL',
        ]);

        //Crea una spesa collegata espressamente a questo veicolo
        Expense::factory()
            ->for($vehicle)
            ->create();

        //Prova a eliminare il veicolo
        $response = $this->deleteJson("/api/vehicles/{$vehicle->id}");

        //L'API deve impedire l'eliminazione con 409 Conflict
        $response->assertConflict();

        //Verifica il messaggio restituito
        $response->assertJsonPath(
            'message',
            'Il veicolo non può essere eliminato perché possiede noleggi, spese o celle dell’autorimessa collegate. Rimuovilo dall’autorimessa oppure disattivalo.'
        );

        //Verifica che il veicolo sia ancora presente nel database
        $this->assertDatabaseHas('vehicles', [
            'id' => $vehicle->id,
            'license_plate' => 'IJ456KL',
        ]);

        //Verifica che anche la spesa collegata sia ancora presente
        $this->assertDatabaseHas('expenses', [
            'vehicle_id' => $vehicle->id,
        ]);
    }
}
