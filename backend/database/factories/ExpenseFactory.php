<?php

namespace Database\Factories;

use App\Models\Expense;
use App\Models\Vehicle;
use Illuminate\Database\Eloquent\Factories\Factory;

class ExpenseFactory extends Factory
{
    //Definisce i valori predefiniti di una spesa fittizia
    public function definition(): array
    {
        //Sceglie una delle categorie definite nel Model Expense
        $category = fake()->randomElement(
            Expense::CATEGORIES
        );

        //Genera una data compresa negli ultimi due anni
        $expenseDate = fake()->dateTimeBetween(
            '-2 years',
            'now'
        );

        //Genera una descrizione coerente con la categoria
        $description = match ($category) {
            Expense::CATEGORY_PURCHASE => 'Acquisto del veicolo',
            Expense::CATEGORY_MAINTENANCE => 'Manutenzione ordinaria',
            Expense::CATEGORY_REPAIR => 'Riparazione del veicolo',
            Expense::CATEGORY_ROAD_TAX => 'Pagamento del bollo',
            Expense::CATEGORY_INSURANCE => 'Pagamento assicurazione',
            Expense::CATEGORY_FUEL => 'Rifornimento carburante',
            Expense::CATEGORY_CLEANING => 'Pulizia del veicolo',
            Expense::CATEGORY_INSPECTION => 'Revisione periodica',
            Expense::CATEGORY_OTHER => 'Altra spesa',
        };

        //Genera un importo realistico in base alla categoria
        $amount = match ($category) {
            Expense::CATEGORY_PURCHASE => fake()
                ->randomFloat(2, 5000, 70000),
            Expense::CATEGORY_MAINTENANCE => fake()
                ->randomFloat(2, 50, 1200),
            Expense::CATEGORY_REPAIR => fake()
                ->randomFloat(2, 100, 5000),
            Expense::CATEGORY_ROAD_TAX => fake()
                ->randomFloat(2, 100, 800),
            Expense::CATEGORY_INSURANCE => fake()
                ->randomFloat(2, 300, 2000),
            Expense::CATEGORY_FUEL => fake()
                ->randomFloat(2, 20, 200),
            Expense::CATEGORY_CLEANING => fake()
                ->randomFloat(2, 15, 150),
            Expense::CATEGORY_INSPECTION => fake()
                ->randomFloat(2, 50, 150),
            Expense::CATEGORY_OTHER => fake()
                ->randomFloat(2, 10, 1000),
        };

        //Calcola la scadenza soltanto per i costi che la prevedono
        $expiresOn = match ($category) {
            Expense::CATEGORY_ROAD_TAX,
            Expense::CATEGORY_INSURANCE => (clone $expenseDate)
                ->modify('+1 year'),
            Expense::CATEGORY_INSPECTION => (clone $expenseDate)
                ->modify('+2 years'),
            default => null,
        };

        return [
            //Crea automaticamente un veicolo se non ne viene fornito uno
            'vehicle_id' => Vehicle::factory(),

            //Salva categoria, descrizione e importo
            'category' => $category,
            'description' => $description,
            'amount' => $amount,

            //Salva data della spesa ed eventuale scadenza
            'expense_date' => $expenseDate,
            'expires_on' => $expiresOn,

            //Genera il chilometraggio registrato al momento della spesa
            'mileage' => fake()->numberBetween(0, 180000),

            //Genera un fornitore fittizio
            'supplier' => fake()->company(),

            //La spesa non possiede annotazioni iniziali
            'notes' => null,
        ];
    }
}
