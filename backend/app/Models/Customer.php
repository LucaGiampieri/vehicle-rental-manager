<?php

namespace App\Models;

use Database\Factories\CustomerFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Customer extends Model
{
    /**
     * Permette di creare clienti fittizi attraverso CustomerFactory.
     *
     * @use HasFactory<CustomerFactory>
     */
    use HasFactory;

    // Elenca i campi assegnabili in modo controllato.
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

    // Converte automaticamente i valori nei tipi PHP corretti.
    protected function casts(): array
    {
        return [
            'birth_date' => 'date',
            'driving_license_expiry_date' => 'date',
            'is_active' => 'boolean',
        ];
    }

    /*
     * Relazione uno a molti:
     * un cliente può avere numerosi noleggi.
     */
    public function rentals(): HasMany
    {
        return $this->hasMany(Rental::class);
    }

    /*
     * Restituisce l’eventuale noleggio attualmente in corso.
     *
     * In una situazione corretta dovrebbe esistere al massimo
     * un noleggio attivo per ogni cliente.
     */
    public function activeRental(): HasOne
    {
        return $this->hasOne(Rental::class)
            ->where('status', Rental::STATUS_ACTIVE)
            ->latest('actual_starts_at');
    }

    /*
     * Restituisce la prenotazione futura più vicina.
     *
     * Le prenotazioni già iniziate o scadute non vengono considerate.
     */
    public function nextReservation(): HasOne
    {
        return $this->hasOne(Rental::class)
            ->where('status', Rental::STATUS_RESERVED)
            ->where('starts_at', '>=', now())
            ->oldest('starts_at');
    }
}
