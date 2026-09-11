<?php

namespace Tests\Feature\Api;

use App\Models\ParkingSpace;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ParkingSpaceApiTest extends TestCase
{
    use RefreshDatabase;

    //Crea e autentica un utente per i test protetti.
    private function authenticateUser(): void
    {
        Sanctum::actingAs(
            User::factory()->create()
        );
    }

    //Restituisce dati validi, modificabili attraverso $overrides.
    private function validParkingSpaceData(
        array $overrides = []
    ): array {
        return array_merge([
            'label' => 'A-01',
            'zone' => 'main',
            'row_number' => 1,
            'column_number' => 1,
            'is_active' => true,
            'notes' => null,
        ], $overrides);
    }

    //Verifica che un ospite non possa accedere alle celle.
    public function test_guest_cannot_access_parking_spaces(): void
    {
        $response = $this->getJson('/api/parking-spaces');

        $response->assertUnauthorized();
    }

    //Verifica che un utente autenticato possa visualizzare la mappa.
    public function test_authenticated_user_can_list_parking_spaces(): void
    {
        $this->authenticateUser();

        ParkingSpace::factory()
            ->count(3)
            ->create();

        $response = $this->getJson('/api/parking-spaces');

        $response->assertOk();
        $response->assertJsonCount(3, 'data');

        $response->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'label',
                    'zone',
                    'row_number',
                    'column_number',
                    'vehicle_id',
                    'is_occupied',
                    'is_active',
                    'notes',
                    'vehicle',
                    'created_at',
                    'updated_at',
                ],
            ],
        ]);
    }

    //Verifica la creazione e la normalizzazione dei dati.
    public function test_authenticated_user_can_create_parking_space(): void
    {
        $this->authenticateUser();

        $response = $this->postJson('/api/parking-spaces', [
            'label' => '  a-01  ',
            'zone' => '  MAIN  ',
            'row_number' => 1,
            'column_number' => 1,
            'is_active' => true,
            'notes' => '  Vicino all’entrata  ',
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.label', 'A-01');
        $response->assertJsonPath('data.zone', 'main');
        $response->assertJsonPath('data.row_number', 1);
        $response->assertJsonPath('data.column_number', 1);
        $response->assertJsonPath('data.vehicle_id', null);
        $response->assertJsonPath('data.is_occupied', false);
        $response->assertJsonPath(
            'data.notes',
            'Vicino all’entrata'
        );

        $this->assertDatabaseHas('parking_spaces', [
            'label' => 'A-01',
            'zone' => 'main',
            'row_number' => 1,
            'column_number' => 1,
            'vehicle_id' => null,
            'is_active' => 1,
            'notes' => 'Vicino all’entrata',
        ]);
    }

    //Verifica che i dati non validi vengano rifiutati.
    public function test_parking_space_creation_requires_valid_data(): void
    {
        $this->authenticateUser();

        $response = $this->postJson(
            '/api/parking-spaces',
            $this->validParkingSpaceData([
                'label' => str_repeat('A', 21),
                'zone' => '   ',
                'row_number' => 0,
                'column_number' => 65536,
                'is_active' => 'non-valido',
                'notes' => str_repeat('A', 5001),
            ])
        );

        $response->assertUnprocessable();

        $response->assertJsonValidationErrors([
            'label',
            'zone',
            'row_number',
            'column_number',
            'is_active',
            'notes',
        ]);

        $this->assertDatabaseCount('parking_spaces', 0);
    }

    //Verifica che etichetta e posizione non possano essere duplicate.
    public function test_label_and_position_cannot_be_duplicated(): void
    {
        $this->authenticateUser();

        ParkingSpace::factory()->create([
            'label' => 'A-01',
            'zone' => 'main',
            'row_number' => 1,
            'column_number' => 1,
        ]);

        $duplicateLabel = $this->postJson(
            '/api/parking-spaces',
            $this->validParkingSpaceData([
                'label' => 'a-01',
                'row_number' => 2,
                'column_number' => 2,
            ])
        );

        $duplicateLabel->assertUnprocessable();
        $duplicateLabel->assertJsonValidationErrors(['label']);

        $duplicatePosition = $this->postJson(
            '/api/parking-spaces',
            $this->validParkingSpaceData([
                'label' => 'A-02',
            ])
        );

        $duplicatePosition->assertUnprocessable();
        $duplicatePosition->assertJsonValidationErrors([
            'row_number',
        ]);

        $this->assertDatabaseCount('parking_spaces', 1);
    }

    //Le stesse coordinate possono esistere in zone differenti.
    public function test_same_position_can_exist_in_different_zones(): void
    {
        $this->authenticateUser();

        ParkingSpace::factory()->create([
            'label' => 'A-01',
            'zone' => 'main',
            'row_number' => 1,
            'column_number' => 1,
        ]);

        $response = $this->postJson(
            '/api/parking-spaces',
            $this->validParkingSpaceData([
                'label' => 'B-01',
                'zone' => 'secondary',
            ])
        );

        $response->assertCreated();

        $this->assertDatabaseCount('parking_spaces', 2);
    }

    //Verifica il dettaglio di una cella occupata.
    public function test_authenticated_user_can_view_parking_space(): void
    {
        $this->authenticateUser();

        $vehicle = Vehicle::factory()->create();

        $parkingSpace = ParkingSpace::factory()->create([
            'vehicle_id' => $vehicle->id,
        ]);

        $response = $this->getJson(
            "/api/parking-spaces/{$parkingSpace->id}"
        );

        $response->assertOk();
        $response->assertJsonPath(
            'data.id',
            $parkingSpace->id
        );
        $response->assertJsonPath(
            'data.vehicle_id',
            $vehicle->id
        );
        $response->assertJsonPath('data.is_occupied', true);
        $response->assertJsonPath(
            'data.vehicle.license_plate',
            $vehicle->license_plate
        );
    }

    //Verifica che una cella vuota possa essere modificata.
    public function test_authenticated_user_can_update_parking_space(): void
    {
        $this->authenticateUser();

        $parkingSpace = ParkingSpace::factory()->create([
            'label' => 'A-01',
            'zone' => 'main',
            'row_number' => 1,
            'column_number' => 1,
            'is_active' => true,
        ]);

        $response = $this->patchJson(
            "/api/parking-spaces/{$parkingSpace->id}",
            [
                'label' => '  b-02  ',
                'zone' => '  SECONDARY  ',
                'row_number' => 2,
                'column_number' => 2,
                'is_active' => false,
                'notes' => '  Temporaneamente chiusa  ',
            ]
        );

        $response->assertOk();
        $response->assertJsonPath('data.label', 'B-02');
        $response->assertJsonPath('data.zone', 'secondary');
        $response->assertJsonPath('data.is_active', false);

        $this->assertDatabaseHas('parking_spaces', [
            'id' => $parkingSpace->id,
            'label' => 'B-02',
            'zone' => 'secondary',
            'row_number' => 2,
            'column_number' => 2,
            'is_active' => 0,
            'notes' => 'Temporaneamente chiusa',
        ]);
    }

    //Verifica la posizione completa anche modificando soltanto la zona.
    public function test_parking_space_cannot_move_onto_existing_position(): void
    {
        $this->authenticateUser();

        ParkingSpace::factory()->create([
            'zone' => 'main',
            'row_number' => 1,
            'column_number' => 1,
        ]);

        $parkingSpace = ParkingSpace::factory()->create([
            'zone' => 'secondary',
            'row_number' => 1,
            'column_number' => 1,
        ]);

        $response = $this->patchJson(
            "/api/parking-spaces/{$parkingSpace->id}",
            ['zone' => 'main']
        );

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['row_number']);

        $this->assertDatabaseHas('parking_spaces', [
            'id' => $parkingSpace->id,
            'zone' => 'secondary',
        ]);
    }

    //Una cella occupata non può cambiare posizione
    public function test_occupied_parking_space_cannot_change_position(): void
    {
        $this->authenticateUser();

        $vehicle = Vehicle::factory()->create();

        $parkingSpace = ParkingSpace::factory()->create([
            'zone' => 'main',
            'row_number' => 1,
            'column_number' => 1,
            'vehicle_id' => $vehicle->id,
            'is_active' => true,
        ]);

        //Prova a spostare direttamente la cella occupata
        $response = $this->patchJson(
            "/api/parking-spaces/{$parkingSpace->id}",
            [
                'zone' => 'secondary',
                'row_number' => 2,
                'column_number' => 2,
            ]
        );

        $response->assertConflict();

        $response->assertJsonPath(
            'message',
            'Una cella occupata non può cambiare posizione. Sposta o rimuovi prima il veicolo.'
        );

        //Posizione e occupazione devono essere rimaste invariate
        $this->assertDatabaseHas('parking_spaces', [
            'id' => $parkingSpace->id,
            'zone' => 'main',
            'row_number' => 1,
            'column_number' => 1,
            'vehicle_id' => $vehicle->id,
        ]);
    }

    //Una cella occupata non può essere disattivata.
    public function test_occupied_parking_space_cannot_be_deactivated(): void
    {
        $this->authenticateUser();

        $vehicle = Vehicle::factory()->create();

        $parkingSpace = ParkingSpace::factory()->create([
            'vehicle_id' => $vehicle->id,
            'is_active' => true,
        ]);

        $response = $this->patchJson(
            "/api/parking-spaces/{$parkingSpace->id}",
            ['is_active' => false]
        );

        $response->assertConflict();

        $this->assertDatabaseHas('parking_spaces', [
            'id' => $parkingSpace->id,
            'vehicle_id' => $vehicle->id,
            'is_active' => 1,
        ]);
    }

    //Verifica che una cella vuota possa essere eliminata.
    public function test_empty_parking_space_can_be_deleted(): void
    {
        $this->authenticateUser();

        $parkingSpace = ParkingSpace::factory()->create([
            'vehicle_id' => null,
        ]);

        $response = $this->deleteJson(
            "/api/parking-spaces/{$parkingSpace->id}"
        );

        $response->assertNoContent();

        $this->assertDatabaseMissing('parking_spaces', [
            'id' => $parkingSpace->id,
        ]);
    }

    //Verifica che una cella occupata non possa essere eliminata.
    public function test_occupied_parking_space_cannot_be_deleted(): void
    {
        $this->authenticateUser();

        $vehicle = Vehicle::factory()->create();

        $parkingSpace = ParkingSpace::factory()->create([
            'vehicle_id' => $vehicle->id,
        ]);

        $response = $this->deleteJson(
            "/api/parking-spaces/{$parkingSpace->id}"
        );

        $response->assertConflict();

        $this->assertDatabaseHas('parking_spaces', [
            'id' => $parkingSpace->id,
            'vehicle_id' => $vehicle->id,
        ]);
    }
}
