<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Customer extends Model
{
    //Permette di creare clienti fittizi attraverso CustomerFactory
    /** @use HasFactory<\Database\Factories\CustomerFactory> */
    use HasFactory;

    //Elenca i campi che possono essere assegnati in modo controllato
    protected $fillable = [
        'first_name',
        'last_name',
        'birth_date',
        'email',
        'phone',
        'tax_code',
        'driving_license_number',
        'driving_license_expiry_date',
        'address',
        'notes',
        'is_active',
    ];

    //Converte automaticamente alcuni valori del database nei tipi PHP corretti
    protected function casts(): array
    {
        return [
            //Converte le date in oggetti Carbon
            'birth_date' => 'date',
            'driving_license_expiry_date' => 'date',

            //Converte 0 e 1 del database in false e true
            'is_active' => 'boolean',
        ];
    }


    //Restituisce tutti i noleggi appartenenti al cliente.

    //Relazione uno a molti (1:N):
    //un cliente può effettuare molti noleggi, mentre ogni noleggio appartiene a un solo cliente
    public function rentals(): HasMany
    {
        return $this->hasMany(Rental::class);
    }
}
