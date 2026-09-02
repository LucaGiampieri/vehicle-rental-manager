<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\Rental;
use App\Models\Vehicle;
use Illuminate\Database\Eloquent\Factories\Factory;

class RentalFactory extends Factory
{
    //Definisce i valori predefiniti di un noleggio fittizio
    public function definition(): array
    {
        //Genera una prenotazione che inizierà in futuro
        $startsAt = fake()->dateTimeBetween(
            '+1 day',
            '+3 months'
        );

        //Genera una durata compresa tra 1 e 14 giorni
        $rentalDays = fake()->numberBetween(1, 14);

        //Calcola la data prevista di riconsegna
        //clone evita di modificare anche la data iniziale
        $expectedEndsAt = (clone $startsAt)
            ->modify("+{$rentalDays} days");

        //Genera la tariffa giornaliera concordata
        $dailyRate = fake()->randomFloat(2, 35, 180);

        //Calcola il totale in base alla durata
        $totalAmount = round(
            $dailyRate * $rentalDays,
            2
        );

        return [
            //Crea automaticamente un veicolo se non ne viene fornito uno
            'vehicle_id' => Vehicle::factory(),

            //Crea automaticamente un cliente se non ne viene fornito uno
            'customer_id' => Customer::factory(),

            //Il noleggio nasce come prenotazione
            'status' => Rental::STATUS_RESERVED,

            //Salva il periodo previsto del noleggio
            'starts_at' => $startsAt,
            'actual_starts_at' => null,
            'expected_ends_at' => $expectedEndsAt,
            'actual_ends_at' => null,

            //Salva tariffa, totale concordato e importo incassato
            'daily_rate' => $dailyRate,
            'total_amount' => $totalAmount,
            'amount_paid' => 0,

            //I chilometraggi verranno inseriti alla consegna e al rientro
            'start_mileage' => null,
            'end_mileage' => null,

            //La prenotazione non ha annotazioni iniziali
            'notes' => null,
        ];
    }
}
