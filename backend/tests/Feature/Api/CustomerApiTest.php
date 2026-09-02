<?php

namespace Tests\Feature\Api;

use App\Models\Customer;
use App\Models\Rental;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CustomerApiTest extends TestCase
{
    use RefreshDatabase;

    //Verifica che un utente non autenticato non possa leggere i clienti
    public function test_guest_cannot_access_customers(): void
    {
        $response = $this->getJson('/api/customers');

        $response->assertUnauthorized();
    }

    //Verifica che un utente autenticato possa visualizzare i clienti
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

    //Verifica che un utente autenticato possa creare un cliente
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

    //Verifica che un utente autenticato possa visualizzare un cliente
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

    //Verifica che un utente autenticato possa modificare un cliente
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

    //Verifica che un cliente senza noleggi possa essere eliminato
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

    //Verifica che non sia possibile creare un cliente con dati non validi
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

    //Verifica che email, codice fiscale e patente non possano essere duplicati
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

    //Verifica che una modifica non possa usare l'email di un altro cliente
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

    //Verifica che un cliente con noleggi non possa essere eliminato
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
