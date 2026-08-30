<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Expense extends Model
{
    //Permette di creare spese fittizie tramite ExpenseFactory
    use HasFactory;

    //Categorie disponibili per classificare i costi
    public const CATEGORY_PURCHASE = 'purchase';
    public const CATEGORY_MAINTENANCE = 'maintenance';
    public const CATEGORY_REPAIR = 'repair';
    public const CATEGORY_ROAD_TAX = 'road_tax';
    public const CATEGORY_INSURANCE = 'insurance';
    public const CATEGORY_FUEL = 'fuel';
    public const CATEGORY_CLEANING = 'cleaning';
    public const CATEGORY_INSPECTION = 'inspection';
    public const CATEGORY_OTHER = 'other';

    //Elenco completo delle categorie ammesse
    public const CATEGORIES = [
        self::CATEGORY_PURCHASE,
        self::CATEGORY_MAINTENANCE,
        self::CATEGORY_REPAIR,
        self::CATEGORY_ROAD_TAX,
        self::CATEGORY_INSURANCE,
        self::CATEGORY_FUEL,
        self::CATEGORY_CLEANING,
        self::CATEGORY_INSPECTION,
        self::CATEGORY_OTHER,
    ];

    //Campi che possono essere assegnati in modo controllato
    protected $fillable = [
        'vehicle_id',
        'category',
        'description',
        'amount',
        'expense_date',
        'expires_on',
        'mileage',
        'supplier',
        'notes',
    ];

    //Converte automaticamente i valori del database nei tipi PHP corretti
    protected function casts(): array
    {
        return [
            //Mantiene sempre due cifre decimali per l'importo
            'amount' => 'decimal:2',

            //Converte le date in oggetti Carbon
            'expense_date' => 'date',
            'expires_on' => 'date',

            //Converte il chilometraggio in un numero intero
            'mileage' => 'integer',
        ];
    }

    //Relazione molti a uno (N:1):
    //ogni spesa appartiene a un veicolo, mentre un veicolo può avere molte spese
    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }
}
