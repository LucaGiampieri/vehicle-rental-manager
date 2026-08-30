<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class ParkingSpaceFactory extends Factory
{
    //Definisce i valori predefiniti di una cella fittizia
    public function definition(): array
    {
        //Genera un numero univoco da trasformare in coordinate
        $position = fake()
            ->unique()
            ->numberBetween(1, 10000);

        //Trasforma la posizione in una griglia con 50 colonne
        $rowNumber = intdiv($position - 1, 50) + 1;
        $columnNumber = (($position - 1) % 50) + 1;

        return [
            //Genera un'etichetta leggibile dalle coordinate
            'label' => "M-{$rowNumber}-{$columnNumber}",

            //Inserisce la cella nella zona principale
            'zone' => 'main',

            //Salva la posizione nella griglia
            'row_number' => $rowNumber,
            'column_number' => $columnNumber,

            //La cella viene creata vuota e utilizzabile
            'vehicle_id' => null,
            'is_active' => true,

            //La cella non ha annotazioni iniziali
            'notes' => null,
        ];
    }
}
