<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class VehicleImageResource extends JsonResource
{
    /**
     * Trasforma l’immagine nel formato JSON usato dal frontend.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'vehicle_id' => $this->vehicle_id,

            // Indirizzo completo utilizzabile da React
            'url' => Storage::disk('public')->url(
                $this->path
            ),

            // Informazioni del file
            'original_name' => $this->original_name,
            'mime_type' => $this->mime_type,
            'size' => $this->size,

            // Informazioni della galleria
            'category' => $this->category,
            'caption' => $this->caption,
            'is_primary' => $this->is_primary,
            'sort_order' => $this->sort_order,

            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
