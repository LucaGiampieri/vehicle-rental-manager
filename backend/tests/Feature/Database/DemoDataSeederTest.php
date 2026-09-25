<?php

namespace Tests\Feature\Database;

use App\Models\ParkingMovement;
use App\Models\ParkingSpace;
use App\Models\Rental;
use App\Models\User;
use App\Models\Vehicle;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DemoDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class DemoDataSeederTest extends TestCase
{
    use RefreshDatabase;

    // Verifica quantità e collegamenti principali dei dati dimostrativi.
    public function test_demo_data_is_created_correctly(): void
    {
        $this->seed(DemoDataSeeder::class);

        $this->assertDatabaseHas('users', [
            'email' => 'admin@example.com',
            'name' => 'Amministratore Demo',
        ]);

        $demoUser = User::query()
            ->where('email', 'admin@example.com')
            ->firstOrFail();

        $this->assertTrue(
            Hash::check('PasswordDemo!2026', $demoUser->password)
        );

        $this->assertDatabaseCount('vehicles', 30);
        $this->assertDatabaseCount('customers', 15);
        $this->assertDatabaseCount('parking_spaces', 24);
        $this->assertDatabaseCount('rentals', 30);
        $this->assertDatabaseCount('expenses', 45);
        $this->assertDatabaseCount('parking_movements', 14);

        $panda = Vehicle::query()->where('license_plate', 'DEMO-001')->firstOrFail();
        $transit = Vehicle::query()->where('license_plate', 'DEMO-002')->firstOrFail();
        $ducato = Vehicle::query()->where('license_plate', 'DEMO-003')->firstOrFail();

        $this->assertSame(1, ParkingSpace::query()->where('vehicle_id', $panda->id)->count());
        $this->assertSame(2, ParkingSpace::query()->where('vehicle_id', $transit->id)->count());
        $this->assertSame(0, ParkingSpace::query()->where('vehicle_id', $ducato->id)->count());

        $this->assertSame(4, Rental::query()->where('status', Rental::STATUS_ACTIVE)->count());
        $this->assertSame(6, Rental::query()->where('status', Rental::STATUS_RESERVED)->count());
        $this->assertSame(15, Rental::query()->where('status', Rental::STATUS_COMPLETED)->count());
        $this->assertSame(5, Rental::query()->where('status', Rental::STATUS_CANCELLED)->count());

        $this->assertDatabaseHas('parking_movements', [
            'vehicle_id' => $ducato->id,
            'type' => ParkingMovement::TYPE_RENTAL_DEPARTURE,
        ]);

        $this->assertDatabaseHas('parking_spaces', [
            'zone' => 'main',
            'row_number' => 4,
            'column_number' => 6,
            'is_active' => 0,
        ]);
    }

    // Verifica che una seconda esecuzione non duplichi i dati demo.
    public function test_demo_seeder_can_be_run_more_than_once(): void
    {
        $this->seed(DemoDataSeeder::class);
        $this->seed(DemoDataSeeder::class);

        $this->assertDatabaseCount('vehicles', 30);
        $this->assertDatabaseCount('customers', 15);
        $this->assertDatabaseCount('parking_spaces', 24);
        $this->assertDatabaseCount('rentals', 30);
        $this->assertDatabaseCount('expenses', 45);
        $this->assertDatabaseCount('parking_movements', 14);
    }

    // Verifica che il Seeder principale non inserisca demo in produzione.
    public function test_demo_data_is_not_created_in_production(): void
    {
        $this->app->detectEnvironment(
            fn (): string => 'production'
        );

        (new DatabaseSeeder)->run();

        $this->assertDatabaseMissing('users', [
            'email' => 'admin@example.com',
        ]);
    }
}
