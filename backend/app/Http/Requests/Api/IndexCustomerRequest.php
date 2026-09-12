<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class IndexCustomerRequest extends FormRequest
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

        /*
         * Accetta lo stato sia come 1/0
         * sia come true/false.
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

    // Definisce i filtri accettati dall’elenco dei clienti
    public function rules(): array
    {
        return [
            'search' => [
                'sometimes',
                'nullable',
                'string',
                'max:100',
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
