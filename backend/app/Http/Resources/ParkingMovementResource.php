<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ParkingMovementResource extends JsonResource
{
    /**
     * Trasforma un movimento nel formato JSON restituito dall'API.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,

            // Riferimento attuale al veicolo e fotografia storica della targa.
            'vehicle_id' => $this->vehicle_id,
            'vehicle_license_plate' => $this->vehicle_license_plate,
            'parking_units' => $this->parking_units,

            // Posizione dalla quale è partito il veicolo.
            // Durante il primo parcheggio sarà null.
            'from' => $this->from_zone === null
                ? null
                : [
                    'parking_space_id' => $this->from_parking_space_id,
                    'zone' => $this->from_zone,
                    'row_number' => $this->from_row_number,
                    'column_number' => $this->from_column_number,
                ],

            // Posizione verso la quale è stato spostato il veicolo.
            // Durante l'uscita dall'autorimessa sarà null.
            'to' => $this->to_zone === null
                ? null
                : [
                    'parking_space_id' => $this->to_parking_space_id,
                    'zone' => $this->to_zone,
                    'row_number' => $this->to_row_number,
                    'column_number' => $this->to_column_number,
                ],

            'notes' => $this->notes,
            'occurred_at' => $this->occurred_at?->toISOString(),

            // Dati attuali del veicolo, se esiste ancora.
            'vehicle' => $this->when(
                $this->relationLoaded('vehicle'),
                fn () => $this->vehicle === null
                    ? null
                    : [
                        'id' => $this->vehicle->id,
                        'license_plate' => $this->vehicle->license_plate,
                        'brand' => $this->vehicle->brand,
                        'model' => $this->vehicle->model,
                        'parking_units' => $this->vehicle->parking_units,
                    ]
            ),

            // Utente che ha eseguito manualmente l'operazione.
            'performed_by' => $this->when(
                $this->relationLoaded('performedBy'),
                fn () => $this->performedBy === null
                    ? null
                    : [
                        'id' => $this->performedBy->id,
                        'name' => $this->performedBy->name,
                        'email' => $this->performedBy->email,
                    ]
            ),

            // Noleggio eventualmente associato al movimento.
            'rental' => $this->when(
                $this->relationLoaded('rental'),
                fn () => $this->rental === null
                    ? null
                    : [
                        'id' => $this->rental->id,
                        'status' => $this->rental->status,
                    ]
            ),

            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
