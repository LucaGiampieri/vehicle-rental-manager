<?php

namespace Tests\Feature\Models;

use App\Models\Customer;
use App\Models\Rental;
use App\Models\Vehicle;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class RentalTest extends TestCase
{
    //Ricrea il database di test prima di ogni metodo
    use RefreshDatabase;

    public function test_factory_creates_a_reserved_rental(): void
    {
        //Crea automaticamente noleggio, veicolo e cliente
        $rental = Rental::factory()
            ->create();

        //Controlla che siano stati creati tutti i record collegati
        $this->assertDatabaseCount('rentals', 1);
        $this->assertDatabaseCount('vehicles', 1);
        $this->assertDatabaseCount('customers', 1);

        //Controlla lo stato iniziale della prenotazione
        $this->assertSame(
            Rental::STATUS_RESERVED,
            $rental->status
        );

        //Controlla le conversioni automatiche delle date
        $this->assertInstanceOf(Carbon::class, $rental->starts_at);
        $this->assertInstanceOf(
            Carbon::class,
            $rental->expected_ends_at
        );

        //La riconsegna prevista deve essere successiva alla consegna
        $this->assertTrue(
            $rental->expected_ends_at->greaterThan($rental->starts_at)
        );

        //Gli importi devono sempre contenere due cifre decimali
        $this->assertMatchesRegularExpression(
            '/^\d+\.\d{2}$/',
            $rental->daily_rate
        );
        $this->assertMatchesRegularExpression(
            '/^\d+\.\d{2}$/',
            $rental->total_amount
        );
        $this->assertSame('0.00', $rental->amount_paid);

        //Una prenotazione non ancora iniziata non ha dati di rientro
        $this->assertNull($rental->actual_ends_at);
        $this->assertNull($rental->start_mileage);
        $this->assertNull($rental->end_mileage);
    }

    public function test_rental_has_vehicle_and_customer_relationships(): void
    {
        //Crea separatamente il veicolo e il cliente
        $vehicle = Vehicle::factory()
            ->create();

        $customer = Customer::factory()
            ->create();

        //Crea un noleggio collegato ai record già esistenti
        $rental = Rental::factory()
            ->create([
                'vehicle_id' => $vehicle->id,
                'customer_id' => $customer->id,
            ]);

        //Relazione molti a uno (N:1):
        //il noleggio restituisce il proprio veicolo e il proprio cliente
        $this->assertTrue($rental->vehicle->is($vehicle));
        $this->assertTrue($rental->customer->is($customer));

        //Relazione uno a molti (1:N):
        //veicolo e cliente restituiscono il noleggio collegato
        $this->assertTrue(
            $vehicle->rentals->contains($rental)
        );
        $this->assertTrue(
            $customer->rentals->contains($rental)
        );

        //Le Factory predefinite non devono creare record aggiuntivi
        $this->assertDatabaseCount('vehicles', 1);
        $this->assertDatabaseCount('customers', 1);
        $this->assertDatabaseCount('rentals', 1);
    }

    public function test_rental_contains_all_allowed_statuses(): void
    {
        //Controlla l'elenco centrale degli stati ammessi
        $this->assertSame([
            Rental::STATUS_RESERVED,
            Rental::STATUS_ACTIVE,
            Rental::STATUS_COMPLETED,
            Rental::STATUS_CANCELLED,
        ], Rental::STATUSES);

        //Controlla i valori che verranno salvati nel database
        $this->assertSame('reserved', Rental::STATUS_RESERVED);
        $this->assertSame('active', Rental::STATUS_ACTIVE);
        $this->assertSame('completed', Rental::STATUS_COMPLETED);
        $this->assertSame('cancelled', Rental::STATUS_CANCELLED);
    }

    public function test_vehicle_with_rentals_cannot_be_deleted(): void
    {
        //Crea un noleggio con il relativo veicolo
        $rental = Rental::factory()
            ->create();

        $vehicle = $rental->vehicle;

        //La chiave esterna deve impedire di eliminare il veicolo
        $this->expectException(QueryException::class);

        $vehicle->delete();
    }

    public function test_customer_with_rentals_cannot_be_deleted(): void
    {
        //Crea un noleggio con il relativo cliente
        $rental = Rental::factory()
            ->create();

        $customer = $rental->customer;

        //La chiave esterna deve impedire di eliminare il cliente
        $this->expectException(QueryException::class);

        $customer->delete();
    }
}
