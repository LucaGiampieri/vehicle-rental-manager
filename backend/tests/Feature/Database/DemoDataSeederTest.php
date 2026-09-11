<?php

namespace Tests\Feature\Database;

use App\Models\ParkingMovement;
use App\Models\ParkingSpace;
use App\Models\Rental;
use App\Models\Vehicle;
use Database\Seeders\DemoDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Database\Seeders\DatabaseSeeder;

class DemoDataSeederTest extends TestCase
{
    use RefreshDatabase;

    //Verifica quantità e collegamenti principali dei dati dimostrativi.
    public function test_demo_data_is_created_correctly(): void
    {
        $this->seed(DemoDataSeeder::class);

        $this->assertDatabaseHas('users', [
            'email' => 'admin@example.com',
            'name' => 'Amministratore Demo',
        ]);

        $this->assertDatabaseCount('vehicles', 4);
        $this->assertDatabaseCount('customers', 3);
        $this->assertDatabaseCount('parking_spaces', 24);
        $this->assertDatabaseCount('rentals', 4);
        $this->assertDatabaseCount('expenses', 5);
        $this->assertDatabaseCount('parking_movements', 3);

        $panda = Vehicle::query()
            ->where('license_plate', 'DEMO-001')
            ->firstOrFail();

        $transit = Vehicle::query()
            ->where('license_plate', 'DEMO-002')
            ->firstOrFail();

        $ducato = Vehicle::query()
            ->where('license_plate', 'DEMO-003')
            ->firstOrFail();

        $this->assertSame(
            1,
            ParkingSpace::query()
                ->where('vehicle_id', $panda->id)
                ->count()
        );

        $this->assertSame(
            2,
            ParkingSpace::query()
                ->where('vehicle_id', $transit->id)
                ->count()
        );

        $this->assertSame(
            0,
            ParkingSpace::query()
                ->where('vehicle_id', $ducato->id)
                ->count()
        );

        $this->assertDatabaseHas('rentals', [
            'vehicle_id' => $ducato->id,
            'status' => Rental::STATUS_ACTIVE,
        ]);

        $this->assertDatabaseHas('parking_movements', [
            'vehicle_id' => $ducato->id,
            'type' => ParkingMovement::TYPE_RENTAL_DEPARTURE,
        ]);

        $this->assertDatabaseHas('parking_spaces', [
            'zone' => 'main',
            'row_number' => 4,
            'column_number' => 6,
            //MySQL conserva i booleani come 0 e 1.
            'is_active' => 0,
        ]);
    }

    //Verifica che una seconda esecuzione non duplichi i dati demo.
    public function test_demo_seeder_can_be_run_more_than_once(): void
    {
        $this->seed(DemoDataSeeder::class);
        $this->seed(DemoDataSeeder::class);

        $this->assertDatabaseCount('vehicles', 4);
        $this->assertDatabaseCount('customers', 3);
        $this->assertDatabaseCount('parking_spaces', 24);
        $this->assertDatabaseCount('rentals', 4);
        $this->assertDatabaseCount('expenses', 5);
        $this->assertDatabaseCount('parking_movements', 3);
    }

    //Verifica che il Seeder principale non inserisca dati demo in produzione
    public function test_demo_data_is_not_created_in_production(): void
    {
        //Simula l'ambiente di produzione
        $this->app->detectEnvironment(
            fn (): string => 'production'
        );

        //Esegue direttamente il Seeder principale
        (new DatabaseSeeder())->run();

        //L'account con password dimostrativa non deve essere creato
        $this->assertDatabaseMissing('users', [
            'email' => 'admin@example.com',
        ]);
    }
}
