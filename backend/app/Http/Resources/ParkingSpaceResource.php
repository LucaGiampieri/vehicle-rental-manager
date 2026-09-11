<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ParkingSpaceResource extends JsonResource
{
    /**
     * Trasforma la cella nel formato JSON restituito dall'API.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'label' => $this->label,
            'zone' => $this->zone,
            'row_number' => $this->row_number,
            'column_number' => $this->column_number,

            // Identifica il veicolo che occupa attualmente la cella.
            'vehicle_id' => $this->vehicle_id,

            // Permette al frontend di capire rapidamente se è occupata.
            'is_occupied' => $this->vehicle_id !== null,

            'is_active' => $this->is_active,
            'notes' => $this->notes,

            // Mostra il veicolo soltanto quando il controller
            // ha caricato la relazione vehicle.
            'vehicle' => $this->when(
                $this->relationLoaded('vehicle'),
                fn () => $this->vehicle === null
                    ? null
                    : [
                        'id' => $this->vehicle->id,
                        'license_plate' => $this->vehicle->license_plate,
                        'brand' => $this->vehicle->brand,
                        'model' => $this->vehicle->model,
                        'type' => $this->vehicle->type,
                        'parking_units' => $this->vehicle->parking_units,
                        'is_active' => $this->vehicle->is_active,
                    ]
            ),

            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
