<?php

namespace Tests\Feature\Models;

use App\Models\ParkingSpace;
use App\Models\Vehicle;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ParkingSpaceTest extends TestCase
{
    //Ricrea il database di test prima di ogni metodo
    //Il database MySQL reale non viene modificato
    use RefreshDatabase;

    public function test_factory_creates_an_empty_active_cell(): void
    {
        //Crea una cella vuota tramite la Factory
        $parkingSpace = ParkingSpace::factory()
            ->create();

        //Controlla che sia stata creata soltanto la cella
        $this->assertDatabaseCount('parking_spaces', 1);
        $this->assertDatabaseCount('vehicles', 0);

        //Una nuova cella deve essere vuota e utilizzabile
        $this->assertNull($parkingSpace->vehicle_id);
        $this->assertTrue($parkingSpace->is_active);

        //Controlla i tipi convertiti dal Model
        $this->assertIsInt($parkingSpace->row_number);
        $this->assertIsInt($parkingSpace->column_number);

        //La Factory utilizza inizialmente la zona principale
        $this->assertSame('main', $parkingSpace->zone);
    }

    public function test_coordinates_must_be_unique_inside_the_same_zone(): void
    {
        //Crea la prima cella nella posizione main, riga 1, colonna 1
        ParkingSpace::factory()
            ->create([
                'label' => 'MAIN-1',
                'zone' => 'main',
                'row_number' => 1,
                'column_number' => 1,
            ]);

        //Una seconda cella non può occupare la stessa posizione
        $this->expectException(QueryException::class);

        ParkingSpace::factory()
            ->create([
                'label' => 'MAIN-2',
                'zone' => 'main',
                'row_number' => 1,
                'column_number' => 1,
            ]);
    }

    public function test_same_coordinates_can_exist_in_different_zones(): void
    {
        //Crea una cella nella zona principale
        ParkingSpace::factory()
            ->create([
                'label' => 'MAIN-1',
                'zone' => 'main',
                'row_number' => 1,
                'column_number' => 1,
            ]);

        //Crea la stessa posizione in una zona differente
        ParkingSpace::factory()
            ->create([
                'label' => 'OUTSIDE-1',
                'zone' => 'outside',
                'row_number' => 1,
                'column_number' => 1,
            ]);

        //Le due celle possono esistere perché appartengono a zone diverse
        $this->assertDatabaseCount('parking_spaces', 2);
    }

    public function test_labels_can_be_null_but_must_be_unique_when_provided(): void
    {
        //Più celle possono non avere un'etichetta visibile
        ParkingSpace::factory()
            ->create([
                'label' => null,
            ]);

        ParkingSpace::factory()
            ->create([
                'label' => null,
            ]);

        $this->assertDatabaseCount('parking_spaces', 2);

        //Crea una cella con un'etichetta conosciuta
        ParkingSpace::factory()
            ->create([
                'label' => 'A1',
            ]);

        //Una seconda etichetta uguale deve essere rifiutata
        $this->expectException(QueryException::class);

        ParkingSpace::factory()
            ->create([
                'label' => 'A1',
            ]);
    }

    public function test_vehicle_can_occupy_multiple_cells(): void
    {
        //Crea un camper che richiede quattro celle
        $vehicle = Vehicle::factory()
            ->create([
                'type' => 'camper',
                'parking_units' => Vehicle::PARKING_UNITS_LARGE,
            ]);

        //Assegna quattro celle adiacenti allo stesso camper
        for ($column = 1; $column <= 4; $column++) {
            ParkingSpace::factory()
                ->create([
                    'label' => "A-{$column}",
                    'zone' => 'main',
                    'row_number' => 1,
                    'column_number' => $column,
                    'vehicle_id' => $vehicle->id,
                ]);
        }

        //Relazione uno a molti (1:N):
        //il veicolo restituisce tutte le celle che occupa
        $this->assertSame(
            4,
            $vehicle->parkingSpaces()
                ->count()
        );

        //Relazione molti a uno (N:1):
        //ogni cella restituisce lo stesso veicolo
        foreach ($vehicle->parkingSpaces as $parkingSpace) {
            $this->assertTrue(
                $parkingSpace->vehicle->is($vehicle)
            );
        }

        //Il numero di celle corrisponde alle unità richieste dal camper
        $this->assertSame(
            $vehicle->parking_units,
            $vehicle->parkingSpaces()
                ->count()
        );

        $this->assertDatabaseCount('vehicles', 1);
        $this->assertDatabaseCount('parking_spaces', 4);
    }

    public function test_vehicle_contains_all_allowed_parking_units(): void
    {
        //Controlla i numeri di celle ammessi
        $this->assertSame([
            Vehicle::PARKING_UNITS_SMALL,
            Vehicle::PARKING_UNITS_STANDARD,
            Vehicle::PARKING_UNITS_LARGE,
            Vehicle::PARKING_UNITS_EXTRA_LARGE,
        ], Vehicle::ALLOWED_PARKING_UNITS);

        //Controlla i valori associati alle quattro dimensioni
        $this->assertSame(1, Vehicle::PARKING_UNITS_SMALL);
        $this->assertSame(2, Vehicle::PARKING_UNITS_STANDARD);
        $this->assertSame(4, Vehicle::PARKING_UNITS_LARGE);
        $this->assertSame(8, Vehicle::PARKING_UNITS_EXTRA_LARGE);

        //Controlla che la Factory generi un valore ammesso
        $vehicle = Vehicle::factory()
            ->create();

        $this->assertContains(
            $vehicle->parking_units,
            Vehicle::ALLOWED_PARKING_UNITS
        );
        $this->assertIsInt($vehicle->parking_units);
    }

    public function test_vehicle_uses_two_parking_units_by_default(): void
    {
        //Crea manualmente un veicolo senza specificare parking_units
        $vehicle = Vehicle::create([
            'license_plate' => 'DEFAULT-01',
            'brand' => 'Fiat',
            'model' => 'Panda',
            'type' => 'car',
            'year' => 2020,
            'mileage' => 10000,
            'daily_rate' => 40,
            'is_active' => true,
        ]);

        //Rilegge il valore predefinito assegnato dal database
        $vehicle->refresh();

        //Un veicolo senza valore specifico viene trattato come automobile
        $this->assertSame(
            Vehicle::PARKING_UNITS_STANDARD,
            $vehicle->parking_units
        );
    }

    public function test_deleting_vehicle_empties_its_cells(): void
    {
        //Crea un'automobile che occupa due celle
        $vehicle = Vehicle::factory()
            ->create([
                'parking_units' => Vehicle::PARKING_UNITS_STANDARD,
            ]);

        $firstParkingSpace = ParkingSpace::factory()
            ->create([
                'vehicle_id' => $vehicle->id,
            ]);

        $secondParkingSpace = ParkingSpace::factory()
            ->create([
                'vehicle_id' => $vehicle->id,
            ]);

        //Elimina il veicolo privo di noleggi e spese
        $vehicle->delete();

        //nullOnDelete mantiene le celle ma le rende vuote
        $this->assertNull(
            $firstParkingSpace->refresh()->vehicle_id
        );
        $this->assertNull(
            $secondParkingSpace->refresh()->vehicle_id
        );

        $this->assertDatabaseCount('vehicles', 0);
        $this->assertDatabaseCount('parking_spaces', 2);
    }
}
