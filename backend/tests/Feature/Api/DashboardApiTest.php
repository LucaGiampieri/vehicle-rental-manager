<?php

namespace Tests\Feature\Api;

use App\Models\Customer;
use App\Models\Expense;
use App\Models\ParkingSpace;
use App\Models\Rental;
use App\Models\User;
use App\Models\Vehicle;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DashboardApiTest extends TestCase
{
    use RefreshDatabase;

    // Crea e autentica un utente fittizio.
    private function authenticateUser(): void
    {
        Sanctum::actingAs(
            User::factory()->create()
        );
    }

    // Crea un noleggio con i valori necessari per il test.
    private function createRental(
        Vehicle $vehicle,
        array $attributes = []
    ): Rental {
        return Rental::factory()->create(array_merge([
            'vehicle_id' => $vehicle->id,
            'status' => Rental::STATUS_COMPLETED,
            'starts_at' => Carbon::parse('2026-09-02 10:00:00'),
            'expected_ends_at' => Carbon::parse('2026-09-04 10:00:00'),
            'actual_starts_at' => Carbon::parse('2026-09-02 10:00:00'),
            'actual_ends_at' => Carbon::parse('2026-09-04 10:00:00'),
            'daily_rate' => 50,
            'total_amount' => 100,
            'amount_paid' => 100,
            'start_mileage' => 10000,
            'end_mileage' => 10200,
        ], $attributes));
    }

    // Crea una spesa associata al veicolo.
    private function createExpense(
        Vehicle $vehicle,
        array $attributes = []
    ): Expense {
        return Expense::factory()->create(array_merge([
            'vehicle_id' => $vehicle->id,
            'category' => Expense::CATEGORY_MAINTENANCE,
            'description' => 'Spesa di test',
            'amount' => 100,
            'expense_date' => '2026-09-10',
            'expires_on' => null,
        ], $attributes));
    }

    // La dashboard deve essere protetta da Sanctum.
    public function test_guest_cannot_access_dashboard(): void
    {
        $this->getJson('/api/dashboard')
            ->assertUnauthorized();
    }

    // Senza filtri vengono analizzati automaticamente gli ultimi 30 giorni.
    public function test_dashboard_uses_default_period(): void
    {
        $this->travelTo(
            Carbon::parse('2026-09-11 12:00:00')
        );

        $this->authenticateUser();

        $response = $this->getJson('/api/dashboard');

        $response->assertOk();
        $response->assertJsonPath(
            'data.period.date_from',
            '2026-08-13'
        );
        $response->assertJsonPath(
            'data.period.date_to',
            '2026-09-11'
        );
        $response->assertJsonPath('data.period.days', 30);
        $response->assertJsonPath(
            'data.financial.contracted_revenue',
            0
        );
        $response->assertJsonPath(
            'data.utilization.utilization_rate',
            0
        );

        $response->assertJsonStructure([
            'data' => [
                'period',
                'filters',
                'financial' => [
                    'contracted_revenue',
                    'completed_revenue',
                    'amount_collected',
                    'amount_outstanding',
                    'total_expenses',
                    'projected_profit',
                    'realized_profit',
                    'cash_balance',
                    'expenses_by_category',
                ],
                'rentals',
                'active_rentals',
                'fleet',
                'garage',
                'utilization',
                'deadlines',
            ],
        ]);
    }

    // Verifica ricavi, incassi, crediti, spese e profitti.
    public function test_dashboard_calculates_financial_summary(): void
    {
        $this->travelTo(
            Carbon::parse('2026-09-11 12:00:00')
        );

        $this->authenticateUser();

        $vehicle = Vehicle::factory()->create();

        // Noleggio completato: 300 euro interamente incassati.
        $this->createRental($vehicle, [
            'status' => Rental::STATUS_COMPLETED,
            'starts_at' => Carbon::parse('2026-09-02 10:00:00'),
            'total_amount' => 300,
            'amount_paid' => 300,
        ]);

        // Noleggio attivo: 500 euro, di cui 200 incassati.
        $this->createRental($vehicle, [
            'status' => Rental::STATUS_ACTIVE,
            'starts_at' => Carbon::parse('2026-09-10 10:00:00'),
            'actual_starts_at' => Carbon::parse('2026-09-10 10:00:00'),
            'actual_ends_at' => null,
            'end_mileage' => null,
            'total_amount' => 500,
            'amount_paid' => 200,
        ]);

        // Prenotazione futura: 400 euro, di cui 100 incassati.
        $this->createRental($vehicle, [
            'status' => Rental::STATUS_RESERVED,
            'starts_at' => Carbon::parse('2026-09-20 10:00:00'),
            'actual_starts_at' => null,
            'actual_ends_at' => null,
            'start_mileage' => null,
            'end_mileage' => null,
            'total_amount' => 400,
            'amount_paid' => 100,
        ]);

        // Il noleggio annullato viene contato, ma non genera ricavi.
        $this->createRental($vehicle, [
            'status' => Rental::STATUS_CANCELLED,
            'starts_at' => Carbon::parse('2026-09-05 10:00:00'),
            'actual_starts_at' => null,
            'actual_ends_at' => null,
            'start_mileage' => null,
            'end_mileage' => null,
            'total_amount' => 999,
            'amount_paid' => 0,
        ]);

        // Questo noleggio è fuori dal periodo e non deve essere conteggiato.
        $this->createRental($vehicle, [
            'starts_at' => Carbon::parse('2026-08-20 10:00:00'),
            'actual_starts_at' => Carbon::parse('2026-08-20 10:00:00'),
            'actual_ends_at' => Carbon::parse('2026-08-22 10:00:00'),
            'total_amount' => 700,
            'amount_paid' => 700,
        ]);

        $this->createExpense($vehicle, [
            'category' => Expense::CATEGORY_MAINTENANCE,
            'amount' => 100,
        ]);

        $this->createExpense($vehicle, [
            'category' => Expense::CATEGORY_FUEL,
            'description' => 'Carburante',
            'amount' => 50,
        ]);

        // Questa spesa è fuori dal periodo.
        $this->createExpense($vehicle, [
            'expense_date' => '2026-08-10',
            'amount' => 200,
        ]);

        $response = $this->getJson(
            '/api/dashboard'
            .'?date_from=2026-09-01'
            .'&date_to=2026-09-30'
        );

        $response->assertOk();
        $response->assertJsonPath(
            'data.financial.contracted_revenue',
            1200
        );
        $response->assertJsonPath(
            'data.financial.completed_revenue',
            300
        );
        $response->assertJsonPath(
            'data.financial.amount_collected',
            600
        );
        $response->assertJsonPath(
            'data.financial.amount_outstanding',
            600
        );
        $response->assertJsonPath(
            'data.financial.total_expenses',
            150
        );
        $response->assertJsonPath(
            'data.financial.projected_profit',
            1050
        );
        $response->assertJsonPath(
            'data.financial.realized_profit',
            150
        );
        $response->assertJsonPath(
            'data.financial.cash_balance',
            450
        );

        $response->assertJsonPath('data.rentals.total', 4);
        $response->assertJsonPath('data.rentals.reserved', 1);
        $response->assertJsonPath('data.rentals.active', 1);
        $response->assertJsonPath('data.rentals.completed', 1);
        $response->assertJsonPath('data.rentals.cancelled', 1);

        // Le categorie vengono ordinate alfabeticamente.
        $response->assertJsonPath(
            'data.financial.expenses_by_category.0.category',
            Expense::CATEGORY_FUEL
        );
        $response->assertJsonPath(
            'data.financial.expenses_by_category.0.total',
            50
        );
        $response->assertJsonPath(
            'data.financial.expenses_by_category.1.category',
            Expense::CATEGORY_MAINTENANCE
        );
        $response->assertJsonPath(
            'data.financial.expenses_by_category.1.total',
            100
        );
    }

    // Verifica stato della flotta e occupazione delle celle.
    public function test_dashboard_calculates_fleet_and_garage_summary(): void
    {
        $this->authenticateUser();

        $parkedVehicle = Vehicle::factory()->create([
            'parking_units' => 2,
            'is_active' => true,
        ]);

        $rentedVehicle = Vehicle::factory()->create([
            'is_active' => true,
        ]);

        Vehicle::factory()->create([
            'is_active' => false,
        ]);

        // Tre celle attive: due occupate e una libera.
        ParkingSpace::factory()->create([
            'zone' => 'main',
            'row_number' => 1,
            'column_number' => 1,
            'vehicle_id' => $parkedVehicle->id,
            'is_active' => true,
        ]);

        ParkingSpace::factory()->create([
            'zone' => 'main',
            'row_number' => 1,
            'column_number' => 2,
            'vehicle_id' => $parkedVehicle->id,
            'is_active' => true,
        ]);

        ParkingSpace::factory()->create([
            'zone' => 'main',
            'row_number' => 1,
            'column_number' => 3,
            'vehicle_id' => null,
            'is_active' => true,
        ]);

        // Questa quarta cella è disattivata.
        ParkingSpace::factory()->create([
            'zone' => 'main',
            'row_number' => 1,
            'column_number' => 4,
            'vehicle_id' => null,
            'is_active' => false,
        ]);

        $this->createRental($rentedVehicle, [
            'status' => Rental::STATUS_ACTIVE,
            'starts_at' => now()->subDay(),
            'actual_starts_at' => now()->subDay(),
            'actual_ends_at' => null,
            'end_mileage' => null,
        ]);

        $response = $this->getJson('/api/dashboard');

        $response->assertOk();
        $response->assertJsonPath('data.fleet.total', 3);
        $response->assertJsonPath('data.fleet.active', 2);
        $response->assertJsonPath('data.fleet.inactive', 1);
        $response->assertJsonPath('data.fleet.rented_now', 1);
        $response->assertJsonPath('data.fleet.parked_now', 1);
        $response->assertJsonPath('data.fleet.outside_garage', 2);
        $response->assertJsonPath(
            'data.fleet.available_for_rental',
            1
        );

        $response->assertJsonPath('data.garage.total_spaces', 4);
        $response->assertJsonPath('data.garage.active_spaces', 3);
        $response->assertJsonPath('data.garage.inactive_spaces', 1);
        $response->assertJsonPath('data.garage.occupied_spaces', 2);
        $response->assertJsonPath('data.garage.free_spaces', 1);
        $response->assertJsonPath(
            'data.garage.occupancy_rate',
            66.67
        );
    }

    // Verifica giorni noleggiati, giacenza e percentuale di utilizzo.
    public function test_dashboard_calculates_vehicle_utilization(): void
    {
        $this->travelTo(
            Carbon::parse('2026-09-11 12:00:00')
        );

        $this->authenticateUser();

        $firstVehicle = Vehicle::factory()->create([
            'is_active' => true,
        ]);

        $secondVehicle = Vehicle::factory()->create([
            'is_active' => true,
        ]);

        // Primo veicolo utilizzato esattamente per cinque giorni.
        $this->createRental($firstVehicle, [
            'status' => Rental::STATUS_COMPLETED,
            'starts_at' => Carbon::parse('2026-09-01 00:00:00'),
            'actual_starts_at' => Carbon::parse('2026-09-01 00:00:00'),
            'actual_ends_at' => Carbon::parse('2026-09-06 00:00:00'),
        ]);

        // Secondo veicolo utilizzato per gli ultimi tre giorni del periodo.
        $this->createRental($secondVehicle, [
            'status' => Rental::STATUS_ACTIVE,
            'starts_at' => Carbon::parse('2026-09-08 00:00:00'),
            'actual_starts_at' => Carbon::parse('2026-09-08 00:00:00'),
            'actual_ends_at' => null,
            'end_mileage' => null,
        ]);

        $response = $this->getJson(
            '/api/dashboard'
            .'?date_from=2026-09-01'
            .'&date_to=2026-09-10'
        );

        $response->assertOk();
        $response->assertJsonPath(
            'data.utilization.vehicles_considered',
            2
        );
        $response->assertJsonPath(
            'data.utilization.capacity_days',
            20
        );
        $response->assertJsonPath(
            'data.utilization.rented_days',
            8
        );
        $response->assertJsonPath(
            'data.utilization.idle_days',
            12
        );
        $response->assertJsonPath(
            'data.utilization.utilization_rate',
            40
        );
    }

    // Il filtro vehicle_id deve isolare dati economici e flotta.
    public function test_dashboard_can_be_filtered_by_vehicle(): void
    {
        $this->authenticateUser();

        $firstVehicle = Vehicle::factory()->create();
        $secondVehicle = Vehicle::factory()->create();

        $this->createRental($firstVehicle, [
            'total_amount' => 200,
            'amount_paid' => 200,
        ]);

        $this->createRental($secondVehicle, [
            'total_amount' => 500,
            'amount_paid' => 500,
        ]);

        $this->createExpense($firstVehicle, [
            'amount' => 50,
        ]);

        $this->createExpense($secondVehicle, [
            'amount' => 80,
        ]);

        $response = $this->getJson(
            '/api/dashboard'
            .'?date_from=2026-09-01'
            .'&date_to=2026-09-30'
            ."&vehicle_id={$firstVehicle->id}"
        );

        $response->assertOk();
        $response->assertJsonPath(
            'data.filters.vehicle_id',
            $firstVehicle->id
        );
        $response->assertJsonPath('data.fleet.total', 1);
        $response->assertJsonPath(
            'data.financial.contracted_revenue',
            200
        );
        $response->assertJsonPath(
            'data.financial.total_expenses',
            50
        );
        $response->assertJsonPath(
            'data.financial.realized_profit',
            150
        );
    }

    // Verifica scadenze superate e imminenti.
    public function test_dashboard_lists_expense_deadlines(): void
    {
        $this->travelTo(
            Carbon::parse('2026-09-11 12:00:00')
        );

        $this->authenticateUser();

        $vehicle = Vehicle::factory()->create();

        $this->createExpense($vehicle, [
            'category' => Expense::CATEGORY_INSURANCE,
            'description' => 'Assicurazione scaduta',
            'expires_on' => '2026-09-10',
        ]);

        $this->createExpense($vehicle, [
            'category' => Expense::CATEGORY_ROAD_TAX,
            'description' => 'Bollo in scadenza oggi',
            'expires_on' => '2026-09-11',
        ]);

        $this->createExpense($vehicle, [
            'category' => Expense::CATEGORY_INSPECTION,
            'description' => 'Revisione imminente',
            'expires_on' => '2026-09-30',
        ]);

        // Questa scadenza è oltre i prossimi 30 giorni.
        $this->createExpense($vehicle, [
            'description' => 'Scadenza lontana',
            'expires_on' => '2026-10-20',
        ]);

        $response = $this->getJson('/api/dashboard');

        $response->assertOk();
        $response->assertJsonPath(
            'data.deadlines.overdue_count',
            1
        );
        $response->assertJsonPath(
            'data.deadlines.upcoming_30_days_count',
            2
        );
        $response->assertJsonCount(3, 'data.deadlines.items');
        $response->assertJsonPath(
            'data.deadlines.items.0.status',
            'overdue'
        );
        $response->assertJsonPath(
            'data.deadlines.items.0.expires_on',
            '2026-09-10'
        );
        $response->assertJsonPath(
            'data.deadlines.items.1.status',
            'upcoming'
        );
    }

    // Verifica date, durata massima e identificativo del veicolo.
    public function test_dashboard_rejects_invalid_filters(): void
    {
        $this->authenticateUser();

        $missingDate = $this->getJson(
            '/api/dashboard?date_from=2026-09-01'
        );

        $missingDate->assertUnprocessable();
        $missingDate->assertJsonValidationErrors(['date_to']);

        $invertedPeriod = $this->getJson(
            '/api/dashboard'
            .'?date_from=2026-09-30'
            .'&date_to=2026-09-01'
        );

        $invertedPeriod->assertUnprocessable();
        $invertedPeriod->assertJsonValidationErrors(['date_to']);

        $excessivePeriod = $this->getJson(
            '/api/dashboard'
            .'?date_from=2025-01-01'
            .'&date_to=2026-09-11'
        );

        $excessivePeriod->assertUnprocessable();
        $excessivePeriod->assertJsonValidationErrors(['date_to']);

        $invalidVehicle = $this->getJson(
            '/api/dashboard?vehicle_id=999999'
        );

        $invalidVehicle->assertUnprocessable();
        $invalidVehicle->assertJsonValidationErrors(['vehicle_id']);
    }

    // Elenca i mezzi e i clienti dei noleggi attualmente attivi
    public function test_dashboard_lists_currently_rented_vehicles(): void
    {
        $this->authenticateUser();

        $customer = Customer::factory()->create([
            'first_name' => 'Mario',
            'last_name' => 'Rossi',
        ]);

        $activeVehicle = Vehicle::factory()->create([
            'license_plate' => 'ACTIVE-001',
            'brand' => 'Fiat',
            'model' => 'Panda',
        ]);

        $completedVehicle = Vehicle::factory()->create([
            'license_plate' => 'ENDED-001',
        ]);

        // Questo noleggio deve comparire nella dashboard
        $this->createRental($activeVehicle, [
            'customer_id' => $customer->id,
            'status' => Rental::STATUS_ACTIVE,
            'starts_at' => now()->subDays(2),
            'actual_starts_at' => now()->subDays(2),
            'expected_ends_at' => now()->addDays(2),
            'actual_ends_at' => null,
            'end_mileage' => null,
            'total_amount' => 200,
            'amount_paid' => 100,
        ]);

        // Un noleggio completato non deve comparire
        $this->createRental($completedVehicle, [
            'status' => Rental::STATUS_COMPLETED,
            'starts_at' => now()->subDays(5),
            'actual_starts_at' => now()->subDays(5),
            'expected_ends_at' => now()->subDays(3),
            'actual_ends_at' => now()->subDays(3),
        ]);

        $response = $this->getJson('/api/dashboard');

        $response->assertOk();
        $response->assertJsonCount(
            1,
            'data.active_rentals'
        );

        $response->assertJsonPath(
            'data.active_rentals.0.vehicle_id',
            $activeVehicle->id
        );

        $response->assertJsonPath(
            'data.active_rentals.0.license_plate',
            'ACTIVE-001'
        );

        $response->assertJsonPath(
            'data.active_rentals.0.brand',
            'Fiat'
        );

        $response->assertJsonPath(
            'data.active_rentals.0.model',
            'Panda'
        );

        $response->assertJsonPath(
            'data.active_rentals.0.customer_name',
            'Mario Rossi'
        );

        $response->assertJsonPath(
            'data.active_rentals.0.total_amount',
            200
        );

        $response->assertJsonPath(
            'data.active_rentals.0.amount_paid',
            100
        );
    }
}
