<?php

namespace Tests\Feature\Models;

use App\Models\Customer;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class CustomerTest extends TestCase
{
    //Ricrea il database di test per ogni metodo
    use RefreshDatabase;

    public function test_customer_can_be_created_using_the_factory_data(): void
    {
        //Genera i dati con la Factory e li salva tramite i campi fillable
        $customer = Customer::create(
            Customer::factory()
                ->raw()
        );

        //Controlla che il cliente sia stato inserito nel database
        $this->assertDatabaseHas('customers', [
            'id' => $customer->id,
            'email' => $customer->email,
            'driving_license_number' => $customer->driving_license_number,
            'is_active' => 1,
        ]);

        //Controlla che la Factory generi identificativi della lunghezza prevista
        $this->assertSame(16, strlen($customer->tax_code));
        $this->assertSame(10, strlen($customer->driving_license_number));

        //Controlla le conversioni automatiche definite nel Model
        $this->assertInstanceOf(Carbon::class, $customer->birth_date);
        $this->assertInstanceOf(
            Carbon::class,
            $customer->driving_license_expiry_date
        );
        $this->assertTrue($customer->is_active);
    }

    public function test_optional_fields_can_be_null(): void
    {
        //Crea due clienti senza i dati facoltativi
        //Questo verifica anche che unique permetta più valori null
        $firstCustomer = Customer::factory()
            ->create([
                'birth_date' => null,
                'email' => null,
                'phone' => null,
                'tax_code' => null,
                'address' => null,
                'notes' => null,
            ]);

        $secondCustomer = Customer::factory()
            ->create([
                'birth_date' => null,
                'email' => null,
                'phone' => null,
                'tax_code' => null,
                'address' => null,
                'notes' => null,
            ]);

        //Controlla che entrambi i clienti siano stati salvati
        $this->assertDatabaseCount('customers', 2);
        $this->assertNull($firstCustomer->birth_date);
        $this->assertNull($firstCustomer->email);
        $this->assertNull($secondCustomer->tax_code);
    }

    public function test_email_must_be_unique_when_provided(): void
    {
        //Crea il primo cliente
        $customer = Customer::factory()
            ->create();

        //La seconda email uguale deve essere rifiutata dal database
        $this->expectException(QueryException::class);

        Customer::factory()
            ->create([
                'email' => $customer->email,
            ]);
    }

    public function test_tax_code_must_be_unique_when_provided(): void
    {
        //Crea il primo cliente
        $customer = Customer::factory()
            ->create();

        //Il secondo codice fiscale uguale deve essere rifiutato
        $this->expectException(QueryException::class);

        Customer::factory()
            ->create([
                'tax_code' => $customer->tax_code,
            ]);
    }

    public function test_driving_license_number_must_be_unique(): void
    {
        //Crea il primo cliente
        $customer = Customer::factory()
            ->create();

        //La seconda patente uguale deve essere rifiutata
        $this->expectException(QueryException::class);

        Customer::factory()
            ->create([
                'driving_license_number' => $customer->driving_license_number,
            ]);
    }
}
