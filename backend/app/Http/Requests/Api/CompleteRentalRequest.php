<?php

namespace App\Http\Requests\Api;

use App\Models\Rental;
use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class CompleteRentalRequest extends FormRequest
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

    //Definisce i dati necessari per registrare il rientro
    public function rules(): array
    {
        return [
            //Se non viene inviata, il controller utilizzerà l'ora attuale
            'actual_ends_at' => [
                'sometimes',
                'required',
                'date',
                'before_or_equal:now',
            ],

            //Il chilometraggio finale è obbligatorio
            'end_mileage' => [
                'required',
                'integer',
                'min:0',
            ],

            //Permette di registrare il saldo al momento del rientro
            'amount_paid' => [
                'sometimes',
                'required',
                'numeric',
                'min:0',
                'max:99999999.99',
            ],

            //Permette di aggiungere annotazioni sul rientro
            'notes' => [
                'sometimes',
                'nullable',
                'string',
                'max:5000',
            ],
        ];
    }

    //Controlla lo stato e la coerenza dei dati finali
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

            //Soltanto un noleggio attivo può essere completato
            if ($rental->status !== Rental::STATUS_ACTIVE) {
                $validator->errors()->add(
                    'rental',
                    'Soltanto un noleggio attivo può essere completato.'
                );

                return;
            }

            $actualEndsAt = $this->exists('actual_ends_at')
                ? Carbon::parse($this->input('actual_ends_at'))
                : now();

            //Usa la consegna effettiva; per compatibilità usa quella
            //prevista se il dato effettivo non fosse presente
            $rentalStartsAt = $rental->actual_starts_at
                ?? $rental->starts_at;

            //Il rientro non può essere precedente alla consegna
            if ($actualEndsAt->lt($rentalStartsAt)) {
                $validator->errors()->add(
                    'actual_ends_at',
                    'La data di rientro non può essere precedente all’inizio del noleggio.'
                );
            }

            //Un noleggio attivo deve possedere il chilometraggio iniziale
            if ($rental->start_mileage === null) {
                $validator->errors()->add(
                    'rental',
                    'Il noleggio non possiede un chilometraggio iniziale valido.'
                );

                return;
            }

            //Il chilometraggio finale non può essere inferiore a quello iniziale
            if (
                $this->integer('end_mileage')
                < $rental->start_mileage
            ) {
                $validator->errors()->add(
                    'end_mileage',
                    'Il chilometraggio finale non può essere inferiore a quello iniziale.'
                );
            }

            //L'importo pagato non può superare il totale concordato
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
