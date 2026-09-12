<?php

namespace App\Http\Requests\Api;

use App\Models\Rental;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexRentalRequest extends FormRequest
{
    // Permette la richiesta agli utenti autenticati
    public function authorize(): bool
    {
        return true;
    }

    // Normalizza i filtri testuali
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
            $this->exists('status')
            && is_string($this->input('status'))
        ) {
            $normalizedData['status'] = strtolower(
                trim($this->input('status'))
            );
        }

        if ($normalizedData !== []) {
            $this->merge($normalizedData);
        }
    }

    // Definisce i filtri accettati dall’elenco dei noleggi
    public function rules(): array
    {
        $dateToRules = [
            'sometimes',
            'nullable',
            'date_format:Y-m-d',
        ];

        /*
         * Se è presente anche la data iniziale,
         * la data finale non può essere precedente.
         */
        if ($this->filled('date_from')) {
            $dateToRules[] = 'after_or_equal:date_from';
        }

        return [
            'search' => [
                'sometimes',
                'nullable',
                'string',
                'max:100',
            ],
            'status' => [
                'sometimes',
                'nullable',
                Rule::in(Rental::STATUSES),
            ],
            'vehicle_id' => [
                'sometimes',
                'nullable',
                'integer',
                'exists:vehicles,id',
            ],
            'customer_id' => [
                'sometimes',
                'nullable',
                'integer',
                'exists:customers,id',
            ],
            'date_from' => [
                'sometimes',
                'nullable',
                'date_format:Y-m-d',
            ],
            'date_to' => $dateToRules,
            'per_page' => [
                'sometimes',
                'integer',
                'min:1',
                'max:100',
            ],
        ];
    }
}
