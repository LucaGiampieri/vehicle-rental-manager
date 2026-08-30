<?php

namespace Database\Factories;

use App\Models\Vehicle;
use Illuminate\Database\Eloquent\Factories\Factory;

class VehicleFactory extends Factory
{
    //Definisce i valori predefiniti di un veicolo fittizio
    public function definition(): array
    {
        //Sceglie un veicolo con un numero coerente di celle richieste
        $vehicle = fake()->randomElement([
            [
                'brand' => 'Fiat',
                'model' => 'Panda',
                'type' => 'car',
                'parking_units' => Vehicle::PARKING_UNITS_STANDARD,
            ],
            [
                'brand' => 'Toyota',
                'model' => 'Yaris',
                'type' => 'car',
                'parking_units' => Vehicle::PARKING_UNITS_STANDARD,
            ],
            [
                'brand' => 'Honda',
                'model' => 'SH 125',
                'type' => 'motorcycle',
                'parking_units' => Vehicle::PARKING_UNITS_SMALL,
            ],
            [
                'brand' => 'Ford',
                'model' => 'Transit',
                'type' => 'van',
                'parking_units' => Vehicle::PARKING_UNITS_LARGE,
            ],
            [
                'brand' => 'Fiat',
                'model' => 'Ducato',
                'type' => 'camper',
                'parking_units' => Vehicle::PARKING_UNITS_LARGE,
            ],
            [
                'brand' => 'Volkswagen',
                'model' => 'California',
                'type' => 'camper',
                'parking_units' => Vehicle::PARKING_UNITS_LARGE,
            ],
            [
                'brand' => 'Iveco',
                'model' => 'S-Way',
                'type' => 'truck',
                'parking_units' => Vehicle::PARKING_UNITS_EXTRA_LARGE,
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
            'parking_units' => $vehicle['parking_units'],

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
