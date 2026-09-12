<?php

namespace Tests\Feature\Api;

use App\Models\Expense;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ExpenseApiTest extends TestCase
{
    use RefreshDatabase;

    // Verifica che un utente non autenticato non possa leggere le spese
    public function test_guest_cannot_access_expenses(): void
    {
        $response = $this->getJson('/api/expenses');

        $response->assertUnauthorized();
    }

    // Verifica che un utente autenticato possa visualizzare le spese
    public function test_authenticated_user_can_list_expenses(): void
    {
        $this->authenticateUser();

        Expense::factory()
            ->count(3)
            ->create();

        $response = $this->getJson('/api/expenses');

        $response->assertOk();
        $response->assertJsonCount(3, 'data');

        $response->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'vehicle_id',
                    'category',
                    'description',
                    'amount',
                    'expense_date',
                    'expires_on',
                    'is_expired',
                    'mileage',
                    'supplier',
                    'notes',
                    'vehicle' => [
                        'id',
                        'license_plate',
                        'brand',
                        'model',
                        'type',
                    ],
                    'created_at',
                    'updated_at',
                ],
            ],
            'links',
            'meta',
        ]);
    }

    // Verifica la creazione e la normalizzazione di una spesa
    public function test_authenticated_user_can_create_expense(): void
    {
        $this->authenticateUser();

        $vehicle = Vehicle::factory()->create([
            'mileage' => 50000,
        ]);

        $expenseDate = today()->toDateString();
        $expiresOn = today()
            ->addYear()
            ->toDateString();

        $response = $this->postJson('/api/expenses', [
            'vehicle_id' => $vehicle->id,
            'category' => '  MAINTENANCE  ',
            'description' => '  Tagliando completo  ',
            'amount' => 150.50,
            'expense_date' => $expenseDate,
            'expires_on' => $expiresOn,
            'mileage' => 50500,
            'supplier' => '  Officina Centrale  ',
            'notes' => '  Sostituiti olio e filtri  ',
        ]);

        $response->assertCreated();
        $response->assertJsonPath(
            'data.category',
            Expense::CATEGORY_MAINTENANCE
        );
        $response->assertJsonPath(
            'data.description',
            'Tagliando completo'
        );
        $response->assertJsonPath('data.amount', '150.50');
        $response->assertJsonPath(
            'data.expense_date',
            $expenseDate
        );
        $response->assertJsonPath(
            'data.expires_on',
            $expiresOn
        );
        $response->assertJsonPath('data.is_expired', false);
        $response->assertJsonPath('data.mileage', 50500);
        $response->assertJsonPath(
            'data.supplier',
            'Officina Centrale'
        );
        $response->assertJsonPath(
            'data.notes',
            'Sostituiti olio e filtri'
        );
        $response->assertJsonPath(
            'data.vehicle.id',
            $vehicle->id
        );

        $expenseId = $response->json('data.id');

        $this->assertDatabaseHas('expenses', [
            'id' => $expenseId,
            'vehicle_id' => $vehicle->id,
            'category' => Expense::CATEGORY_MAINTENANCE,
            'description' => 'Tagliando completo',
            'amount' => 150.50,
            'mileage' => 50500,
            'supplier' => 'Officina Centrale',
            'notes' => 'Sostituiti olio e filtri',
        ]);

        // Il chilometraggio più recente aggiorna anche il veicolo
        $this->assertSame(
            50500,
            Vehicle::findOrFail($vehicle->id)->mileage
        );
    }

    // Verifica che una spesa storica non riduca il chilometraggio
    public function test_historical_expense_does_not_reduce_vehicle_mileage(): void
    {
        $this->authenticateUser();

        $vehicle = Vehicle::factory()->create([
            'mileage' => 50000,
        ]);

        $response = $this->postJson('/api/expenses', [
            'vehicle_id' => $vehicle->id,
            'category' => Expense::CATEGORY_REPAIR,
            'description' => 'Riparazione precedente',
            'amount' => 300,
            'expense_date' => today()
                ->subMonth()
                ->toDateString(),
            'mileage' => 40000,
        ]);

        $response->assertCreated();

        $this->assertSame(
            50000,
            Vehicle::findOrFail($vehicle->id)->mileage
        );
    }

    // Verifica tutte le principali regole di validazione
    public function test_expense_creation_requires_valid_data(): void
    {
        $this->authenticateUser();

        $response = $this->postJson('/api/expenses', [
            'vehicle_id' => 999,
            'category' => 'categoria_non_valida',
            'description' => '',
            'amount' => 0,
            'expense_date' => today()
                ->addDay()
                ->toDateString(),
            'expires_on' => 'data-non-valida',
            'mileage' => -1,
            'supplier' => str_repeat('A', 151),
            'notes' => str_repeat('A', 5001),
        ]);

        $response->assertUnprocessable();

        $response->assertJsonValidationErrors([
            'vehicle_id',
            'category',
            'description',
            'amount',
            'expense_date',
            'expires_on',
            'mileage',
            'supplier',
            'notes',
        ]);

        $this->assertDatabaseCount('expenses', 0);
    }

    // Verifica che la scadenza non preceda la spesa
    public function test_expiry_cannot_precede_expense_date(): void
    {
        $this->authenticateUser();

        $vehicle = Vehicle::factory()->create();

        $response = $this->postJson('/api/expenses', [
            'vehicle_id' => $vehicle->id,
            'category' => Expense::CATEGORY_INSURANCE,
            'description' => 'Assicurazione annuale',
            'amount' => 800,
            'expense_date' => today()
                ->subMonth()
                ->toDateString(),
            'expires_on' => today()
                ->subMonths(2)
                ->toDateString(),
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['expires_on']);

        $this->assertDatabaseCount('expenses', 0);
    }

    // Verifica la visualizzazione di una singola spesa
    public function test_authenticated_user_can_view_expense(): void
    {
        $this->authenticateUser();

        $vehicle = Vehicle::factory()->create([
            'brand' => 'Fiat',
            'model' => 'Panda',
        ]);

        $expense = Expense::factory()->create([
            'vehicle_id' => $vehicle->id,
            'category' => Expense::CATEGORY_ROAD_TAX,
            'description' => 'Bollo annuale',
            'expires_on' => today()->subDay(),
        ]);

        $response = $this->getJson(
            "/api/expenses/{$expense->id}"
        );

        $response->assertOk();
        $response->assertJsonPath('data.id', $expense->id);
        $response->assertJsonPath(
            'data.category',
            Expense::CATEGORY_ROAD_TAX
        );
        $response->assertJsonPath(
            'data.description',
            'Bollo annuale'
        );
        $response->assertJsonPath('data.is_expired', true);
        $response->assertJsonPath(
            'data.vehicle.brand',
            'Fiat'
        );
        $response->assertJsonPath(
            'data.vehicle.model',
            'Panda'
        );
    }

    // Verifica la modifica parziale di una spesa
    public function test_authenticated_user_can_update_expense(): void
    {
        $this->authenticateUser();

        $vehicle = Vehicle::factory()->create([
            'mileage' => 60000,
        ]);

        $expense = Expense::factory()->create([
            'vehicle_id' => $vehicle->id,
            'category' => Expense::CATEGORY_MAINTENANCE,
            'description' => 'Descrizione iniziale',
            'amount' => 100,
            'expense_date' => today()->subDays(5),
            'expires_on' => today()->addMonth(),
            'mileage' => 59000,
        ]);

        $response = $this->patchJson(
            "/api/expenses/{$expense->id}",
            [
                'category' => '  REPAIR  ',
                'description' => '  Riparazione definitiva  ',
                'amount' => 450.75,
                'mileage' => 61000,
                'supplier' => '  Nuova Officina  ',
                'notes' => '  Intervento completato  ',
            ]
        );

        $response->assertOk();
        $response->assertJsonPath(
            'data.category',
            Expense::CATEGORY_REPAIR
        );
        $response->assertJsonPath(
            'data.description',
            'Riparazione definitiva'
        );
        $response->assertJsonPath('data.amount', '450.75');
        $response->assertJsonPath('data.mileage', 61000);
        $response->assertJsonPath(
            'data.supplier',
            'Nuova Officina'
        );
        $response->assertJsonPath(
            'data.notes',
            'Intervento completato'
        );

        $this->assertDatabaseHas('expenses', [
            'id' => $expense->id,
            'category' => Expense::CATEGORY_REPAIR,
            'description' => 'Riparazione definitiva',
            'amount' => 450.75,
            'mileage' => 61000,
            'supplier' => 'Nuova Officina',
            'notes' => 'Intervento completato',
        ]);

        $this->assertSame(
            61000,
            Vehicle::findOrFail($vehicle->id)->mileage
        );
    }

    // Verifica il confronto con la scadenza già presente
    public function test_updating_expense_date_cannot_make_expiry_invalid(): void
    {
        $this->authenticateUser();

        $originalExpenseDate = today()->subDays(10);
        $expiresOn = today()->subDays(5);

        $expense = Expense::factory()->create([
            'expense_date' => $originalExpenseDate,
            'expires_on' => $expiresOn,
        ]);

        // La nuova data diventerebbe successiva alla scadenza esistente
        $response = $this->patchJson(
            "/api/expenses/{$expense->id}",
            [
                'expense_date' => today()
                    ->subDay()
                    ->toDateString(),
            ]
        );

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['expires_on']);

        $this->assertSame(
            $originalExpenseDate->toDateString(),
            $expense->fresh()->expense_date->toDateString()
        );
    }

    // Verifica i filtri dell'elenco
    public function test_expenses_can_be_filtered(): void
    {
        $this->authenticateUser();

        $firstVehicle = Vehicle::factory()->create();
        $secondVehicle = Vehicle::factory()->create();

        $firstExpense = Expense::factory()->create([
            'vehicle_id' => $firstVehicle->id,
            'category' => Expense::CATEGORY_FUEL,
            'description' => 'Carburante aziendale',
            'supplier' => 'Distributore Centrale',
            'expense_date' => today()->subDays(10),
            'expires_on' => today()->addDays(5),
        ]);

        Expense::factory()->create([
            'vehicle_id' => $firstVehicle->id,
            'category' => Expense::CATEGORY_MAINTENANCE,
            'description' => 'Tagliando ordinario',
            'expense_date' => today()->subDays(5),
            'expires_on' => null,
        ]);

        Expense::factory()->create([
            'vehicle_id' => $secondVehicle->id,
            'category' => Expense::CATEGORY_FUEL,
            'description' => 'Secondo rifornimento',
            'expense_date' => today()->subDays(2),
            'expires_on' => today()->addDays(10),
        ]);

        // Filtra contemporaneamente per veicolo e categoria
        $vehicleCategoryResponse = $this->getJson(
            '/api/expenses?vehicle_id='
            .$firstVehicle->id
            .'&category='.Expense::CATEGORY_FUEL
        );

        $vehicleCategoryResponse->assertOk();
        $vehicleCategoryResponse->assertJsonCount(1, 'data');
        $vehicleCategoryResponse->assertJsonPath(
            'data.0.id',
            $firstExpense->id
        );

        // Cerca dentro descrizione e fornitore
        $searchResponse = $this->getJson(
            '/api/expenses?search=Centrale'
        );

        $searchResponse->assertOk();
        $searchResponse->assertJsonCount(1, 'data');
        $searchResponse->assertJsonPath(
            'data.0.id',
            $firstExpense->id
        );

        // Filtra per intervallo della data della spesa
        $dateResponse = $this->getJson(
            '/api/expenses?date_from='
            .today()->subDays(6)->toDateString()
            .'&date_to='.today()->toDateString()
        );

        $dateResponse->assertOk();
        $dateResponse->assertJsonCount(2, 'data');

        // Cerca le scadenze entro sei giorni
        $expiryResponse = $this->getJson(
            '/api/expenses?expires_before='
            .today()->addDays(6)->toDateString()
        );

        $expiryResponse->assertOk();
        $expiryResponse->assertJsonCount(1, 'data');
        $expiryResponse->assertJsonPath(
            'data.0.id',
            $firstExpense->id
        );
    }

    // Verifica la paginazione configurabile delle spese
    public function test_expense_list_supports_custom_pagination(): void
    {
        $this->authenticateUser();

        Expense::factory()
            ->count(5)
            ->create();

        $response = $this->getJson(
            '/api/expenses?per_page=2'
        );

        $response->assertOk();
        $response->assertJsonCount(2, 'data');
        $response->assertJsonPath('meta.per_page', 2);
        $response->assertJsonPath('meta.total', 5);
        $response->assertJsonPath('meta.last_page', 3);
    }

    // Verifica che i filtri non validi vengano rifiutati
    public function test_expense_filters_require_valid_values(): void
    {
        $this->authenticateUser();

        $search = str_repeat('A', 101);

        $queryString = http_build_query([
            'vehicle_id' => 999999,
            'category' => 'invalid_category',
            'expires_before' => 'invalid-date',
            'search' => $search,
            'per_page' => 101,
        ]);

        $response = $this->getJson(
            "/api/expenses?{$queryString}"
        );

        $response->assertUnprocessable();

        $response->assertJsonValidationErrors([
            'vehicle_id',
            'category',
            'expires_before',
            'search',
            'per_page',
        ]);
    }

    // Verifica che l'intervallo dei filtri sia coerente
    public function test_expense_filter_rejects_invalid_date_range(): void
    {
        $this->authenticateUser();

        $response = $this->getJson(
            '/api/expenses?date_from=2026-12-31'
            .'&date_to=2026-01-01'
        );

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['date_to']);
    }

    // Verifica l'eliminazione di una spesa
    public function test_authenticated_user_can_delete_expense(): void
    {
        $this->authenticateUser();

        $expense = Expense::factory()->create();

        $response = $this->deleteJson(
            "/api/expenses/{$expense->id}"
        );

        $response->assertNoContent();

        $this->assertDatabaseMissing('expenses', [
            'id' => $expense->id,
        ]);
    }

    // Crea e autentica un utente fittizio
    private function authenticateUser(): void
    {
        $user = User::factory()->create();

        Sanctum::actingAs($user);
    }
}
