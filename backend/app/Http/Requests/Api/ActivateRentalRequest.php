<?php

namespace App\Http\Requests\Api;

use App\Models\Rental;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class ActivateRentalRequest extends FormRequest
{
    //Permette l'esecuzione della validazione
    public function authorize(): bool
    {
        return true;
    }

    //Normalizza le eventuali annotazioni
    protected function prepareForValidation(): void
    {
        $notes = $this->input('notes');

        if ($this->exists('notes') && is_string($notes)) {
            $this->merge([
                'notes' => trim($notes),
            ]);
        }
    }

    //Definisce i dati necessari per consegnare il mezzo
    public function rules(): array
    {
        return [
            'start_mileage' => [
                'required',
                'integer',
                'min:0',
            ],
            'amount_paid' => [
                'sometimes',
                'required',
                'numeric',
                'min:0',
                'max:99999999.99',
            ],
            'notes' => [
                'sometimes',
                'nullable',
                'string',
                'max:5000',
            ],
        ];
    }

    //Controlla lo stato del noleggio e i dati collegati
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $rental = $this->route('rental');

            if (! $rental instanceof Rental) {
                return;
            }

            //Soltanto una prenotazione può diventare attiva
            if ($rental->status !== Rental::STATUS_RESERVED) {
                $validator->errors()->add(
                    'rental',
                    'Soltanto un noleggio prenotato può essere attivato.'
                );

                return;
            }

            //La consegna non può avvenire prima dell'inizio concordato
            if (now()->lt($rental->starts_at)) {
                $validator->errors()->add(
                    'rental',
                    'Il noleggio non può essere attivato prima della data iniziale.'
                );
            }

            //Il veicolo deve essere ancora utilizzabile
            if (! $rental->vehicle->is_active) {
                $validator->errors()->add(
                    'vehicle_id',
                    'Il veicolo associato non è attivo.'
                );
            }

            //Il cliente deve essere ancora attivo
            if (! $rental->customer->is_active) {
                $validator->errors()->add(
                    'customer_id',
                    'Il cliente associato non è attivo.'
                );
            }

            //La patente deve essere ancora valida per tutto il periodo
            if (
                $rental->customer
                    ->driving_license_expiry_date
                    ->copy()
                    ->endOfDay()
                    ->lt($rental->expected_ends_at)
            ) {
                $validator->errors()->add(
                    'customer_id',
                    'La patente del cliente scade prima della fine prevista del noleggio.'
                );
            }

            //Il chilometraggio non può diminuire rispetto al veicolo
            if (
                $this->integer('start_mileage')
                < $rental->vehicle->mileage
            ) {
                $validator->errors()->add(
                    'start_mileage',
                    'Il chilometraggio iniziale non può essere inferiore a quello attuale del veicolo.'
                );
            }

            //Il pagamento non può superare il totale
            if (
                $this->exists('amount_paid')
                && (float) $this->input('amount_paid')
                    > (float) $rental->total_amount
            ) {
                $validator->errors()->add(
                    'amount_paid',
                    'L’importo pagato non può superare il totale del noleggio.'
                );
            }
        });
    }
}
