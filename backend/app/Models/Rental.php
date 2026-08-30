<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Rental extends Model
{
    //Permette di creare noleggi fittizi tramite RentalFactory
    /** @use HasFactory<\Database\Factories\RentalFactory> */
    use HasFactory;

    //Stati utilizzabili durante il ciclo di vita del noleggio
    public const STATUS_RESERVED = 'reserved';
    public const STATUS_ACTIVE = 'active';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_CANCELLED = 'cancelled';

    //Elenco completo degli stati ammessi
    //Lo useremo nella validazione delle API
    public const STATUSES = [
        self::STATUS_RESERVED,
        self::STATUS_ACTIVE,
        self::STATUS_COMPLETED,
        self::STATUS_CANCELLED,
    ];

    //Campi che possono essere assegnati in modo controllato
    protected $fillable = [
        'vehicle_id',
        'customer_id',
        'status',
        'starts_at',
        'expected_ends_at',
        'actual_ends_at',
        'daily_rate',
        'total_amount',
        'amount_paid',
        'start_mileage',
        'end_mileage',
        'notes',
    ];

    //Converte automaticamente i valori del database nei tipi PHP corretti.
    protected function casts(): array
    {
        return [
            //Converte date e orari in oggetti Carbon
            'starts_at' => 'datetime',
            'expected_ends_at' => 'datetime',
            'actual_ends_at' => 'datetime',

            //Mantiene sempre due cifre decimali per gli importi
            'daily_rate' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'amount_paid' => 'decimal:2',

            //Converte i chilometraggi in numeri interi
            'start_mileage' => 'integer',
            'end_mileage' => 'integer',
        ];
    }

    //Relazione molti a uno (N:1):
    //ogni noleggio appartiene a un veicolo, mentre un veicolo può avere molti noleggi
    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    //Relazione molti a uno (N:1):
    //ogni noleggio appartiene a un cliente, mentre un cliente può avere molti noleggi
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
