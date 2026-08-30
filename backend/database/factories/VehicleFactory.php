<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class VehicleFactory extends Factory
{
    //Definisce i valori predefiniti di un veicolo fittizio
    public function definition(): array
    {
        //Sceglie una combinazione coerente di marca, modello e tipo
        $vehicle = fake()->randomElement([
            [
                'brand' => 'Fiat',
                'model' => 'Panda',
                'type' => 'car',
            ],
            [
                'brand' => 'Toyota',
                'model' => 'Yaris',
                'type' => 'car',
            ],
            [
                'brand' => 'Ford',
                'model' => 'Transit',
                'type' => 'van',
            ],
            [
                'brand' => 'Fiat',
                'model' => 'Ducato',
                'type' => 'camper',
            ],
            [
                'brand' => 'Volkswagen',
                'model' => 'California',
                'type' => 'camper',
            ],
        ]);

        return [
            //Genera una targa fittizia nel formato AA123AA
            'license_plate' => strtoupper(
                fake()
                    ->unique()
                    ->bothify('??###??')
            ),

            //Utilizza i dati del veicolo scelto
            'brand' => $vehicle['brand'],
            'model' => $vehicle['model'],
            'type' => $vehicle['type'],

            //Genera anno, chilometraggio e tariffa
            'year' => fake()->numberBetween(
                2015,
                (int) now()->format('Y')
            ),
            'mileage' => fake()->numberBetween(0, 180000),
            'daily_rate' => fake()->randomFloat(2, 35, 180),

            //Il mezzo viene creato come appartenente alla flotta attiva
            'is_active' => true,
        ];
    }
}
