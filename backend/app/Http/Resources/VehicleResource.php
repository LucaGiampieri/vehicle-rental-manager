<?php

namespace App\Http\Resources;

use App\Models\Vehicle;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class VehicleResource extends JsonResource
{
    /**
     * Trasforma il veicolo nel JSON inviato al frontend.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            // Dati identificativi del veicolo
            'id' => $this->id,
            'license_plate' => $this->license_plate,
            'brand' => $this->brand,
            'model' => $this->model,
            'type' => $this->type,

            // Informazioni necessarie per l'autorimessa
            'parking_units' => $this->parking_units,

            // Informazioni tecniche ed economiche
            'year' => $this->year,
            'mileage' => $this->mileage,
            'daily_rate' => $this->daily_rate,
            'is_active' => $this->is_active,

            /*
             * Stato operativo calcolato dal backend.
             * Il frontend dovrà soltanto tradurlo e mostrarlo.
             */
            'operational_status' => $this->operationalStatus(),

            // Noleggio attualmente in corso, se presente
            'active_rental' => $this->when(
                $this->relationLoaded('activeRental'),
                fn () => $this->activeRental
                    ? new RentalResource($this->activeRental)
                    : null
            ),

            // Prenotazione più vicina, se presente
            'next_reservation' => $this->when(
                $this->relationLoaded('nextReservation'),
                fn () => $this->nextReservation
                    ? new RentalResource($this->nextReservation)
                    : null
            ),

            // Copertina utilizzata negli elenchi e nelle schede
            'primary_image' => new VehicleImageResource(
                $this->whenLoaded('primaryImage')
            ),

            // Galleria completa, caricata soltanto quando richiesta
            'images' => VehicleImageResource::collection(
                $this->whenLoaded('images')
            ),

            // Numero complessivo delle fotografie
            'images_count' => $this->whenCounted('images'),

            // Conteggi aggiunti soltanto quando caricati dal Controller
            'rentals_count' => $this->whenCounted('rentals'),
            'expenses_count' => $this->whenCounted('expenses'),
            'parking_spaces_count' => $this->whenCounted('parkingSpaces'),

            // Celle attualmente occupate dal veicolo.
            // Sono incluse soltanto nella pagina di dettaglio.
            'parking_spaces' => ParkingSpaceResource::collection(
                $this->whenLoaded('parkingSpaces')
            ),

            // Date di creazione e ultima modifica
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }

    /*
     * Determina lo stato seguendo un ordine di priorità:
     *
     * 1. Un veicolo disattivato rimane sempre fuori servizio.
     * 2. Un noleggio attivo indica che il veicolo è consegnato.
     * 3. Una prenotazione indica che il veicolo è riservato.
     * 4. In tutti gli altri casi il veicolo è disponibile.
     */
    private function operationalStatus(): string
    {
        if (! $this->is_active) {
            return Vehicle::OPERATIONAL_STATUS_INACTIVE;
        }

        if (
            $this->relationLoaded('activeRental')
            && $this->activeRental !== null
        ) {
            return Vehicle::OPERATIONAL_STATUS_RENTED;
        }

        if (
            $this->relationLoaded('nextReservation')
            && $this->nextReservation !== null
        ) {
            return Vehicle::OPERATIONAL_STATUS_RESERVED;
        }

        return Vehicle::OPERATIONAL_STATUS_AVAILABLE;
    }
}
