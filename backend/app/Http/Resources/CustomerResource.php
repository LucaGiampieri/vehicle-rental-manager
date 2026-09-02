<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CustomerResource extends JsonResource
{
    /**
     * Trasforma il cliente in una risposta JSON.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,

            //Le date vengono restituite sempre nel formato anno-mese-giorno
            'birth_date' => $this->birth_date?->format('Y-m-d'),

            'email' => $this->email,
            'phone' => $this->phone,
            'tax_code' => $this->tax_code,
            'driving_license_number' => $this->driving_license_number,

            'driving_license_expiry_date' => $this
                ->driving_license_expiry_date
                ?->format('Y-m-d'),

            'address' => $this->address,
            'notes' => $this->notes,
            'is_active' => $this->is_active,

            //Compare soltanto quando il controller ha caricato il conteggio
            'rentals_count' => $this->whenCounted('rentals'),

            //Le date tecniche vengono restituite nel formato ISO 8601
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
