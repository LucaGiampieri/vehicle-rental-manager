<?php

namespace Tests\Feature\Api;

use App\Models\Customer;
use App\Models\Rental;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RentalApiTest extends TestCase
{
    use RefreshDatabase;

    //Verifica che un utente non autenticato non possa leggere i noleggi
    public function test_guest_cannot_access_rentals(): void
    {
        $response = $this->getJson('/api/rentals');

        $response->assertUnauthorized();
    }

    //Verifica che un utente autenticato possa visualizzare i noleggi
    public function test_authenticated_user_can_list_rentals(): void
    {
        $this->authenticateUser();

        Rental::factory()
            ->count(3)
            ->create();

        $response = $this->getJson('/api/rentals');

        $response->assertOk();
        $response->assertJsonCount(3, 'data');

        $response->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'vehicle_id',
                    'customer_id',
                    'status',
                    'starts_at',
                    'actual_starts_at',
                    'expected_ends_at',
                    'actual_ends_at',
                    'daily_rate',
                    'chargeable_days',
                    'total_amount',
                    'amount_paid',
                    'balance_due',
                    'start_mileage',
                    'end_mileage',
                    'notes',
                    'vehicle' => [
                        'id',
                        'license_plate',
                        'brand',
                        'model',
                        'type',
                    ],
                    'customer' => [
                        'id',
                        'first_name',
                        'last_name',
                        'email',
                        'phone',
                        'driving_license_number',
                        'driving_license_expiry_date',
                    ],
                    'created_at',
                    'updated_at',
                ],
            ],
            'links',
            'meta',
        ]);
    }

    //Verifica la creazione e il calcolo automatico del totale
    public function test_authenticated_user_can_create_rental(): void
    {
        $this->authenticateUser();

        $vehicle = Vehicle::factory()->create([
            'is_active' => true,
        ]);

        $customer = Customer::factory()->create([
            'is_active' => true,
            'driving_license_expiry_date' => now()
                ->addYears(2)
                ->toDateString(),
        ]);

        $startsAt = now()
            ->addDays(2)
            ->startOfHour();

        //Trenta ore corrispondono a due giornate addebitabili
        $expectedEndsAt = $startsAt
            ->copy()
            ->addHours(30);

        $response = $this->postJson('/api/rentals', [
            'vehicle_id' => $vehicle->id,
            'customer_id' => $customer->id,
            'starts_at' => $startsAt->toDateTimeString(),
            'expected_ends_at' => $expectedEndsAt->toDateTimeString(),
            'daily_rate' => 50,
            'amount_paid' => 20,
            'notes' => '  Prenotazione dimostrativa  ',
        ]);

        $response->assertCreated();
        $response->assertJsonPath(
            'data.status',
            Rental::STATUS_RESERVED
        );
        $response->assertJsonPath('data.chargeable_days', 2);
        $response->assertJsonPath('data.daily_rate', '50.00');
        $response->assertJsonPath('data.total_amount', '100.00');
        $response->assertJsonPath('data.amount_paid', '20.00');
        $response->assertJsonPath('data.balance_due', '80.00');
        $response->assertJsonPath(
            'data.notes',
            'Prenotazione dimostrativa'
        );
        $response->assertJsonPath(
            'data.vehicle.id',
            $vehicle->id
        );
        $response->assertJsonPath(
            'data.customer.id',
            $customer->id
        );

        $rentalId = $response->json('data.id');
        $savedRental = Rental::findOrFail($rentalId);

        $this->assertSame(
            Rental::STATUS_RESERVED,
            $savedRental->status
        );
        $this->assertSame('100.00', $savedRental->total_amount);
        $this->assertSame('20.00', $savedRental->amount_paid);
        $this->assertNull($savedRental->actual_starts_at);
        $this->assertNull($savedRental->actual_ends_at);
        $this->assertNull($savedRental->start_mileage);
        $this->assertNull($savedRental->end_mileage);
    }

    //Verifica le regole di base della creazione
    public function test_rental_creation_requires_valid_data(): void
    {
        $this->authenticateUser();

        $response = $this->postJson('/api/rentals', [
            'vehicle_id' => 999,
            'customer_id' => 999,
            'starts_at' => now()
                ->subDay()
                ->toDateTimeString(),
            'expected_ends_at' => now()
                ->subDays(2)
                ->toDateTimeString(),
            'daily_rate' => 0,
            'amount_paid' => -1,
            'notes' => str_repeat('A', 5001),
        ]);

        $response->assertUnprocessable();

        $response->assertJsonValidationErrors([
            'vehicle_id',
            'customer_id',
            'starts_at',
            'expected_ends_at',
            'daily_rate',
            'amount_paid',
            'notes',
        ]);

        $this->assertDatabaseCount('rentals', 0);
    }

    //Verifica che il pagamento non possa superare il totale
    public function test_initial_payment_cannot_exceed_rental_total(): void
    {
        $this->authenticateUser();

        $vehicle = Vehicle::factory()->create();

        $customer = Customer::factory()->create([
            'driving_license_expiry_date' => now()
                ->addYears(2)
                ->toDateString(),
        ]);

        $startsAt = now()->addDay();
        $expectedEndsAt = $startsAt->copy()->addDay();

        $response = $this->postJson('/api/rentals', [
            'vehicle_id' => $vehicle->id,
            'customer_id' => $customer->id,
            'starts_at' => $startsAt->toDateTimeString(),
            'expected_ends_at' => $expectedEndsAt->toDateTimeString(),
            'daily_rate' => 50,
            'amount_paid' => 51,
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['amount_paid']);

        $this->assertDatabaseCount('rentals', 0);
    }

    //Verifica che due prenotazioni non possano sovrapporsi
    public function test_overlapping_rental_cannot_be_created(): void
    {
        $this->authenticateUser();

        $vehicle = Vehicle::factory()->create();

        $customer = Customer::factory()->create([
            'driving_license_expiry_date' => now()
                ->addYears(2)
                ->toDateString(),
        ]);

        $startsAt = now()->addDays(2);
        $expectedEndsAt = $startsAt->copy()->addDays(2);

        Rental::factory()->create([
            'vehicle_id' => $vehicle->id,
            'customer_id' => $customer->id,
            'status' => Rental::STATUS_RESERVED,
            'starts_at' => $startsAt,
            'expected_ends_at' => $expectedEndsAt,
        ]);

        $response = $this->postJson('/api/rentals', [
            'vehicle_id' => $vehicle->id,
            'customer_id' => $customer->id,
            'starts_at' => $startsAt
                ->copy()
                ->addDay()
                ->toDateTimeString(),
            'expected_ends_at' => $expectedEndsAt
                ->copy()
                ->addDay()
                ->toDateTimeString(),
            'daily_rate' => 60,
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['vehicle_id']);

        $this->assertDatabaseCount('rentals', 1);
    }

    //Verifica che due noleggi consecutivi siano consentiti
    public function test_rentals_can_be_created_back_to_back(): void
    {
        $this->authenticateUser();

        $vehicle = Vehicle::factory()->create();

        $customer = Customer::factory()->create([
            'driving_license_expiry_date' => now()
                ->addYears(2)
                ->toDateString(),
        ]);

        $firstStartsAt = now()->addDays(2);
        $firstEndsAt = $firstStartsAt->copy()->addDays(2);

        Rental::factory()->create([
            'vehicle_id' => $vehicle->id,
            'customer_id' => $customer->id,
            'status' => Rental::STATUS_RESERVED,
            'starts_at' => $firstStartsAt,
            'expected_ends_at' => $firstEndsAt,
        ]);

        //Il secondo noleggio comincia esattamente alla fine del primo
        $response = $this->postJson('/api/rentals', [
            'vehicle_id' => $vehicle->id,
            'customer_id' => $customer->id,
            'starts_at' => $firstEndsAt->toDateTimeString(),
            'expected_ends_at' => $firstEndsAt
                ->copy()
                ->addDay()
                ->toDateTimeString(),
            'daily_rate' => 60,
        ]);

        $response->assertCreated();

        $this->assertDatabaseCount('rentals', 2);
    }

    //Verifica che mezzo e cliente debbano essere attivi
    public function test_inactive_vehicle_and_customer_cannot_be_rented(): void
    {
        $this->authenticateUser();

        $vehicle = Vehicle::factory()->create([
            'is_active' => false,
        ]);

        $customer = Customer::factory()->create([
            'is_active' => false,
            'driving_license_expiry_date' => now()
                ->addYears(2)
                ->toDateString(),
        ]);

        $startsAt = now()->addDay();

        $response = $this->postJson('/api/rentals', [
            'vehicle_id' => $vehicle->id,
            'customer_id' => $customer->id,
            'starts_at' => $startsAt->toDateTimeString(),
            'expected_ends_at' => $startsAt
                ->copy()
                ->addDay()
                ->toDateTimeString(),
            'daily_rate' => 50,
        ]);

        $response->assertUnprocessable();

        $response->assertJsonValidationErrors([
            'vehicle_id',
            'customer_id',
        ]);

        $this->assertDatabaseCount('rentals', 0);
    }

    //Verifica che la patente copra tutto il periodo previsto
    public function test_customer_license_must_cover_rental_period(): void
    {
        $this->authenticateUser();

        $vehicle = Vehicle::factory()->create();

        $customer = Customer::factory()->create([
            'is_active' => true,
            'driving_license_expiry_date' => now()
                ->addDays(2)
                ->toDateString(),
        ]);

        $startsAt = now()->addDay();

        $response = $this->postJson('/api/rentals', [
            'vehicle_id' => $vehicle->id,
            'customer_id' => $customer->id,
            'starts_at' => $startsAt->toDateTimeString(),
            'expected_ends_at' => $startsAt
                ->copy()
                ->addDays(4)
                ->toDateTimeString(),
            'daily_rate' => 50,
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['customer_id']);

        $this->assertDatabaseCount('rentals', 0);
    }

    //Verifica la visualizzazione di un singolo noleggio
    public function test_authenticated_user_can_view_rental(): void
    {
        $this->authenticateUser();

        $vehicle = Vehicle::factory()->create([
            'brand' => 'Fiat',
            'model' => 'Panda',
        ]);

        $customer = Customer::factory()->create([
            'first_name' => 'Mario',
            'last_name' => 'Rossi',
        ]);

        $rental = Rental::factory()->create([
            'vehicle_id' => $vehicle->id,
            'customer_id' => $customer->id,
        ]);

        $response = $this->getJson(
            "/api/rentals/{$rental->id}"
        );

        $response->assertOk();
        $response->assertJsonPath('data.id', $rental->id);
        $response->assertJsonPath(
            'data.vehicle.brand',
            'Fiat'
        );
        $response->assertJsonPath(
            'data.vehicle.model',
            'Panda'
        );
        $response->assertJsonPath(
            'data.customer.first_name',
            'Mario'
        );
        $response->assertJsonPath(
            'data.customer.last_name',
            'Rossi'
        );
    }

    //Verifica la modifica di una prenotazione
    public function test_reserved_rental_can_be_updated(): void
    {
        $this->authenticateUser();

        $vehicle = Vehicle::factory()->create();

        $customer = Customer::factory()->create([
            'driving_license_expiry_date' => now()
                ->addYears(2)
                ->toDateString(),
        ]);

        $startsAt = now()->addDays(2);
        $originalEndsAt = $startsAt->copy()->addDay();
        $newEndsAt = $startsAt->copy()->addDays(3);

        $rental = Rental::factory()->create([
            'vehicle_id' => $vehicle->id,
            'customer_id' => $customer->id,
            'status' => Rental::STATUS_RESERVED,
            'starts_at' => $startsAt,
            'expected_ends_at' => $originalEndsAt,
            'daily_rate' => 50,
            'total_amount' => 50,
            'amount_paid' => 10,
        ]);

        $response = $this->patchJson(
            "/api/rentals/{$rental->id}",
            [
                'expected_ends_at' => $newEndsAt
                    ->toDateTimeString(),
                'daily_rate' => 60,
                'amount_paid' => 100,
                'notes' => '  Periodo modificato  ',
            ]
        );

        $response->assertOk();
        $response->assertJsonPath('data.chargeable_days', 3);
        $response->assertJsonPath('data.daily_rate', '60.00');
        $response->assertJsonPath('data.total_amount', '180.00');
        $response->assertJsonPath('data.amount_paid', '100.00');
        $response->assertJsonPath('data.balance_due', '80.00');
        $response->assertJsonPath(
            'data.notes',
            'Periodo modificato'
        );

        $savedRental = Rental::findOrFail($rental->id);

        $this->assertSame('60.00', $savedRental->daily_rate);
        $this->assertSame('180.00', $savedRental->total_amount);
        $this->assertSame('100.00', $savedRental->amount_paid);
    }

    //Verifica che i dati principali siano bloccati dopo la consegna
    public function test_active_rental_booking_data_cannot_be_changed(): void
    {
        $this->authenticateUser();

        $rental = Rental::factory()->create([
            'status' => Rental::STATUS_ACTIVE,
            'starts_at' => now()->subDay(),
            'actual_starts_at' => now()->subDay(),
            'expected_ends_at' => now()->addDay(),
            'daily_rate' => 50,
            'total_amount' => 100,
            'start_mileage' => 30000,
        ]);

        $response = $this->patchJson(
            "/api/rentals/{$rental->id}",
            [
                'daily_rate' => 75,
            ]
        );

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['rental']);

        $this->assertSame(
            '50.00',
            $rental->fresh()->daily_rate
        );
    }

    //Verifica la consegna e l'attivazione del noleggio
    public function test_reserved_rental_can_be_activated(): void
    {
        $this->authenticateUser();

        $vehicle = Vehicle::factory()->create([
            'mileage' => 35000,
            'is_active' => true,
        ]);

        $customer = Customer::factory()->create([
            'is_active' => true,
            'driving_license_expiry_date' => now()
                ->addYears(2)
                ->toDateString(),
        ]);

        $startsAt = now()->subHour();

        $rental = Rental::factory()->create([
            'vehicle_id' => $vehicle->id,
            'customer_id' => $customer->id,
            'status' => Rental::STATUS_RESERVED,
            'starts_at' => $startsAt,
            'expected_ends_at' => $startsAt
                ->copy()
                ->addDays(2),
            'daily_rate' => 50,
            'total_amount' => 100,
            'amount_paid' => 0,
        ]);

        $response = $this->patchJson(
            "/api/rentals/{$rental->id}/activate",
            [
                'start_mileage' => 35100,
                'amount_paid' => 30,
                'notes' => '  Mezzo consegnato  ',
            ]
        );

        $response->assertOk();
        $response->assertJsonPath(
            'data.status',
            Rental::STATUS_ACTIVE
        );
        $response->assertJsonPath('data.start_mileage', 35100);
        $response->assertJsonPath('data.amount_paid', '30.00');
        $response->assertJsonPath(
            'data.notes',
            'Mezzo consegnato'
        );

        $savedRental = Rental::findOrFail($rental->id);

        $this->assertNotNull($savedRental->actual_starts_at);
        $this->assertSame(35100, $savedRental->start_mileage);
        $this->assertSame(
            35100,
            Vehicle::findOrFail($vehicle->id)->mileage
        );
    }

    //Verifica il rientro e il completamento del noleggio
    public function test_active_rental_can_be_completed(): void
    {
        $this->authenticateUser();

        $vehicle = Vehicle::factory()->create([
            'mileage' => 40000,
        ]);

        $customer = Customer::factory()->create();

        $startsAt = now()->subDays(2);

        $rental = Rental::factory()->create([
            'vehicle_id' => $vehicle->id,
            'customer_id' => $customer->id,
            'status' => Rental::STATUS_ACTIVE,
            'starts_at' => $startsAt,
            'actual_starts_at' => $startsAt,
            'expected_ends_at' => now()->addDay(),
            'daily_rate' => 50,
            'total_amount' => 150,
            'amount_paid' => 50,
            'start_mileage' => 40000,
        ]);

        $response = $this->patchJson(
            "/api/rentals/{$rental->id}/complete",
            [
                'end_mileage' => 40500,
                'amount_paid' => 150,
                'notes' => '  Mezzo riconsegnato  ',
            ]
        );

        $response->assertOk();
        $response->assertJsonPath(
            'data.status',
            Rental::STATUS_COMPLETED
        );
        $response->assertJsonPath('data.end_mileage', 40500);
        $response->assertJsonPath('data.amount_paid', '150.00');
        $response->assertJsonPath('data.balance_due', '0.00');
        $response->assertJsonPath(
            'data.notes',
            'Mezzo riconsegnato'
        );

        $savedRental = Rental::findOrFail($rental->id);

        $this->assertNotNull($savedRental->actual_ends_at);
        $this->assertSame(40500, $savedRental->end_mileage);
        $this->assertSame(
            40500,
            Vehicle::findOrFail($vehicle->id)->mileage
        );
    }

    //Verifica che il chilometraggio non possa diminuire
    public function test_rental_cannot_be_completed_with_lower_mileage(): void
    {
        $this->authenticateUser();

        $rental = Rental::factory()->create([
            'status' => Rental::STATUS_ACTIVE,
            'starts_at' => now()->subDay(),
            'actual_starts_at' => now()->subDay(),
            'expected_ends_at' => now()->addDay(),
            'start_mileage' => 50000,
        ]);

        $response = $this->patchJson(
            "/api/rentals/{$rental->id}/complete",
            [
                'end_mileage' => 49999,
            ]
        );

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['end_mileage']);

        $this->assertSame(
            Rental::STATUS_ACTIVE,
            $rental->fresh()->status
        );
    }

    //Verifica che una prenotazione possa essere annullata
    public function test_reserved_rental_can_be_cancelled(): void
    {
        $this->authenticateUser();

        $rental = Rental::factory()->create([
            'status' => Rental::STATUS_RESERVED,
        ]);

        $response = $this->patchJson(
            "/api/rentals/{$rental->id}/cancel"
        );

        $response->assertOk();
        $response->assertJsonPath(
            'data.status',
            Rental::STATUS_CANCELLED
        );

        $this->assertDatabaseHas('rentals', [
            'id' => $rental->id,
            'status' => Rental::STATUS_CANCELLED,
        ]);
    }

    //Verifica che una prenotazione senza pagamenti possa essere eliminata
    public function test_unpaid_reserved_rental_can_be_deleted(): void
    {
        $this->authenticateUser();

        $rental = Rental::factory()->create([
            'status' => Rental::STATUS_RESERVED,
            'amount_paid' => 0,
        ]);

        $response = $this->deleteJson(
            "/api/rentals/{$rental->id}"
        );

        $response->assertNoContent();

        $this->assertDatabaseMissing('rentals', [
            'id' => $rental->id,
        ]);
    }

    //Verifica la protezione dello storico e dei pagamenti
    public function test_historical_or_paid_rentals_cannot_be_deleted(): void
    {
        $this->authenticateUser();

        $completedRental = Rental::factory()->create([
            'status' => Rental::STATUS_COMPLETED,
            'amount_paid' => 0,
        ]);

        $paidReservation = Rental::factory()->create([
            'status' => Rental::STATUS_RESERVED,
            'amount_paid' => 20,
        ]);

        $completedResponse = $this->deleteJson(
            "/api/rentals/{$completedRental->id}"
        );

        $paidResponse = $this->deleteJson(
            "/api/rentals/{$paidReservation->id}"
        );

        $completedResponse->assertConflict();
        $paidResponse->assertConflict();

        $completedResponse->assertJsonPath(
            'message',
            'Il noleggio non può essere eliminato perché è iniziato, completato oppure possiede pagamenti registrati.'
        );

        $this->assertDatabaseHas('rentals', [
            'id' => $completedRental->id,
        ]);

        $this->assertDatabaseHas('rentals', [
            'id' => $paidReservation->id,
        ]);
    }

    //Crea e autentica un utente fittizio per i test protetti
    private function authenticateUser(): void
    {
        $user = User::factory()->create();

        Sanctum::actingAs($user);
    }
}
