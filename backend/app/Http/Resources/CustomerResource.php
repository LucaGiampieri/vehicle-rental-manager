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
        /*
         * Controlla se il controller ha caricato i dati economici.
         * Questo accade nella pagina di dettaglio, ma non nell’elenco.
         */
        $hasFinancialSummary = array_key_exists(
            'completed_rentals_total',
            $this->resource->getAttributes()
        );

        $nonCancelledTotal = (float) (
            $this->non_cancelled_rentals_total ?? 0
        );

        $paidTotal = (float) (
            $this->paid_rentals_total ?? 0
        );

        return [
            'id' => $this->id,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,

            // Le date vengono restituite nel formato anno-mese-giorno.
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

            // Numero complessivo dei noleggi.
            'rentals_count' => $this->whenCounted('rentals'),

            /*
             * Conteggi suddivisi per stato.
             * Sono inclusi soltanto nella scheda del cliente.
             */
            'rental_summary' => $this->when(
                array_key_exists(
                    'reserved_rentals_count',
                    $this->resource->getAttributes()
                ),
                fn (): array => [
                    'total' => $this->rentals_count ?? 0,
                    'reserved' => $this->reserved_rentals_count ?? 0,
                    'active' => $this->active_rentals_count ?? 0,
                    'completed' => $this->completed_rentals_count ?? 0,
                    'cancelled' => $this->cancelled_rentals_count ?? 0,
                ]
            ),

            /*
             * Riepilogo economico dei noleggi non annullati.
             *
             * completed_total: valore dei noleggi conclusi;
             * open_total: valore di prenotazioni e noleggi aperti;
             * paid_total: denaro già ricevuto;
             * balance_due: importo ancora da ricevere.
             */
            'financial_summary' => $this->when(
                $hasFinancialSummary,
                fn (): array => [
                    'completed_total' => number_format(
                        (float) (
                            $this->completed_rentals_total ?? 0
                        ),
                        2,
                        '.',
                        ''
                    ),

                    'open_total' => number_format(
                        (float) (
                            $this->open_rentals_total ?? 0
                        ),
                        2,
                        '.',
                        ''
                    ),

                    'paid_total' => number_format(
                        $paidTotal,
                        2,
                        '.',
                        ''
                    ),

                    'balance_due' => number_format(
                        max(
                            0,
                            $nonCancelledTotal - $paidTotal
                        ),
                        2,
                        '.',
                        ''
                    ),
                ]
            ),

            // Noleggio attualmente in corso, se presente.
            'active_rental' => new RentalResource(
                $this->whenLoaded('activeRental')
            ),

            // Prenotazione futura più vicina, se presente.
            'next_reservation' => new RentalResource(
                $this->whenLoaded('nextReservation')
            ),

            // Date tecniche nel formato ISO 8601.
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
