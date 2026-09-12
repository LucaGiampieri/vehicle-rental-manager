<?php

namespace App\Models;

use Database\Factories\VehicleImageFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VehicleImage extends Model
{
    /** @use HasFactory<VehicleImageFactory> */
    use HasFactory;

    // Categorie disponibili per classificare le fotografie
    public const CATEGORY_EXTERIOR = 'exterior';

    public const CATEGORY_INTERIOR = 'interior';

    public const CATEGORY_PLATE = 'plate';

    public const CATEGORY_DAMAGE = 'damage';

    public const CATEGORY_OTHER = 'other';

    // Elenco completo delle categorie ammesse
    public const CATEGORIES = [
        self::CATEGORY_EXTERIOR,
        self::CATEGORY_INTERIOR,
        self::CATEGORY_PLATE,
        self::CATEGORY_DAMAGE,
        self::CATEGORY_OTHER,
    ];

    // Campi assegnabili in modo controllato
    protected $fillable = [
        'vehicle_id',
        'path',
        'original_name',
        'mime_type',
        'size',
        'category',
        'caption',
        'is_primary',
        'sort_order',
    ];

    // Converte i valori del database nei tipi PHP corretti
    protected function casts(): array
    {
        return [
            'size' => 'integer',
            'is_primary' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    // Ogni immagine appartiene a un solo veicolo
    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }
}
