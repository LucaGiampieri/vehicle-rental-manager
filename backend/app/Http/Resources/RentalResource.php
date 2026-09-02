<?php

namespace App\Http\Resources;

use App\Models\Rental;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RentalResource extends JsonResource
{
    /**
     * Trasforma il noleggio in una risposta JSON.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,

            //Identificativi utilizzabili nei form
            'vehicle_id' => $this->vehicle_id,
            'customer_id' => $this->customer_id,

            'status' => $this->status,

            //Periodo previsto ed effettivo
            'starts_at' => $this->starts_at?->toISOString(),
            'actual_starts_at' => $this->actual_starts_at?->toISOString(),
            'expected_ends_at' => $this->expected_ends_at?->toISOString(),
            'actual_ends_at' => $this->actual_ends_at?->toISOString(),

            //Dati economici
            'daily_rate' => $this->daily_rate,
            'chargeable_days' => Rental::calculateChargeableDays(
                $this->starts_at,
                $this->expected_ends_at
            ),
            'total_amount' => $this->total_amount,
            'amount_paid' => $this->amount_paid,
            'balance_due' => number_format(
                max(
                    0,
                    (float) $this->total_amount
                    - (float) $this->amount_paid
                ),
                2,
                '.',
                ''
            ),

            //Chilometraggi alla consegna e al rientro
            'start_mileage' => $this->start_mileage,
            'end_mileage' => $this->end_mileage,

            'notes' => $this->notes,

            //Riepilogo del mezzo, senza esporre inutilmente tutti i dati
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

            //Riepilogo del cliente associato
            'customer' => $this->whenLoaded(
                'customer',
                fn () => [
                    'id' => $this->customer->id,
                    'first_name' => $this->customer->first_name,
                    'last_name' => $this->customer->last_name,
                    'email' => $this->customer->email,
                    'phone' => $this->customer->phone,
                    'driving_license_number' => $this
                        ->customer
                        ->driving_license_number,
                    'driving_license_expiry_date' => $this
                        ->customer
                        ->driving_license_expiry_date
                        ?->format('Y-m-d'),
                ]
            ),

            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
