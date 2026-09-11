<?php

namespace App\Http\Requests\Api;

use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class DashboardRequest extends FormRequest
{
    // La dashboard è già protetta dal middleware auth:sanctum.
    public function authorize(): bool
    {
        return true;
    }

    // Prepara il periodo predefinito e normalizza le date ricevute.
    protected function prepareForValidation(): void
    {
        $normalizedData = [];

        // Rimuove eventuali spazi dalle date inviate.
        if (
            $this->exists('date_from')
            && is_string($this->input('date_from'))
        ) {
            $normalizedData['date_from'] = trim(
                $this->input('date_from')
            );
        }

        if (
            $this->exists('date_to')
            && is_string($this->input('date_to'))
        ) {
            $normalizedData['date_to'] = trim(
                $this->input('date_to')
            );
        }

        /*
         * Se non viene inviato alcun periodo,
         * utilizza oggi e i 29 giorni precedenti:
         * in totale vengono analizzati 30 giorni.
         */
        if (
            ! $this->exists('date_from')
            && ! $this->exists('date_to')
        ) {
            $normalizedData['date_from'] = now()
                ->subDays(29)
                ->toDateString();

            $normalizedData['date_to'] = now()
                ->toDateString();
        }

        $this->merge($normalizedData);
    }

    // Definisce i filtri utilizzabili dalla dashboard.
    public function rules(): array
    {
        return [
            // Se si specifica un periodo, entrambe le date sono obbligatorie.
            'date_from' => [
                'required',
                'date_format:Y-m-d',
            ],
            'date_to' => [
                'required',
                'date_format:Y-m-d',
                'after_or_equal:date_from',
            ],

            // Permette di calcolare i dati di un solo veicolo.
            'vehicle_id' => [
                'sometimes',
                'required',
                'integer',
                'exists:vehicles,id',
            ],
        ];
    }

    // Impedisce richieste eccessivamente grandi.
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $dateFrom = Carbon::createFromFormat(
                'Y-m-d',
                $this->input('date_from')
            )->startOfDay();

            $dateTo = Carbon::createFromFormat(
                'Y-m-d',
                $this->input('date_to')
            )->endOfDay();

            // Il periodo massimo consentito è di 366 giorni inclusivi.
            if ($dateFrom->diffInDays($dateTo) > 365) {
                $validator->errors()->add(
                    'date_to',
                    'Il periodo della dashboard non può superare 366 giorni.'
                );
            }
        });
    }
}
