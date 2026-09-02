<?php

namespace App\Models;

use Carbon\CarbonInterface;
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

    //Stati che rendono il veicolo non disponibile nel periodo del noleggio
    public const BLOCKING_STATUSES = [
        self::STATUS_RESERVED,
        self::STATUS_ACTIVE,
    ];

    //Campi che possono essere assegnati in modo controllato
    protected $fillable = [
        'vehicle_id',
        'customer_id',
        'status',
        'starts_at',
        'actual_starts_at',
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
            'actual_starts_at' => 'datetime',
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

    //Calcola il numero di giornate da addebitare
    public static function calculateChargeableDays(
        CarbonInterface $startsAt,
        CarbonInterface $endsAt
    ): int {
        //Calcola la distanza tra inizio e fine espressa in minuti
        $minutes = $startsAt->diffInMinutes($endsAt);

        //Una giornata contiene 1440 minuti
        //ceil arrotonda verso l'alto ogni giornata iniziata
        //max garantisce che venga addebitata almeno una giornata
        return max(1, (int) ceil($minutes / 1440));
    }

    //Calcola l'importo totale del noleggio
    public static function calculateTotalAmount(
        CarbonInterface $startsAt,
        CarbonInterface $endsAt,
        int|float|string $dailyRate
    ): string {
        //Calcola il numero di giornate da addebitare
        $days = self::calculateChargeableDays(
            $startsAt,
            $endsAt
        );

        //Converte la tariffa in centesimi
        //Questo riduce i problemi di arrotondamento dei numeri decimali
        $dailyRateInCents = (int) round(
            (float) $dailyRate * 100
        );

        //Moltiplica la tariffa giornaliera per il numero di giornate
        $totalInCents = $dailyRateInCents * $days;

        //Riconverte l'importo in euro mantenendo due cifre decimali
        return number_format(
            $totalInCents / 100,
            2,
            '.',
            ''
        );
    }

    //Controlla se un veicolo possiede già un noleggio nello stesso periodo
    public static function hasOverlappingRental(
        int $vehicleId,
        CarbonInterface $startsAt,
        CarbonInterface $endsAt,
        ?int $excludedRentalId = null
    ): bool {
        return self::query()
            //Controlla soltanto i noleggi dello stesso veicolo
            ->where('vehicle_id', $vehicleId)

            //I noleggi completati o annullati non bloccano il periodo
            ->whereIn('status', self::BLOCKING_STATUSES)

            //Due intervalli si sovrappongono quando il primo inizia
            //prima della fine del secondo e termina dopo il suo inizio
            ->where('starts_at', '<', $endsAt)
            ->where('expected_ends_at', '>', $startsAt)

            //Durante una modifica esclude il noleggio che stiamo aggiornando
            ->when(
                $excludedRentalId !== null,
                fn ($query) => $query->whereKeyNot($excludedRentalId)
            )

            //Restituisce true se esiste almeno una sovrapposizione
            ->exists();
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
