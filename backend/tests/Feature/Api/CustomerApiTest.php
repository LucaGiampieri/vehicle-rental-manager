<?php

namespace Tests\Feature\Api;

use App\Models\Customer;
use App\Models\Rental;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CustomerApiTest extends TestCase
{
    use RefreshDatabase;

    // Verifica che un utente non autenticato non possa leggere i clienti
    public function test_guest_cannot_access_customers(): void
    {
        $response = $this->getJson('/api/customers');

        $response->assertUnauthorized();
    }

    // Verifica che un utente autenticato possa visualizzare i clienti
    public function test_authenticated_user_can_list_customers(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        Customer::factory()
            ->count(3)
            ->create();

        $response = $this->getJson('/api/customers');

        $response->assertOk();
        $response->assertJsonCount(3, 'data');

        $response->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'first_name',
                    'last_name',
                    'birth_date',
                    'email',
                    'phone',
                    'tax_code',
                    'driving_license_number',
                    'driving_license_expiry_date',
                    'address',
                    'notes',
                    'is_active',
                    'rentals_count',
                    'created_at',
                    'updated_at',
                ],
            ],
            'links',
            'meta',
        ]);
    }

    // Verifica la ricerca dei clienti usando nome e cognome
    public function test_customers_can_be_searched(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $expectedCustomer = Customer::factory()->create([
            'first_name' => 'Mario',
            'last_name' => 'Rossi',
            'email' => 'mario.rossi@example.com',
        ]);

        Customer::factory()->create([
            'first_name' => 'Luca',
            'last_name' => 'Bianchi',
            'email' => 'luca.bianchi@example.com',
        ]);

        $response = $this->getJson(
            '/api/customers?search=Mario%20Rossi'
        );

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath(
            'data.0.id',
            $expectedCustomer->id
        );
    }

    // Verifica il filtro per stato del cliente
    public function test_customers_can_be_filtered_by_active_status(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $expectedCustomer = Customer::factory()->create([
            'first_name' => 'Cliente',
            'last_name' => 'Disattivato',
            'is_active' => false,
        ]);

        Customer::factory()->create([
            'first_name' => 'Cliente',
            'last_name' => 'Attivo',
            'is_active' => true,
        ]);

        $response = $this->getJson(
            '/api/customers?is_active=false'
        );

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath(
            'data.0.id',
            $expectedCustomer->id
        );
        $response->assertJsonPath(
            'data.0.is_active',
            false
        );
    }

    // Verifica gli ordinamenti disponibili nell’elenco clienti.
    public function test_customers_can_be_sorted(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $rossi = Customer::factory()->create([
            'first_name' => 'Mario',
            'last_name' => 'Rossi',
            'created_at' => now()->subDays(3),
        ]);

        $bianchi = Customer::factory()->create([
            'first_name' => 'Luca',
            'last_name' => 'Bianchi',
            'created_at' => now()->subDay(),
        ]);

        $verdi = Customer::factory()->create([
            'first_name' => 'Anna',
            'last_name' => 'Verdi',
            'created_at' => now(),
        ]);

        /*
         * Rossi possiede due noleggi, ma entrambi meno recenti
         * rispetto al noleggio di Bianchi.
         */
        Rental::factory()
            ->count(2)
            ->for($rossi)
            ->create([
                'status' => Rental::STATUS_COMPLETED,
                'starts_at' => now()->subDays(20),
                'actual_starts_at' => now()->subDays(20),
                'expected_ends_at' => now()->subDays(18),
                'actual_ends_at' => now()->subDays(18),
            ]);

        /*
         * Bianchi possiede un solo noleggio, ma è quello
         * con la data di inizio più recente.
         */
        Rental::factory()
            ->for($bianchi)
            ->create([
                'status' => Rental::STATUS_COMPLETED,
                'starts_at' => now()->subDays(2),
                'actual_starts_at' => now()->subDays(2),
                'expected_ends_at' => now()->subDay(),
                'actual_ends_at' => now()->subDay(),
            ]);

        /*
         * Controlla l’attività più recente:
         * Bianchi deve comparire prima di Rossi.
         *
         * Verdi è il cliente inserito più recentemente,
         * ma non possiede alcun noleggio.
         */
        $activityResponse = $this->getJson(
            '/api/customers?sort=activity_desc'
        );

        $activityResponse->assertOk();
        $activityResponse->assertJsonPath(
            'data.0.id',
            $bianchi->id
        );
        $activityResponse->assertJsonPath(
            'data.1.id',
            $rossi->id
        );

        // Controlla l’ordine alfabetico crescente.
        $alphabeticalResponse = $this->getJson(
            '/api/customers?sort=name_asc'
        );

        $alphabeticalResponse->assertOk();
        $alphabeticalResponse->assertJsonPath(
            'data.0.id',
            $bianchi->id
        );
        $alphabeticalResponse->assertJsonPath(
            'data.1.id',
            $rossi->id
        );
        $alphabeticalResponse->assertJsonPath(
            'data.2.id',
            $verdi->id
        );

        // Controlla l’ordine alfabetico decrescente.
        $reverseResponse = $this->getJson(
            '/api/customers?sort=name_desc'
        );

        $reverseResponse->assertOk();
        $reverseResponse->assertJsonPath(
            'data.0.id',
            $verdi->id
        );
        $reverseResponse->assertJsonPath(
            'data.1.id',
            $rossi->id
        );
        $reverseResponse->assertJsonPath(
            'data.2.id',
            $bianchi->id
        );

        // Controlla che venga mostrato prima chi possiede più noleggi.
        $rentalsResponse = $this->getJson(
            '/api/customers?sort=rentals_desc'
        );

        $rentalsResponse->assertOk();
        $rentalsResponse->assertJsonPath(
            'data.0.id',
            $rossi->id
        );
        $rentalsResponse->assertJsonPath(
            'data.0.rentals_count',
            2
        );

        // Controlla che venga mostrato prima il cliente appena inserito.
        $newestResponse = $this->getJson(
            '/api/customers?sort=newest'
        );

        $newestResponse->assertOk();
        $newestResponse->assertJsonPath(
            'data.0.id',
            $verdi->id
        );
    }

    // Verifica la paginazione configurabile dei clienti
    public function test_customer_list_supports_custom_pagination(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        Customer::factory()
            ->count(5)
            ->create();

        $response = $this->getJson(
            '/api/customers?per_page=2'
        );

        $response->assertOk();
        $response->assertJsonCount(2, 'data');
        $response->assertJsonPath('meta.per_page', 2);
        $response->assertJsonPath('meta.total', 5);
        $response->assertJsonPath('meta.last_page', 3);
    }

    // Verifica che i filtri non validi vengano rifiutati
    public function test_customer_filters_require_valid_values(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $search = str_repeat('A', 101);

        $response = $this->getJson(
            "/api/customers?search={$search}&is_active=maybe&sort=unknown&per_page=101"
        );

        $response->assertUnprocessable();

        $response->assertJsonValidationErrors([
            'search',
            'is_active',
            'sort',
            'per_page',
        ]);
    }

    // Verifica che un utente autenticato possa creare un cliente
    public function test_authenticated_user_can_create_customer(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $birthDate = now()->subYears(30)->toDateString();
        $licenseExpiryDate = now()->addYears(5)->toDateString();

        $customerData = [
            'first_name' => '  Luca  ',
            'last_name' => '  Rossi  ',
            'birth_date' => $birthDate,
            'email' => '  LUCA.ROSSI@EXAMPLE.COM  ',
            'phone' => '  3331234567  ',
            'tax_code' => '  rsslcu96a01h501z  ',
            'driving_license_number' => '  ab123456c  ',
            'driving_license_expiry_date' => $licenseExpiryDate,
            'address' => '  Via Roma 10, Pesaro  ',
            'notes' => '  Cliente dimostrativo  ',
            'is_active' => true,
        ];

        $response = $this->postJson('/api/customers', $customerData);

        $response->assertCreated();
        $response->assertJsonPath('data.first_name', 'Luca');
        $response->assertJsonPath('data.last_name', 'Rossi');
        $response->assertJsonPath(
            'data.email',
            'luca.rossi@example.com'
        );
        $response->assertJsonPath(
            'data.tax_code',
            'RSSLCU96A01H501Z'
        );
        $response->assertJsonPath(
            'data.driving_license_number',
            'AB123456C'
        );
        $response->assertJsonPath('data.birth_date', $birthDate);
        $response->assertJsonPath(
            'data.driving_license_expiry_date',
            $licenseExpiryDate
        );
        $response->assertJsonPath('data.is_active', true);
        $response->assertJsonPath('data.rentals_count', 0);

        $customerId = $response->json('data.id');

        $this->assertDatabaseHas('customers', [
            'id' => $customerId,
            'first_name' => 'Luca',
            'last_name' => 'Rossi',
            'email' => 'luca.rossi@example.com',
            'phone' => '3331234567',
            'tax_code' => 'RSSLCU96A01H501Z',
            'driving_license_number' => 'AB123456C',
            'address' => 'Via Roma 10, Pesaro',
            'notes' => 'Cliente dimostrativo',
        ]);

        $savedCustomer = Customer::findOrFail($customerId);

        $this->assertSame(
            $birthDate,
            $savedCustomer->birth_date->toDateString()
        );
        $this->assertSame(
            $licenseExpiryDate,
            $savedCustomer->driving_license_expiry_date->toDateString()
        );
        $this->assertTrue($savedCustomer->is_active);
    }

    // Verifica che un utente autenticato possa visualizzare un cliente
    public function test_authenticated_user_can_view_customer(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $customer = Customer::factory()->create([
            'first_name' => 'Mario',
            'last_name' => 'Bianchi',
            'email' => 'mario.bianchi@example.com',
            'driving_license_number' => 'CD987654E',
        ]);

        $response = $this->getJson(
            "/api/customers/{$customer->id}"
        );

        $response->assertOk();
        $response->assertJsonPath('data.id', $customer->id);
        $response->assertJsonPath('data.first_name', 'Mario');
        $response->assertJsonPath('data.last_name', 'Bianchi');
        $response->assertJsonPath(
            'data.email',
            'mario.bianchi@example.com'
        );
        $response->assertJsonPath(
            'data.driving_license_number',
            'CD987654E'
        );
        $response->assertJsonPath('data.rentals_count', 0);
    }

    // Verifica riepilogo operativo ed economico della scheda cliente.
    public function test_customer_detail_includes_rental_and_financial_summary(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $customer = Customer::factory()->create();

        $completedVehicle = Vehicle::factory()->create();
        $activeVehicle = Vehicle::factory()->create();
        $reservedVehicle = Vehicle::factory()->create();
        $cancelledVehicle = Vehicle::factory()->create();

        // Noleggio concluso e completamente pagato.
        Rental::factory()
            ->for($customer)
            ->for($completedVehicle)
            ->create([
                'status' => Rental::STATUS_COMPLETED,
                'starts_at' => now()->subMonths(2),
                'actual_starts_at' => now()->subMonths(2),
                'expected_ends_at' => now()->subMonths(2)->addDays(3),
                'actual_ends_at' => now()->subMonths(2)->addDays(3),
                'total_amount' => '300.00',
                'amount_paid' => '300.00',
            ]);

        // Noleggio attualmente in corso.
        $activeRental = Rental::factory()
            ->for($customer)
            ->for($activeVehicle)
            ->create([
                'status' => Rental::STATUS_ACTIVE,
                'starts_at' => now()->subDay(),
                'actual_starts_at' => now()->subDay(),
                'expected_ends_at' => now()->addDays(4),
                'actual_ends_at' => null,
                'total_amount' => '500.00',
                'amount_paid' => '200.00',
            ]);

        // Prossima prenotazione futura.
        $reservedRental = Rental::factory()
            ->for($customer)
            ->for($reservedVehicle)
            ->create([
                'status' => Rental::STATUS_RESERVED,
                'starts_at' => now()->addDays(10),
                'actual_starts_at' => null,
                'expected_ends_at' => now()->addDays(15),
                'actual_ends_at' => null,
                'total_amount' => '400.00',
                'amount_paid' => '100.00',
            ]);

        // Il noleggio annullato deve restare nello storico,
        // ma non deve influenzare i totali economici.
        Rental::factory()
            ->for($customer)
            ->for($cancelledVehicle)
            ->create([
                'status' => Rental::STATUS_CANCELLED,
                'starts_at' => now()->addMonth(),
                'actual_starts_at' => null,
                'expected_ends_at' => now()->addMonth()->addDays(3),
                'actual_ends_at' => null,
                'total_amount' => '900.00',
                'amount_paid' => '0.00',
            ]);

        $response = $this->getJson(
            "/api/customers/{$customer->id}"
        );

        $response->assertOk();

        // Verifica i conteggi suddivisi per stato.
        $response->assertJsonPath(
            'data.rental_summary.total',
            4
        );
        $response->assertJsonPath(
            'data.rental_summary.reserved',
            1
        );
        $response->assertJsonPath(
            'data.rental_summary.active',
            1
        );
        $response->assertJsonPath(
            'data.rental_summary.completed',
            1
        );
        $response->assertJsonPath(
            'data.rental_summary.cancelled',
            1
        );

        /*
         * Noleggi conclusi: 300 euro.
         * Impegni aperti: 500 + 400 = 900 euro.
         * Pagato: 300 + 200 + 100 = 600 euro.
         * Da saldare: 1200 - 600 = 600 euro.
         */
        $response->assertJsonPath(
            'data.financial_summary.completed_total',
            '300.00'
        );
        $response->assertJsonPath(
            'data.financial_summary.open_total',
            '900.00'
        );
        $response->assertJsonPath(
            'data.financial_summary.paid_total',
            '600.00'
        );
        $response->assertJsonPath(
            'data.financial_summary.balance_due',
            '600.00'
        );

        // Verifica il noleggio attualmente attivo.
        $response->assertJsonPath(
            'data.active_rental.id',
            $activeRental->id
        );
        $response->assertJsonPath(
            'data.active_rental.vehicle.id',
            $activeVehicle->id
        );

        // Verifica la prenotazione futura più vicina.
        $response->assertJsonPath(
            'data.next_reservation.id',
            $reservedRental->id
        );
        $response->assertJsonPath(
            'data.next_reservation.vehicle.id',
            $reservedVehicle->id
        );
    }

    // Verifica che un utente autenticato possa modificare un cliente
    public function test_authenticated_user_can_update_customer(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $customer = Customer::factory()->create([
            'first_name' => 'Anna',
            'last_name' => 'Verdi',
            'email' => 'anna.verdi@example.com',
            'tax_code' => 'VRDNNA90A41H501Y',
            'driving_license_number' => 'EF123456G',
            'is_active' => true,
        ]);

        $response = $this->patchJson(
            "/api/customers/{$customer->id}",
            [
                'first_name' => '  Giulia  ',
                'email' => '  ANNA.VERDI@EXAMPLE.COM  ',
                'tax_code' => '  vrdnna90a41h501y  ',
                'driving_license_number' => '  ef123456g  ',
                'phone' => '  3339876543  ',
                'is_active' => false,
            ]
        );

        $response->assertOk();
        $response->assertJsonPath('data.first_name', 'Giulia');
        $response->assertJsonPath('data.last_name', 'Verdi');
        $response->assertJsonPath(
            'data.email',
            'anna.verdi@example.com'
        );
        $response->assertJsonPath(
            'data.tax_code',
            'VRDNNA90A41H501Y'
        );
        $response->assertJsonPath(
            'data.driving_license_number',
            'EF123456G'
        );
        $response->assertJsonPath('data.phone', '3339876543');
        $response->assertJsonPath('data.is_active', false);

        $this->assertDatabaseHas('customers', [
            'id' => $customer->id,
            'first_name' => 'Giulia',
            'last_name' => 'Verdi',
            'email' => 'anna.verdi@example.com',
            'tax_code' => 'VRDNNA90A41H501Y',
            'driving_license_number' => 'EF123456G',
            'phone' => '3339876543',
        ]);

        $savedCustomer = Customer::findOrFail($customer->id);

        $this->assertFalse($savedCustomer->is_active);
    }

    // Verifica che un cliente senza noleggi possa essere eliminato
    public function test_authenticated_user_can_delete_customer_without_rentals(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $customer = Customer::factory()->create();

        $response = $this->deleteJson(
            "/api/customers/{$customer->id}"
        );

        $response->assertNoContent();

        $this->assertDatabaseMissing('customers', [
            'id' => $customer->id,
        ]);
    }

    // Verifica che non sia possibile creare un cliente con dati non validi
    public function test_customer_creation_requires_valid_data(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/customers', [
            'first_name' => '',
            'last_name' => '',
            'birth_date' => now()->addDay()->toDateString(),
            'email' => 'email-non-valida',
            'phone' => str_repeat('1', 31),
            'tax_code' => str_repeat('A', 33),
            'driving_license_number' => '',
            'driving_license_expiry_date' => 'data-non-valida',
            'address' => str_repeat('A', 256),
            'notes' => str_repeat('A', 5001),
            'is_active' => 'non-booleano',
        ]);

        $response->assertUnprocessable();

        $response->assertJsonValidationErrors([
            'first_name',
            'last_name',
            'birth_date',
            'email',
            'phone',
            'tax_code',
            'driving_license_number',
            'driving_license_expiry_date',
            'address',
            'notes',
            'is_active',
        ]);

        $this->assertDatabaseCount('customers', 0);
    }

    // Verifica che email, codice fiscale e patente non possano essere duplicati
    public function test_customer_unique_fields_cannot_be_duplicated(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        Customer::factory()->create([
            'email' => 'cliente@example.com',
            'tax_code' => 'RSSMRA80A01H501U',
            'driving_license_number' => 'GH123456I',
        ]);

        $response = $this->postJson('/api/customers', [
            'first_name' => 'Nuovo',
            'last_name' => 'Cliente',
            'email' => '  CLIENTE@EXAMPLE.COM  ',
            'tax_code' => '  rssmra80a01h501u  ',
            'driving_license_number' => '  gh123456i  ',
            'driving_license_expiry_date' => now()
                ->addYears(5)
                ->toDateString(),
        ]);

        $response->assertUnprocessable();

        $response->assertJsonValidationErrors([
            'email',
            'tax_code',
            'driving_license_number',
        ]);

        $this->assertDatabaseCount('customers', 1);
    }

    // Verifica che una modifica non possa usare l'email di un altro cliente
    public function test_customer_cannot_use_another_customers_email(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $firstCustomer = Customer::factory()->create([
            'email' => 'primo.cliente@example.com',
        ]);

        $secondCustomer = Customer::factory()->create([
            'email' => 'secondo.cliente@example.com',
        ]);

        $response = $this->patchJson(
            "/api/customers/{$secondCustomer->id}",
            [
                'email' => '  PRIMO.CLIENTE@EXAMPLE.COM  ',
            ]
        );

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['email']);

        $this->assertDatabaseHas('customers', [
            'id' => $firstCustomer->id,
            'email' => 'primo.cliente@example.com',
        ]);

        $this->assertDatabaseHas('customers', [
            'id' => $secondCustomer->id,
            'email' => 'secondo.cliente@example.com',
        ]);
    }

    // Verifica che un cliente con noleggi non possa essere eliminato
    public function test_customer_with_rentals_cannot_be_deleted(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $customer = Customer::factory()->create();

        $rental = Rental::factory()
            ->for($customer)
            ->create();

        $response = $this->deleteJson(
            "/api/customers/{$customer->id}"
        );

        $response->assertConflict();

        $response->assertJsonPath(
            'message',
            'Il cliente non può essere eliminato perché possiede noleggi collegati. Disattivalo per conservare lo storico.'
        );

        $this->assertDatabaseHas('customers', [
            'id' => $customer->id,
        ]);

        $this->assertDatabaseHas('rentals', [
            'id' => $rental->id,
            'customer_id' => $customer->id,
        ]);
    }
}
