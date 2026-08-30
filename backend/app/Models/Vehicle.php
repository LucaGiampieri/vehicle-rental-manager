<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Vehicle extends Model
{
    //Permette di creare veicoli fittizi tramite VehicleFactory
    use HasFactory;

    //Tipi di veicolo disponibili
    public const TYPE_CAR = 'car';
    public const TYPE_MOTORCYCLE = 'motorcycle';
    public const TYPE_VAN = 'van';
    public const TYPE_CAMPER = 'camper';
    public const TYPE_TRUCK = 'truck';
    public const TYPE_BUS = 'bus';
    public const TYPE_OTHER = 'other';

    //Elenco completo dei tipi ammessi
    //Lo useremo nella validazione delle API
    public const TYPES = [
        self::TYPE_CAR,
        self::TYPE_MOTORCYCLE,
        self::TYPE_VAN,
        self::TYPE_CAMPER,
        self::TYPE_TRUCK,
        self::TYPE_BUS,
        self::TYPE_OTHER,
    ];

    //Numero di celle richieste in base alla dimensione del veicolo
    public const PARKING_UNITS_SMALL = 1;
    public const PARKING_UNITS_STANDARD = 2;
    public const PARKING_UNITS_LARGE = 4;
    public const PARKING_UNITS_EXTRA_LARGE = 8;

    //Elenco dei numeri di celle ammessi
    //Lo useremo nella validazione delle API
    public const ALLOWED_PARKING_UNITS = [
        self::PARKING_UNITS_SMALL,
        self::PARKING_UNITS_STANDARD,
        self::PARKING_UNITS_LARGE,
        self::PARKING_UNITS_EXTRA_LARGE,
    ];

    //Campi compilabili tramite assegnazione in blocco
    protected $fillable = [
        'license_plate',
        'brand',
        'model',
        'type',
        'parking_units',
        'year',
        'mileage',
        'is_active',
        'daily_rate',
    ];

    //Tipi dei valori restituiti dal Model
    protected function casts(): array
    {
        return [
            'parking_units' => 'integer',
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

    //Relazione uno a molti (1:N):
    //un veicolo può occupare più celle, mentre ogni cella contiene al massimo un veicolo
    public function parkingSpaces(): HasMany
    {
        return $this->hasMany(ParkingSpace::class);
    }
}
