<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Vehicle extends Model
{
    //Campi compilabili tramite assegnazione in blocco
    protected $fillable = [
        'license_plate',
        'brand',
        'model',
        'type',
        'year',
        'mileage',
        'is_active',
        'daily_rate',
    ];

    //Tipi dei valori restituiti dal modello
    protected function casts(): array
    {
        return [
            'year' => 'integer',
            'mileage' => 'integer',
            'is_active' => 'boolean',
            'daily_rate' => 'decimal:2',
        ];
    }
}
