<?php

namespace App\Http\Requests\Api;

use App\Models\Vehicle;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexVehicleRequest extends FormRequest
{
    // Permette la richiesta agli utenti autenticati
    public function authorize(): bool
    {
        return true;
    }

    // Normalizza i filtri ricevuti dalla query string
    protected function prepareForValidation(): void
    {
        $normalizedData = [];

        if (
            $this->exists('search')
            && is_string($this->input('search'))
        ) {
            $normalizedData['search'] = trim(
                $this->input('search')
            );
        }

        if (
            $this->exists('type')
            && is_string($this->input('type'))
        ) {
            $normalizedData['type'] = strtolower(
                trim($this->input('type'))
            );
        }

        /*
         * Permette di ricevere is_active sia come 1/0
         * sia come true/false dalla futura applicazione React.
         */
        if (
            $this->exists('is_active')
            && is_string($this->input('is_active'))
        ) {
            $isActive = filter_var(
                $this->input('is_active'),
                FILTER_VALIDATE_BOOLEAN,
                FILTER_NULL_ON_FAILURE
            );

            if ($isActive !== null) {
                $normalizedData['is_active'] = $isActive;
            }
        }

        if ($normalizedData !== []) {
            $this->merge($normalizedData);
        }
    }

    // Definisce i filtri accettati dall’elenco dei veicoli
    public function rules(): array
    {
        return [
            'search' => [
                'sometimes',
                'nullable',
                'string',
                'max:100',
            ],
            'type' => [
                'sometimes',
                'nullable',
                Rule::in(Vehicle::TYPES),
            ],
            'is_active' => [
                'sometimes',
                'boolean',
            ],
            'per_page' => [
                'sometimes',
                'integer',
                'min:1',
                'max:100',
            ],
        ];
    }
}
