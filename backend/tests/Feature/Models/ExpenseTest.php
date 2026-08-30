<?php

namespace Tests\Feature\Models;

use App\Models\Expense;
use App\Models\Vehicle;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ExpenseTest extends TestCase
{
    //Ricrea il database di test prima di ogni metodo
    use RefreshDatabase;

    public function test_factory_creates_an_expense_with_a_vehicle(): void
    {
        //Crea automaticamente una spesa e il relativo veicolo
        $expense = Expense::factory()
            ->create();

        //Controlla che i record siano stati salvati
        $this->assertDatabaseCount('expenses', 1);
        $this->assertDatabaseCount('vehicles', 1);

        //Controlla che la categoria generata sia tra quelle ammesse
        $this->assertContains(
            $expense->category,
            Expense::CATEGORIES
        );

        //Controlla che l'importo abbia sempre due cifre decimali
        $this->assertMatchesRegularExpression(
            '/^\d+\.\d{2}$/',
            $expense->amount
        );

        //Controlla le conversioni definite nel Model
        $this->assertInstanceOf(
            Carbon::class,
            $expense->expense_date
        );
        $this->assertIsInt($expense->mileage);

        //Relazione molti a uno (N:1):
        //la spesa restituisce il veicolo a cui appartiene
        $this->assertInstanceOf(
            Vehicle::class,
            $expense->vehicle
        );
    }

    public function test_vehicle_has_many_expenses_and_calculates_their_total(): void
    {
        //Crea un veicolo da utilizzare per entrambe le spese
        $vehicle = Vehicle::factory()
            ->create();

        //Crea due spese collegate allo stesso veicolo
        $firstExpense = Expense::factory()
            ->create([
                'vehicle_id' => $vehicle->id,
                'amount' => 100.25,
            ]);

        $secondExpense = Expense::factory()
            ->create([
                'vehicle_id' => $vehicle->id,
                'amount' => 250.25,
            ]);

        //Relazione molti a uno (N:1):
        //ogni spesa restituisce lo stesso veicolo
        $this->assertTrue(
            $firstExpense->vehicle->is($vehicle)
        );
        $this->assertTrue(
            $secondExpense->vehicle->is($vehicle)
        );

        //Relazione uno a molti (1:N):
        //il veicolo restituisce entrambe le spese
        $this->assertSame(
            2,
            $vehicle->expenses()
                ->count()
        );

        //Calcola la somma delle spese direttamente dal database
        $totalExpenses = $vehicle->expenses()
            ->sum('amount');

        $this->assertEqualsWithDelta(
            350.50,
            (float) $totalExpenses,
            0.001
        );

        //Le Factory non devono creare veicoli aggiuntivi
        $this->assertDatabaseCount('vehicles', 1);
        $this->assertDatabaseCount('expenses', 2);
    }

    public function test_expense_contains_all_allowed_categories(): void
    {
        //Controlla l'elenco centrale delle categorie ammesse
        $this->assertSame([
            Expense::CATEGORY_PURCHASE,
            Expense::CATEGORY_MAINTENANCE,
            Expense::CATEGORY_REPAIR,
            Expense::CATEGORY_ROAD_TAX,
            Expense::CATEGORY_INSURANCE,
            Expense::CATEGORY_FUEL,
            Expense::CATEGORY_CLEANING,
            Expense::CATEGORY_INSPECTION,
            Expense::CATEGORY_OTHER,
        ], Expense::CATEGORIES);

        //Controlla alcuni dei valori salvati nel database
        $this->assertSame(
            'purchase',
            Expense::CATEGORY_PURCHASE
        );
        $this->assertSame(
            'road_tax',
            Expense::CATEGORY_ROAD_TAX
        );
        $this->assertSame(
            'insurance',
            Expense::CATEGORY_INSURANCE
        );
        $this->assertSame(
            'fuel',
            Expense::CATEGORY_FUEL
        );
    }

    public function test_expiration_dates_are_converted_and_coherent(): void
    {
        //Prepara una data di partenza conosciuta
        $expenseDate = Carbon::create(2026, 1, 15)
            ->startOfDay();

        //Crea un'assicurazione con scadenza dopo un anno
        $insurance = Expense::factory()
            ->create([
                'category' => Expense::CATEGORY_INSURANCE,
                'expense_date' => $expenseDate,
                'expires_on' => $expenseDate
                    ->copy()
                    ->addYear(),
            ]);

        //Crea una revisione con scadenza dopo due anni
        $inspection = Expense::factory()
            ->create([
                'category' => Expense::CATEGORY_INSPECTION,
                'expense_date' => $expenseDate,
                'expires_on' => $expenseDate
                    ->copy()
                    ->addYears(2),
            ]);

        //Controlla che le scadenze siano oggetti Carbon
        $this->assertInstanceOf(
            Carbon::class,
            $insurance->expires_on
        );
        $this->assertInstanceOf(
            Carbon::class,
            $inspection->expires_on
        );

        //Controlla gli intervalli delle due scadenze
        $this->assertTrue(
            $insurance->expires_on->equalTo(
                $insurance->expense_date
                    ->copy()
                    ->addYear()
            )
        );

        $this->assertTrue(
            $inspection->expires_on->equalTo(
                $inspection->expense_date
                    ->copy()
                    ->addYears(2)
            )
        );
    }

    public function test_vehicle_with_expenses_cannot_be_deleted(): void
    {
        //Crea una spesa con il relativo veicolo
        $expense = Expense::factory()
            ->create();

        $vehicle = $expense->vehicle;

        //La chiave esterna deve impedire di eliminare il veicolo
        $this->expectException(QueryException::class);

        $vehicle->delete();
    }
}
