<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Vehicle extends Model
{
    use HasFactory;

    // Tipi di veicolo disponibili
    public const TYPE_CAR = 'car';

    public const TYPE_MOTORCYCLE = 'motorcycle';

    public const TYPE_VAN = 'van';

    public const TYPE_CAMPER = 'camper';

    public const TYPE_TRUCK = 'truck';

    public const TYPE_BUS = 'bus';

    public const TYPE_OTHER = 'other';

    // Elenco completo dei tipi ammessi dalla validazione
    public const TYPES = [
        self::TYPE_CAR,
        self::TYPE_MOTORCYCLE,
        self::TYPE_VAN,
        self::TYPE_CAMPER,
        self::TYPE_TRUCK,
        self::TYPE_BUS,
        self::TYPE_OTHER,
    ];

    // Stati operativi mostrati nel frontend
    public const OPERATIONAL_STATUS_AVAILABLE = 'available';

    public const OPERATIONAL_STATUS_RESERVED = 'reserved';

    public const OPERATIONAL_STATUS_RENTED = 'rented';

    public const OPERATIONAL_STATUS_INACTIVE = 'inactive';

    // Numero di celle richieste in base alla dimensione del veicolo
    public const PARKING_UNITS_SMALL = 1;

    public const PARKING_UNITS_STANDARD = 2;

    public const PARKING_UNITS_LARGE = 4;

    public const PARKING_UNITS_EXTRA_LARGE = 8;

    // Elenco dei numeri di celle ammessi
    public const ALLOWED_PARKING_UNITS = [
        self::PARKING_UNITS_SMALL,
        self::PARKING_UNITS_STANDARD,
        self::PARKING_UNITS_LARGE,
        self::PARKING_UNITS_EXTRA_LARGE,
    ];

    // Campi compilabili tramite assegnazione in blocco
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

    // Converte automaticamente i valori recuperati dal database
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

    // Un veicolo può avere molti noleggi
    public function rentals(): HasMany
    {
        return $this->hasMany(Rental::class);
    }

    /*
     * Recupera l'eventuale noleggio attualmente attivo.
     * In base alle regole del gestionale può esisterne soltanto uno.
     */
    public function activeRental(): HasOne
    {
        return $this->hasOne(Rental::class)
            ->where('status', Rental::STATUS_ACTIVE);
    }

    // Recupera la prenotazione valida più vicina
    public function nextReservation(): HasOne
    {
        return $this->hasOne(Rental::class)
            ->where('status', Rental::STATUS_RESERVED)
            ->where('expected_ends_at', '>', now())
            ->orderBy('starts_at');
    }

    // Un veicolo può possedere molte spese
    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class);
    }

    // Un veicolo può occupare più celle dell'autorimessa
    public function parkingSpaces(): HasMany
    {
        return $this->hasMany(ParkingSpace::class);
    }

    // Restituisce tutte le immagini nell'ordine della galleria
    public function images(): HasMany
    {
        return $this->hasMany(VehicleImage::class)
            ->orderByDesc('is_primary')
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    // Restituisce la fotografia principale del veicolo
    public function primaryImage(): HasOne
    {
        return $this->hasOne(VehicleImage::class)
            ->where('is_primary', true);
    }
}
