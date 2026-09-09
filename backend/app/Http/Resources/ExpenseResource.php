<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ExpenseResource extends JsonResource
{
    /**
     * Trasforma la spesa in una risposta JSON.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'vehicle_id' => $this->vehicle_id,
            'category' => $this->category,
            'description' => $this->description,
            'amount' => $this->amount,

            //Le date vengono restituite nel formato usato dai form HTML
            'expense_date' => $this->expense_date?->format('Y-m-d'),
            'expires_on' => $this->expires_on?->format('Y-m-d'),

            //Null indica che la spesa non possiede una scadenza
            'is_expired' => $this->expires_on === null
                ? null
                : $this->expires_on->lt(today()),

            'mileage' => $this->mileage,
            'supplier' => $this->supplier,
            'notes' => $this->notes,

            //Riepilogo del veicolo associato
            'vehicle' => $this->whenLoaded(
                'vehicle',
                fn () => [
                    'id' => $this->vehicle->id,
                    'license_plate' => $this->vehicle->license_plate,
                    'brand' => $this->vehicle->brand,
                    'model' => $this->vehicle->model,
                    'type' => $this->vehicle->type,
                ]
            ),

            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
