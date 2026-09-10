<?php

namespace Tests\Feature\Api;

use App\Models\ParkingMovement;
use App\Models\ParkingSpace;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class GarageApiTest extends TestCase
{
    use RefreshDatabase;

    //Crea e autentica un utente fittizio.
    private function authenticateUser(): User
    {
        $user = User::factory()->create();

        Sanctum::actingAs($user);

        return $user;
    }

    //Crea una griglia rettangolare di celle vuote.
    private function createGrid(
        int $rows,
        int $columns,
        string $zone = 'main'
    ): Collection {
        $spaces = collect();

        for ($row = 1; $row <= $rows; $row++) {
            for ($column = 1; $column <= $columns; $column++) {
                $spaces->push(
                    ParkingSpace::factory()->create([
                        'label' => strtoupper(
                            "{$zone}-{$row}-{$column}"
                        ),
                        'zone' => $zone,
                        'row_number' => $row,
                        'column_number' => $column,
                        'vehicle_id' => null,
                        'is_active' => true,
                    ])
                );
            }
        }

        return $spaces;
    }

    //Trova una cella della griglia attraverso riga e colonna.
    private function spaceAt(
        Collection $spaces,
        int $row,
        int $column
    ): ParkingSpace {
        $space = $spaces->first(
            fn (ParkingSpace $space) =>
                $space->row_number === $row
                && $space->column_number === $column
        );

        $this->assertInstanceOf(ParkingSpace::class, $space);

        return $space;
    }

    //Le operazioni del garage richiedono l'autenticazione.
    public function test_guest_cannot_access_garage(): void
    {
        $this->postJson('/api/garage/park')
            ->assertUnauthorized();

        $this->getJson('/api/garage/movements')
            ->assertUnauthorized();
    }

    //La richiesta di parcheggio deve contenere dati validi.
    public function test_parking_request_requires_valid_data(): void
    {
        $this->authenticateUser();

        $response = $this->postJson('/api/garage/park', [
            'vehicle_id' => 999999,
            'parking_space_id' => 999999,
            'notes' => str_repeat('A', 5001),
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors([
            'vehicle_id',
            'parking_space_id',
            'notes',
        ]);

        $this->assertDatabaseCount('parking_movements', 0);
    }

    //Verifica i blocchi 1x1, 1x2, 2x2 e 2x4.
    public function test_vehicles_occupy_required_rectangular_blocks(): void
    {
        $this->authenticateUser();

        $shapes = [
            1 => [1, 1],
            2 => [1, 2],
            4 => [2, 2],
            8 => [2, 4],
        ];

        foreach ($shapes as $parkingUnits => [$rows, $columns]) {
            $zone = "shape{$parkingUnits}";
            $spaces = $this->createGrid($rows, $columns, $zone);

            $vehicle = Vehicle::factory()->create([
                'parking_units' => $parkingUnits,
            ]);

            $startingSpace = $this->spaceAt($spaces, 1, 1);

            $response = $this->postJson('/api/garage/park', [
                'vehicle_id' => $vehicle->id,
                'parking_space_id' => $startingSpace->id,
                'notes' => '  Ingresso manuale  ',
            ]);

            $response->assertCreated();
            $response->assertJsonPath(
                'data.type',
                ParkingMovement::TYPE_PARKED
            );
            $response->assertJsonPath(
                'data.vehicle_id',
                $vehicle->id
            );
            $response->assertJsonPath(
                'data.parking_units',
                $parkingUnits
            );
            $response->assertJsonPath('data.from', null);
            $response->assertJsonPath('data.to.zone', $zone);
            $response->assertJsonPath(
                'data.notes',
                'Ingresso manuale'
            );

            $this->assertSame(
                $parkingUnits,
                ParkingSpace::query()
                    ->where('vehicle_id', $vehicle->id)
                    ->count()
            );
        }

        $this->assertDatabaseCount('parking_movements', 4);
    }

    //Blocchi incompleti, disattivati oppure occupati vengono rifiutati.
    public function test_unavailable_parking_blocks_are_rejected(): void
    {
        $this->authenticateUser();

        //Caso 1: manca una cella necessaria.
        $missingGrid = $this->createGrid(1, 1, 'missing');
        $missingVehicle = Vehicle::factory()->create([
            'parking_units' => 2,
        ]);

        $this->postJson('/api/garage/park', [
            'vehicle_id' => $missingVehicle->id,
            'parking_space_id' => $missingGrid->first()->id,
        ])->assertConflict();

        //Caso 2: una cella è disattivata.
        $inactiveGrid = $this->createGrid(1, 2, 'inactive');
        $inactiveVehicle = Vehicle::factory()->create([
            'parking_units' => 2,
        ]);

        $this->spaceAt($inactiveGrid, 1, 2)->update([
            'is_active' => false,
        ]);

        $this->postJson('/api/garage/park', [
            'vehicle_id' => $inactiveVehicle->id,
            'parking_space_id' => $this->spaceAt(
                $inactiveGrid,
                1,
                1
            )->id,
        ])->assertConflict();

        //Caso 3: una cella appartiene a un altro veicolo.
        $occupiedGrid = $this->createGrid(1, 2, 'occupied');

        $vehicle = Vehicle::factory()->create([
            'parking_units' => 2,
        ]);

        $otherVehicle = Vehicle::factory()->create();

        $this->spaceAt($occupiedGrid, 1, 2)->update([
            'vehicle_id' => $otherVehicle->id,
        ]);

        $this->postJson('/api/garage/park', [
            'vehicle_id' => $vehicle->id,
            'parking_space_id' => $this->spaceAt(
                $occupiedGrid,
                1,
                1
            )->id,
        ])->assertConflict();

        $this->assertDatabaseCount('parking_movements', 0);
    }

    //Lo stesso veicolo non può essere parcheggiato due volte.
    public function test_vehicle_cannot_be_parked_twice(): void
    {
        $this->authenticateUser();

        $spaces = $this->createGrid(1, 2, 'double');
        $vehicle = Vehicle::factory()->create([
            'parking_units' => 1,
        ]);

        $this->postJson('/api/garage/park', [
            'vehicle_id' => $vehicle->id,
            'parking_space_id' => $this->spaceAt(
                $spaces,
                1,
                1
            )->id,
        ])->assertCreated();

        $this->postJson('/api/garage/park', [
            'vehicle_id' => $vehicle->id,
            'parking_space_id' => $this->spaceAt(
                $spaces,
                1,
                2
            )->id,
        ])->assertConflict();

        $this->assertDatabaseCount('parking_movements', 1);
    }

    //Un veicolo può essere spostato verso un nuovo blocco.
    public function test_parked_vehicle_can_be_moved(): void
    {
        $this->authenticateUser();

        $spaces = $this->createGrid(2, 4, 'move');
        $vehicle = Vehicle::factory()->create([
            'parking_units' => 2,
        ]);

        $oldStart = $this->spaceAt($spaces, 1, 1);
        $newStart = $this->spaceAt($spaces, 2, 3);

        $this->postJson('/api/garage/park', [
            'vehicle_id' => $vehicle->id,
            'parking_space_id' => $oldStart->id,
        ])->assertCreated();

        $response = $this->patchJson(
            "/api/garage/vehicles/{$vehicle->id}/move",
            [
                'parking_space_id' => $newStart->id,
                'notes' => '  Cambio posizione  ',
            ]
        );

        $response->assertOk();
        $response->assertJsonPath(
            'data.type',
            ParkingMovement::TYPE_MOVED
        );
        $response->assertJsonPath('data.from.row_number', 1);
        $response->assertJsonPath('data.from.column_number', 1);
        $response->assertJsonPath('data.to.row_number', 2);
        $response->assertJsonPath('data.to.column_number', 3);
        $response->assertJsonPath(
            'data.notes',
            'Cambio posizione'
        );

        //Le due vecchie celle devono essere libere.
        $this->assertSame(
            2,
            ParkingSpace::query()
                ->whereIn('id', [
                    $this->spaceAt($spaces, 1, 1)->id,
                    $this->spaceAt($spaces, 1, 2)->id,
                ])
                ->whereNull('vehicle_id')
                ->count()
        );

        //Le due nuove celle devono essere occupate.
        $this->assertSame(
            2,
            ParkingSpace::query()
                ->whereIn('id', [
                    $this->spaceAt($spaces, 2, 3)->id,
                    $this->spaceAt($spaces, 2, 4)->id,
                ])
                ->where('vehicle_id', $vehicle->id)
                ->count()
        );

        //Non può essere spostato nuovamente sullo stesso blocco.
        $this->patchJson(
            "/api/garage/vehicles/{$vehicle->id}/move",
            ['parking_space_id' => $newStart->id]
        )->assertConflict();

        $this->assertDatabaseCount('parking_movements', 2);
    }

    //Un veicolo non parcheggiato non può essere spostato o rimosso.
    public function test_unparked_vehicle_cannot_be_moved_or_unparked(): void
    {
        $this->authenticateUser();

        $spaces = $this->createGrid(1, 1, 'empty');
        $vehicle = Vehicle::factory()->create();

        $this->patchJson(
            "/api/garage/vehicles/{$vehicle->id}/move",
            [
                'parking_space_id' => $spaces->first()->id,
            ]
        )->assertConflict();

        $this->patchJson(
            "/api/garage/vehicles/{$vehicle->id}/unpark"
        )->assertConflict();

        $this->assertDatabaseCount('parking_movements', 0);
    }

    //L'uscita libera tutte le celle e crea la cronologia.
    public function test_parked_vehicle_can_be_unparked(): void
    {
        $this->authenticateUser();

        $spaces = $this->createGrid(2, 2, 'exit');
        $vehicle = Vehicle::factory()->create([
            'parking_units' => 4,
        ]);

        $this->postJson('/api/garage/park', [
            'vehicle_id' => $vehicle->id,
            'parking_space_id' => $spaces->first()->id,
        ])->assertCreated();

        $response = $this->patchJson(
            "/api/garage/vehicles/{$vehicle->id}/unpark",
            [
                'notes' => '  Uscita manuale  ',
            ]
        );

        $response->assertOk();
        $response->assertJsonPath(
            'data.type',
            ParkingMovement::TYPE_UNPARKED
        );
        $response->assertJsonPath('data.to', null);
        $response->assertJsonPath(
            'data.notes',
            'Uscita manuale'
        );

        $this->assertSame(
            0,
            ParkingSpace::query()
                ->where('vehicle_id', $vehicle->id)
                ->count()
        );

        $this->assertDatabaseHas('parking_movements', [
            'vehicle_id' => $vehicle->id,
            'type' => ParkingMovement::TYPE_UNPARKED,
            'to_parking_space_id' => null,
            'notes' => 'Uscita manuale',
        ]);
    }

    //La cronologia può essere letta e filtrata.
    public function test_movements_can_be_listed_and_filtered(): void
    {
        $this->authenticateUser();

        $spaces = $this->createGrid(1, 2, 'history');

        $firstVehicle = Vehicle::factory()->create([
            'parking_units' => 1,
        ]);

        $secondVehicle = Vehicle::factory()->create([
            'parking_units' => 1,
        ]);

        $this->postJson('/api/garage/park', [
            'vehicle_id' => $firstVehicle->id,
            'parking_space_id' => $this->spaceAt(
                $spaces,
                1,
                1
            )->id,
        ])->assertCreated();

        $this->postJson('/api/garage/park', [
            'vehicle_id' => $secondVehicle->id,
            'parking_space_id' => $this->spaceAt(
                $spaces,
                1,
                2
            )->id,
        ])->assertCreated();

        $this->patchJson(
            "/api/garage/vehicles/{$firstVehicle->id}/unpark"
        )->assertOk();

        $filteredResponse = $this->getJson(
            '/api/garage/movements'
            ."?vehicle_id={$firstVehicle->id}"
            .'&type=parked'
        );

        $filteredResponse->assertOk();
        $filteredResponse->assertJsonCount(1, 'data');
        $filteredResponse->assertJsonPath(
            'data.0.vehicle_id',
            $firstVehicle->id
        );
        $filteredResponse->assertJsonStructure([
            'data',
            'links',
            'meta',
        ]);

        $vehicleResponse = $this->getJson(
            "/api/garage/vehicles/{$firstVehicle->id}/movements"
        );

        $vehicleResponse->assertOk();
        $vehicleResponse->assertJsonCount(2, 'data');

        //Verifica anche la validazione dei filtri.
        $invalidFilters = $this->getJson(
            '/api/garage/movements'
            .'?type=invalid'
            .'&date_from=2026-09-10'
            .'&date_to=2026-09-09'
        );

        $invalidFilters->assertUnprocessable();
        $invalidFilters->assertJsonValidationErrors([
            'type',
            'date_to',
        ]);
    }
}
