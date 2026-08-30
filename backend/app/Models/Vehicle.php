<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Vehicle extends Model
{
    //Permette di creare veicoli fittizi tramite VehicleFactory
    use HasFactory;

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

    //Relazione uno a molti (1:N):
    //un veicolo può avere molti noleggi, mentre ogni noleggio appartiene a un solo veicolo
    public function rentals(): HasMany
    {
        return $this->hasMany(Rental::class);
    }

    //Relazione uno a molti (1:N):
    //un veicolo può avere molte spese, mentre ogni spesa appartiene a un solo veicolo
    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class);
    }
}
