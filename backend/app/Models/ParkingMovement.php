<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ParkingMovement extends Model
{
    // Tipologie di movimento eseguibili manualmente.
    public const TYPE_PARKED = 'parked';

    public const TYPE_MOVED = 'moved';

    public const TYPE_UNPARKED = 'unparked';

    // Tipologie utilizzate durante il ciclo di un noleggio.
    public const TYPE_RENTAL_DEPARTURE = 'rental_departure';

    public const TYPE_RENTAL_RETURN = 'rental_return';

    // Elenco completo delle tipologie ammesse.
    public const TYPES = [
        self::TYPE_PARKED,
        self::TYPE_MOVED,
        self::TYPE_UNPARKED,
        self::TYPE_RENTAL_DEPARTURE,
        self::TYPE_RENTAL_RETURN,
    ];

    // Campi assegnabili in modo controllato.
    protected $fillable = [
        'vehicle_id',
        'vehicle_license_plate',
        'performed_by_user_id',
        'rental_id',
        'type',
        'from_parking_space_id',
        'from_zone',
        'from_row_number',
        'from_column_number',
        'to_parking_space_id',
        'to_zone',
        'to_row_number',
        'to_column_number',
        'parking_units',
        'notes',
        'occurred_at',
    ];

    // Converte i valori del database nei tipi PHP corretti.
    protected function casts(): array
    {
        return [
            'from_row_number' => 'integer',
            'from_column_number' => 'integer',
            'to_row_number' => 'integer',
            'to_column_number' => 'integer',
            'parking_units' => 'integer',

            // Converte data e ora in un oggetto Carbon.
            'occurred_at' => 'datetime',
        ];
    }

    // Veicolo interessato dal movimento.
    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    // Utente che ha effettuato l'operazione.
    public function performedBy(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'performed_by_user_id'
        );
    }

    // Noleggio eventualmente collegato al movimento.
    public function rental(): BelongsTo
    {
        return $this->belongsTo(Rental::class);
    }

    // Cella dalla quale il veicolo è partito.
    public function fromParkingSpace(): BelongsTo
    {
        return $this->belongsTo(
            ParkingSpace::class,
            'from_parking_space_id'
        );
    }

    // Cella verso la quale il veicolo è stato spostato.
    public function toParkingSpace(): BelongsTo
    {
        return $this->belongsTo(
            ParkingSpace::class,
            'to_parking_space_id'
        );
    }
}
