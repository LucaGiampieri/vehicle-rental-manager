<?php

namespace Tests\Feature\Api;

use App\Models\Customer;
use App\Models\ParkingMovement;
use App\Models\ParkingSpace;
use App\Models\Rental;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RentalGarageIntegrationTest extends TestCase
{
    use RefreshDatabase;

    //Crea e autentica l'operatore che esegue le azioni.
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
        string $zone
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

    //Crea un noleggio prenotato che può essere attivato.
    private function createReservedRental(
        Vehicle $vehicle
    ): Rental {
        $customer = Customer::factory()->create([
            'is_active' => true,
        ]);

        return Rental::factory()->create([
            'vehicle_id' => $vehicle->id,
            'customer_id' => $customer->id,
            'status' => Rental::STATUS_RESERVED,
            'starts_at' => now()->subHour(),
            'expected_ends_at' => now()->addDays(2),
            'actual_starts_at' => null,
            'actual_ends_at' => null,
            'daily_rate' => 50,
            'total_amount' => 100,
            'amount_paid' => 0,
            'start_mileage' => null,
            'end_mileage' => null,
        ]);
    }

    //Crea un noleggio già attivo che può essere completato.
    private function createActiveRental(
        Vehicle $vehicle
    ): Rental {
        $customer = Customer::factory()->create([
            'is_active' => true,
        ]);

        return Rental::factory()->create([
            'vehicle_id' => $vehicle->id,
            'customer_id' => $customer->id,
            'status' => Rental::STATUS_ACTIVE,
            'starts_at' => now()->subDays(2),
            'expected_ends_at' => now()->addDay(),
            'actual_starts_at' => now()->subDays(2),
            'actual_ends_at' => null,
            'daily_rate' => 50,
            'total_amount' => 150,
            'amount_paid' => 0,
            'start_mileage' => $vehicle->mileage,
            'end_mileage' => null,
        ]);
    }

    //L'attivazione libera le celle e registra la partenza.
    public function test_activating_rental_unparks_vehicle(): void
    {
        $user = $this->authenticateUser();

        $vehicle = Vehicle::factory()->create([
            'parking_units' => 2,
            'mileage' => 15000,
            'is_active' => true,
        ]);

        $rental = $this->createReservedRental($vehicle);
        $spaces = $this->createGrid(1, 2, 'departure');

        //Posiziona inizialmente il veicolo nelle due celle.
        ParkingSpace::query()
            ->whereKey($spaces->pluck('id'))
            ->update([
                'vehicle_id' => $vehicle->id,
            ]);

        $startingSpace = $spaces->first();

        $response = $this->patchJson(
            "/api/rentals/{$rental->id}/activate",
            [
                'start_mileage' => 15000,
                'notes' => '  Consegnato al cliente  ',
            ]
        );

        $response->assertOk();
        $response->assertJsonPath(
            'data.status',
            Rental::STATUS_ACTIVE
        );

        //Tutte le celle devono essere state liberate.
        $this->assertSame(
            0,
            ParkingSpace::query()
                ->where('vehicle_id', $vehicle->id)
                ->count()
        );

        //La partenza deve essere collegata al noleggio.
        $this->assertDatabaseHas('parking_movements', [
            'vehicle_id' => $vehicle->id,
            'rental_id' => $rental->id,
            'performed_by_user_id' => $user->id,
            'type' => ParkingMovement::TYPE_RENTAL_DEPARTURE,
            'from_parking_space_id' => $startingSpace->id,
            'to_parking_space_id' => null,
            'parking_units' => 2,
            'notes' => 'Consegnato al cliente',
        ]);

        $rental->refresh();

        $this->assertSame(
            Rental::STATUS_ACTIVE,
            $rental->status
        );
        $this->assertNotNull($rental->actual_starts_at);
    }

    //Un mezzo già fuori può comunque iniziare il noleggio.
    public function test_unparked_vehicle_can_activate_rental(): void
    {
        $this->authenticateUser();

        $vehicle = Vehicle::factory()->create([
            'parking_units' => 1,
            'mileage' => 10000,
            'is_active' => true,
        ]);

        $rental = $this->createReservedRental($vehicle);

        $response = $this->patchJson(
            "/api/rentals/{$rental->id}/activate",
            [
                'start_mileage' => 10000,
            ]
        );

        $response->assertOk();
        $response->assertJsonPath(
            'data.status',
            Rental::STATUS_ACTIVE
        );

        //Non viene creato un movimento perché il mezzo era già fuori.
        $this->assertDatabaseCount('parking_movements', 0);
    }

    //Il completamento parcheggia il veicolo e registra il rientro.
    public function test_completing_rental_parks_vehicle(): void
    {
        $user = $this->authenticateUser();

        $vehicle = Vehicle::factory()->create([
            'parking_units' => 4,
            'mileage' => 20000,
            'is_active' => true,
        ]);

        $rental = $this->createActiveRental($vehicle);
        $spaces = $this->createGrid(2, 2, 'return');
        $startingSpace = $spaces->first();

        $response = $this->patchJson(
            "/api/rentals/{$rental->id}/complete",
            [
                'parking_space_id' => $startingSpace->id,
                'actual_ends_at' => now()
                    ->subMinute()
                    ->toDateTimeString(),
                'end_mileage' => 20500,
                'amount_paid' => 150,
                'notes' => '  Rientro completato  ',
            ]
        );

        $response->assertOk();
        $response->assertJsonPath(
            'data.status',
            Rental::STATUS_COMPLETED
        );
        $response->assertJsonPath('data.end_mileage', 20500);

        //Il veicolo deve occupare tutte le quattro celle.
        $this->assertSame(
            4,
            ParkingSpace::query()
                ->where('vehicle_id', $vehicle->id)
                ->count()
        );

        $this->assertDatabaseHas('parking_movements', [
            'vehicle_id' => $vehicle->id,
            'rental_id' => $rental->id,
            'performed_by_user_id' => $user->id,
            'type' => ParkingMovement::TYPE_RENTAL_RETURN,
            'from_parking_space_id' => null,
            'to_parking_space_id' => $startingSpace->id,
            'parking_units' => 4,
            'notes' => 'Rientro completato',
        ]);

        $rental->refresh();
        $vehicle->refresh();

        $this->assertSame(
            Rental::STATUS_COMPLETED,
            $rental->status
        );
        $this->assertNotNull($rental->actual_ends_at);
        $this->assertSame(20500, $vehicle->mileage);
    }

    //Se il blocco non è disponibile, il noleggio non viene completato.
    public function test_failed_return_rolls_back_rental_completion(): void
    {
        $this->authenticateUser();

        $vehicle = Vehicle::factory()->create([
            'parking_units' => 4,
            'mileage' => 30000,
            'is_active' => true,
        ]);

        $rental = $this->createActiveRental($vehicle);

        //Una sola cella non è sufficiente per un veicolo da quattro unità.
        $spaces = $this->createGrid(1, 1, 'rollback');

        $response = $this->patchJson(
            "/api/rentals/{$rental->id}/complete",
            [
                'parking_space_id' => $spaces->first()->id,
                'end_mileage' => 30500,
                'amount_paid' => 150,
            ]
        );

        $response->assertConflict();

        $rental->refresh();
        $vehicle->refresh();

        //La transazione deve annullare tutte le modifiche.
        $this->assertSame(
            Rental::STATUS_ACTIVE,
            $rental->status
        );
        $this->assertNull($rental->actual_ends_at);
        $this->assertNull($rental->end_mileage);
        $this->assertSame(30000, $vehicle->mileage);

        $this->assertSame(
            0,
            ParkingSpace::query()
                ->where('vehicle_id', $vehicle->id)
                ->count()
        );

        $this->assertDatabaseCount('parking_movements', 0);
    }

    //La cella di rientro, se inviata, deve esistere.
    public function test_return_parking_space_must_exist(): void
    {
        $this->authenticateUser();

        $vehicle = Vehicle::factory()->create([
            'parking_units' => 1,
            'mileage' => 40000,
            'is_active' => true,
        ]);

        $rental = $this->createActiveRental($vehicle);

        $response = $this->patchJson(
            "/api/rentals/{$rental->id}/complete",
            [
                'parking_space_id' => 999999,
                'end_mileage' => 40100,
            ]
        );

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors([
            'parking_space_id',
        ]);

        $rental->refresh();

        $this->assertSame(
            Rental::STATUS_ACTIVE,
            $rental->status
        );
        $this->assertDatabaseCount('parking_movements', 0);
    }
}
