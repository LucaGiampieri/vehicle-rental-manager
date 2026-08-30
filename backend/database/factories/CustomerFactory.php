<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\Customer>
 */
class CustomerFactory extends Factory
{
    /**
     * Definisce i valori predefiniti di un cliente fittizio.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            //Genera nome e cognome usando la lingua italiana configurata
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),

            //Genera un cliente con età compresa tra 21 e 75 anni
            'birth_date' => fake()
                ->dateTimeBetween('-75 years', '-21 years')
                ->format('Y-m-d'),

            //Genera contatti fittizi e non duplicati
            'email' => fake()
                ->unique()
                ->safeEmail(),
            'phone' => fake()->phoneNumber(),

            //Genera un codice fiscale fittizio di 16 caratteri
            'tax_code' => strtoupper(
                fake()
                    ->unique()
                    ->bothify('??????##?##?###?')
            ),

            //Genera un numero di patente fittizio e non duplicato
            'driving_license_number' => strtoupper(
                fake()
                    ->unique()
                    ->bothify('??#######?')
            ),

            //Genera una patente non ancora scaduta
            'driving_license_expiry_date' => fake()
                ->dateTimeBetween('+1 year', '+10 years')
                ->format('Y-m-d'),

            //Genera un indirizzo italiano
            'address' => fake()->address(),

            //I clienti dimostrativi non hanno annotazioni iniziali
            'notes' => null,

            //Il cliente viene creato come attivo
            'is_active' => true,
        ];
    }
}
