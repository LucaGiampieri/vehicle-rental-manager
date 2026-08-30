<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ParkingSpace extends Model
{
    //Permette di creare celle fittizie tramite ParkingSpaceFactory
    use HasFactory;

    //Campi che possono essere assegnati in modo controllato
    protected $fillable = [
        'label',
        'zone',
        'row_number',
        'column_number',
        'vehicle_id',
        'is_active',
        'notes',
    ];

    //Converte automaticamente i valori del database nei tipi PHP corretti
    protected function casts(): array
    {
        return [
            //Converte le coordinate in numeri interi
            'row_number' => 'integer',
            'column_number' => 'integer',

            //Converte 0 e 1 in false e true
            'is_active' => 'boolean',
        ];
    }

    //Relazione molti a uno (N:1):
    //molte celle possono appartenere allo stesso veicolo, mentre ogni cella contiene al massimo un veicolo
    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }
}
