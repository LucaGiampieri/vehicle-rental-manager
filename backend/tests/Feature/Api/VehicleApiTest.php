<?php

namespace Tests\Feature\Api;

use App\Models\Expense;
use App\Models\ParkingSpace;
use App\Models\Rental;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleImage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class VehicleApiTest extends TestCase
{
    use RefreshDatabase;

    // Verifica che un utente non autenticato non possa leggere i veicoli
    public function test_guest_cannot_access_vehicles(): void
    {
        // Invia una richiesta senza effettuare il login
        $response = $this->getJson('/api/vehicles');
        // La richiesta deve essere respinta con il codice 401 Unauthorized
        $response->assertUnauthorized();
    }

    // Verifica che un utente autenticato possa visualizzare i veicoli
    public function test_authenticated_user_can_list_vehicles(): void
    {
        // Crea un utente fittizio nel database di test
        $user = User::factory()->create();
        // Autentica l'utente attraverso Sanctum
        Sanctum::actingAs($user);
        // Crea tre veicoli fittizi nel database di test
        Vehicle::factory()
            ->count(3)
            ->create();
        // Richiede l'elenco dei veicoli attraverso l'API
        $response = $this->getJson('/api/vehicles');
        // Verifica che la richiesta sia riuscita
        $response->assertOk();
        // Verifica che la proprietà data contenga tre veicoli
        $response->assertJsonCount(3, 'data');
        // Verifica la struttura della risposta JSON
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

    // Verifica la ricerca per targa, marca oppure modello
    public function test_vehicles_can_be_searched(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $expectedVehicle = Vehicle::factory()->create([
            'license_plate' => 'AA111BB',
            'brand' => 'Fiat',
            'model' => 'Panda',
        ]);
        Vehicle::factory()->create([
            'license_plate' => 'CC222DD',
            'brand' => 'Ford',
            'model' => 'Transit',
        ]);
        $response = $this->getJson(
            '/api/vehicles?search=panda'
        );
        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath(
            'data.0.id',
            $expectedVehicle->id
        );
    }

    // Verifica i filtri per tipo e stato del veicolo
    public function test_vehicles_can_be_filtered_by_type_and_active_status(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $expectedVehicle = Vehicle::factory()->create([
            'license_plate' => 'EE333FF',
            'type' => Vehicle::TYPE_CAR,
            'is_active' => false,
        ]);
        Vehicle::factory()->create([
            'license_plate' => 'GG444HH',
            'type' => Vehicle::TYPE_CAR,
            'is_active' => true,
        ]);
        Vehicle::factory()->create([
            'license_plate' => 'II555JJ',
            'type' => Vehicle::TYPE_VAN,
            'is_active' => false,
        ]);
        $response = $this->getJson(
            '/api/vehicles?type=car&is_active=false'
        );
        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath(
            'data.0.id',
            $expectedVehicle->id
        );
        $response->assertJsonPath(
            'data.0.is_active',
            false
        );
    }

    // Verifica che sia possibile scegliere la dimensione della pagina
    public function test_vehicle_list_supports_custom_pagination(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        Vehicle::factory()
            ->count(5)
            ->create();
        $response = $this->getJson(
            '/api/vehicles?per_page=2'
        );
        $response->assertOk();
        $response->assertJsonCount(2, 'data');
        $response->assertJsonPath('meta.per_page', 2);
        $response->assertJsonPath('meta.total', 5);
        $response->assertJsonPath('meta.last_page', 3);
    }

    // Verifica che i filtri non validi vengano rifiutati
    public function test_vehicle_filters_require_valid_values(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $response = $this->getJson(
            '/api/vehicles?type=spaceship&is_active=maybe&per_page=101'
        );
        $response->assertUnprocessable();
        $response->assertJsonValidationErrors([
            'type',
            'is_active',
            'per_page',
        ]);
    }

    // Verifica che un utente autenticato possa creare un veicolo
    public function test_authenticated_user_can_create_vehicle(): void
    {
        // Crea e autentica un utente fittizio
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        // Prepara i dati da inviare all'API
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
        // Invia una richiesta POST per creare il veicolo
        $response = $this->postJson('/api/vehicles', $vehicleData);
        // Verifica che l'API risponda con 201 Created
        $response->assertCreated();
        // Verifica i dati restituiti dall'API
        $response->assertJsonPath('data.license_plate', 'AB123CD');
        $response->assertJsonPath('data.brand', 'Fiat');
        $response->assertJsonPath('data.model', 'Panda');
        $response->assertJsonPath('data.daily_rate', '45.50');
        // Verifica che il veicolo esista realmente nel database di test
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

    // Verifica che un utente autenticato possa visualizzare un singolo veicolo
    public function test_authenticated_user_can_view_vehicle(): void
    {
        // Crea e autentica un utente fittizio
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        // Crea il veicolo che verrà richiesto
        $vehicle = Vehicle::factory()->create([
            'license_plate' => 'CD456EF',
            'brand' => 'Ford',
            'model' => 'Transit',
        ]);
        // Invia una richiesta GET usando l'ID del veicolo
        $response = $this->getJson("/api/vehicles/{$vehicle->id}");
        // Verifica che la richiesta sia riuscita
        $response->assertOk();
        // Verifica i dati del veicolo restituito
        $response->assertJsonPath('data.id', $vehicle->id);
        $response->assertJsonPath('data.license_plate', 'CD456EF');
        $response->assertJsonPath('data.brand', 'Ford');
        $response->assertJsonPath('data.model', 'Transit');
        // Verifica che siano presenti anche i conteggi delle relazioni
        $response->assertJsonStructure([
            'data' => [
                'rentals_count',
                'expenses_count',
                'parking_spaces_count',
            ],
        ]);
    }

    // Verifica che un utente autenticato possa modificare un veicolo
    public function test_authenticated_user_can_update_vehicle(): void
    {
        // Crea e autentica un utente fittizio
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        // Crea il veicolo iniziale
        $vehicle = Vehicle::factory()->create([
            'license_plate' => 'EF789GH',
            'brand' => 'Renault',
            'model' => 'Clio',
            'mileage' => 30000,
            'daily_rate' => 40.00,
        ]);
        // Invia solamente i campi che vogliamo modificare
        $response = $this->patchJson(
            "/api/vehicles/{$vehicle->id}",
            [
                'mileage' => 45000,
                'daily_rate' => 48.50,
                'is_active' => false,
            ]
        );
        // Verifica che la modifica sia riuscita
        $response->assertOk();
        // Verifica i nuovi valori restituiti dall'API
        $response->assertJsonPath('data.mileage', 45000);
        $response->assertJsonPath('data.daily_rate', '48.50');
        $response->assertJsonPath('data.is_active', false);
        // Verifica che i campi non inviati siano rimasti invariati
        $response->assertJsonPath('data.license_plate', 'EF789GH');
        $response->assertJsonPath('data.brand', 'Renault');
        $response->assertJsonPath('data.model', 'Clio');
        // Verifica i valori realmente salvati nel database
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

    // Verifica che il chilometraggio generale non possa diminuire
    public function test_vehicle_mileage_cannot_be_reduced(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $vehicle = Vehicle::factory()->create([
            'mileage' => 50000,
        ]);
        // Prova a inserire un chilometraggio inferiore
        $response = $this->patchJson(
            "/api/vehicles/{$vehicle->id}",
            [
                'mileage' => 49999,
            ]
        );
        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['mileage']);
        // Il valore originale deve essere rimasto invariato
        $this->assertSame(
            50000,
            $vehicle->fresh()->mileage
        );
    }

    // Verifica che un veicolo parcheggiato non possa cambiare dimensioni
    public function test_parked_vehicle_cannot_change_parking_units(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $vehicle = Vehicle::factory()->create([
            'parking_units' => 2,
        ]);
        // Simula le due celle occupate dal veicolo
        ParkingSpace::factory()
            ->count(2)
            ->create([
                'vehicle_id' => $vehicle->id,
            ]);
        // Prova a cambiare le dimensioni mentre il mezzo è parcheggiato
        $response = $this->patchJson(
            "/api/vehicles/{$vehicle->id}",
            [
                'parking_units' => 4,
            ]
        );
        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['parking_units']);
        // Il veicolo deve continuare a richiedere due celle
        $this->assertSame(
            2,
            $vehicle->fresh()->parking_units
        );
    }

    // Verifica che un utente autenticato possa eliminare un veicolo senza dati collegati
    public function test_authenticated_user_can_delete_vehicle_without_related_data(): void
    {
        // Crea e autentica un utente fittizio
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        // Crea un veicolo senza noleggi, spese o parcheggi collegati
        $vehicle = Vehicle::factory()->create([
            'license_plate' => 'GH123IJ',
        ]);
        // Invia la richiesta DELETE usando l'ID del veicolo
        $response = $this->deleteJson("/api/vehicles/{$vehicle->id}");
        // Verifica che l'eliminazione restituisca 204 No Content
        $response->assertNoContent();
        // Verifica che il veicolo non esista più nel database
        $this->assertDatabaseMissing('vehicles', [
            'id' => $vehicle->id,
            'license_plate' => 'GH123IJ',
        ]);
    }

    // Verifica che non sia possibile creare un veicolo con dati non validi
    public function test_vehicle_creation_requires_valid_data(): void
    {
        // Crea e autentica un utente fittizio
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        // Invia dati volutamente non validi
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
        // Verifica che Laravel risponda con 422 Unprocessable Entity
        $response->assertUnprocessable();
        // Verifica gli errori di validazione restituiti
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
        // Verifica che nessun veicolo sia stato inserito
        $this->assertDatabaseCount('vehicles', 0);
    }

    // Verifica che un veicolo con dati collegati non possa essere eliminato
    public function test_vehicle_with_related_expense_cannot_be_deleted(): void
    {
        // Crea e autentica un utente fittizio
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        // Crea il veicolo che proveremo a eliminare
        $vehicle = Vehicle::factory()->create([
            'license_plate' => 'IJ456KL',
        ]);
        // Crea una spesa collegata espressamente a questo veicolo
        Expense::factory()
            ->for($vehicle)
            ->create();
        // Prova a eliminare il veicolo
        $response = $this->deleteJson("/api/vehicles/{$vehicle->id}");
        // L'API deve impedire l'eliminazione con 409 Conflict
        $response->assertConflict();
        // Verifica il messaggio restituito
        $response->assertJsonPath(
            'message',
            'Il veicolo non può essere eliminato perché possiede noleggi, spese o celle dell’autorimessa collegate. Rimuovilo dall’autorimessa oppure disattivalo.'
        );
        // Verifica che il veicolo sia ancora presente nel database
        $this->assertDatabaseHas('vehicles', [
            'id' => $vehicle->id,
            'license_plate' => 'IJ456KL',
        ]);
        // Verifica che anche la spesa collegata sia ancora presente
        $this->assertDatabaseHas('expenses', [
            'vehicle_id' => $vehicle->id,
        ]);
    }

    // Restituisce copertina, numero immagini e galleria del veicolo
    public function test_vehicle_responses_include_images(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $vehicle = Vehicle::factory()->create();
        $primaryImage = VehicleImage::factory()
            ->for($vehicle)
            ->primary()
            ->create([
                'sort_order' => 1,
            ]);
        $secondaryImage = VehicleImage::factory()
            ->for($vehicle)
            ->create([
                'sort_order' => 2,
            ]);
        // Nell’elenco vengono restituite copertina e quantità
        $listResponse = $this->getJson('/api/vehicles');
        $listResponse->assertOk();
        $listResponse->assertJsonPath(
            'data.0.primary_image.id',
            $primaryImage->id
        );
        $listResponse->assertJsonPath(
            'data.0.images_count',
            2
        );
        // Nella scheda viene restituita anche la galleria completa
        $showResponse = $this->getJson(
            "/api/vehicles/{$vehicle->id}"
        );
        $showResponse->assertOk();
        $showResponse->assertJsonPath(
            'data.primary_image.id',
            $primaryImage->id
        );
        $showResponse->assertJsonPath(
            'data.images_count',
            2
        );
        $showResponse->assertJsonCount(
            2,
            'data.images'
        );
        $showResponse->assertJsonPath(
            'data.images.0.id',
            $primaryImage->id
        );
        $showResponse->assertJsonPath(
            'data.images.1.id',
            $secondaryImage->id
        );
    }

    // Eliminando un veicolo vengono eliminati anche i file delle immagini
    public function test_deleting_vehicle_removes_its_image_files(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $vehicle = Vehicle::factory()->create();
        $path = "vehicles/{$vehicle->id}/vehicle.jpg";
        Storage::disk('public')->put(
            $path,
            'image content'
        );
        $image = VehicleImage::factory()
            ->for($vehicle)
            ->primary()
            ->create([
                'path' => $path,
            ]);
        $response = $this->deleteJson(
            "/api/vehicles/{$vehicle->id}"
        );
        $response->assertNoContent();
        $this->assertDatabaseMissing('vehicles', [
            'id' => $vehicle->id,
        ]);
        $this->assertDatabaseMissing('vehicle_images', [
            'id' => $image->id,
        ]);
        Storage::disk('public')->assertMissing($path);
    }

    // Distingue un veicolo disponibile da uno disattivato
    public function test_vehicle_response_includes_basic_operational_status(): void
    {
        $this->authenticateUser();
        $availableVehicle = Vehicle::factory()->create([
            'is_active' => true,
        ]);
        $inactiveVehicle = Vehicle::factory()->create([
            'is_active' => false,
        ]);
        $this->getJson("/api/vehicles/{$availableVehicle->id}")
            ->assertOk()
            ->assertJsonPath(
                'data.operational_status',
                Vehicle::OPERATIONAL_STATUS_AVAILABLE
            )
            ->assertJsonPath('data.active_rental', null)
            ->assertJsonPath('data.next_reservation', null);
        $this->getJson("/api/vehicles/{$inactiveVehicle->id}")
            ->assertOk()
            ->assertJsonPath(
                'data.operational_status',
                Vehicle::OPERATIONAL_STATUS_INACTIVE
            );
    }

    // Restituisce il noleggio attivo e il relativo cliente
    public function test_vehicle_response_includes_active_rental(): void
    {
        $this->authenticateUser();
        $vehicle = Vehicle::factory()->create();
        $rental = Rental::factory()
            ->for($vehicle)
            ->create([
                'status' => Rental::STATUS_ACTIVE,
                'starts_at' => now()->subDay(),
                'actual_starts_at' => now()->subDay(),
                'expected_ends_at' => now()->addDay(),
                'start_mileage' => $vehicle->mileage,
            ]);
        $response = $this->getJson(
            "/api/vehicles/{$vehicle->id}"
        );
        $response->assertOk();
        $response->assertJsonPath(
            'data.operational_status',
            Vehicle::OPERATIONAL_STATUS_RENTED
        );
        $response->assertJsonPath(
            'data.active_rental.id',
            $rental->id
        );
        $response->assertJsonPath(
            'data.active_rental.customer.id',
            $rental->customer_id
        );
        $response->assertJsonPath('data.next_reservation', null);
    }

    // Restituisce la prenotazione futura più vicina
    public function test_vehicle_response_includes_next_reservation(): void
    {
        $this->authenticateUser();
        $vehicle = Vehicle::factory()->create();
        $reservation = Rental::factory()
            ->for($vehicle)
            ->create([
                'status' => Rental::STATUS_RESERVED,
                'starts_at' => now()->addDay(),
                'expected_ends_at' => now()->addDays(3),
                'actual_starts_at' => null,
                'actual_ends_at' => null,
                'start_mileage' => null,
                'end_mileage' => null,
            ]);
        $response = $this->getJson(
            "/api/vehicles/{$vehicle->id}"
        );
        $response->assertOk();
        $response->assertJsonPath(
            'data.operational_status',
            Vehicle::OPERATIONAL_STATUS_RESERVED
        );
        $response->assertJsonPath(
            'data.next_reservation.id',
            $reservation->id
        );
        $response->assertJsonPath(
            'data.next_reservation.customer.id',
            $reservation->customer_id
        );
        $response->assertJsonPath('data.active_rental', null);
    }

    // Una vecchia prenotazione scaduta non rende il veicolo prenotato
    public function test_expired_reservation_does_not_change_vehicle_status(): void
    {
        $this->authenticateUser();
        $vehicle = Vehicle::factory()->create();
        Rental::factory()
            ->for($vehicle)
            ->create([
                'status' => Rental::STATUS_RESERVED,
                'starts_at' => now()->subDays(3),
                'expected_ends_at' => now()->subDay(),
                'actual_starts_at' => null,
                'actual_ends_at' => null,
                'start_mileage' => null,
                'end_mileage' => null,
            ]);
        $this->getJson("/api/vehicles/{$vehicle->id}")
            ->assertOk()
            ->assertJsonPath(
                'data.operational_status',
                Vehicle::OPERATIONAL_STATUS_AVAILABLE
            )
            ->assertJsonPath('data.next_reservation', null);
    }

    // Il noleggio attivo ha precedenza su una prenotazione futura
    public function test_active_rental_has_priority_over_future_reservation(): void
    {
        $this->authenticateUser();
        $vehicle = Vehicle::factory()->create();
        $activeRental = Rental::factory()
            ->for($vehicle)
            ->create([
                'status' => Rental::STATUS_ACTIVE,
                'starts_at' => now()->subDay(),
                'actual_starts_at' => now()->subDay(),
                'expected_ends_at' => now()->addDay(),
                'start_mileage' => $vehicle->mileage,
            ]);
        $reservation = Rental::factory()
            ->for($vehicle)
            ->create([
                'status' => Rental::STATUS_RESERVED,
                'starts_at' => now()->addDays(4),
                'expected_ends_at' => now()->addDays(6),
                'actual_starts_at' => null,
                'actual_ends_at' => null,
                'start_mileage' => null,
                'end_mileage' => null,
            ]);
        $response = $this->getJson(
            "/api/vehicles/{$vehicle->id}"
        );
        $response->assertOk();
        $response->assertJsonPath(
            'data.operational_status',
            Vehicle::OPERATIONAL_STATUS_RENTED
        );
        $response->assertJsonPath(
            'data.active_rental.id',
            $activeRental->id
        );
        $response->assertJsonPath(
            'data.next_reservation.id',
            $reservation->id
        );
    }

    // Il dettaglio restituisce le celle attualmente occupate dal veicolo.
    public function test_vehicle_detail_includes_current_parking_spaces(): void
    {
        $this->authenticateUser();

        $vehicle = Vehicle::factory()->create([
            'parking_units' => 2,
        ]);

        $firstSpace = ParkingSpace::factory()->create([
            'vehicle_id' => $vehicle->id,
        ]);

        $secondSpace = ParkingSpace::factory()->create([
            'vehicle_id' => $vehicle->id,
        ]);

        $response = $this->getJson(
            "/api/vehicles/{$vehicle->id}"
        );

        $response->assertOk();

        // Verifica che siano restituite entrambe le celle.
        $response->assertJsonCount(
            2,
            'data.parking_spaces'
        );

        // Verifica le informazioni della prima cella.
        $response->assertJsonFragment([
            'id' => $firstSpace->id,
            'label' => $firstSpace->label,
            'vehicle_id' => $vehicle->id,
            'is_occupied' => true,
        ]);

        // Verifica le informazioni della seconda cella.
        $response->assertJsonFragment([
            'id' => $secondSpace->id,
            'label' => $secondSpace->label,
            'vehicle_id' => $vehicle->id,
            'is_occupied' => true,
        ]);
    }

    // Crea e autentica un utente fittizio
    private function authenticateUser(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
    }
}
