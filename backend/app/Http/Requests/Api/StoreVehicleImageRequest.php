<?php

namespace App\Http\Requests\Api;

use App\Models\Vehicle;
use App\Models\VehicleImage;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreVehicleImageRequest extends FormRequest
{
    // L’accesso è già protetto dal middleware Sanctum
    public function authorize(): bool
    {
        return true;
    }

    // Normalizza i dati ricevuti tramite FormData
    protected function prepareForValidation(): void
    {
        $normalizedData = [];

        if (
            $this->exists('category')
            && is_string($this->input('category'))
        ) {
            $normalizedData['category'] = strtolower(
                trim($this->input('category'))
            );
        }

        if (
            $this->exists('caption')
            && is_string($this->input('caption'))
        ) {
            $normalizedData['caption'] = trim(
                $this->input('caption')
            );
        }

        /*
         * FormData invia normalmente i booleani come stringhe.
         * Converte quindi "true"/"false" e "1"/"0".
         */
        if (
            $this->exists('is_primary')
            && is_string($this->input('is_primary'))
        ) {
            $isPrimary = filter_var(
                $this->input('is_primary'),
                FILTER_VALIDATE_BOOLEAN,
                FILTER_NULL_ON_FAILURE
            );

            if ($isPrimary !== null) {
                $normalizedData['is_primary'] = $isPrimary;
            }
        }

        if ($normalizedData !== []) {
            $this->merge($normalizedData);
        }
    }

    // Definisce i dati accettati durante il caricamento
    public function rules(): array
    {
        return [
            'image' => [
                'required',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'max:5120',
            ],
            'category' => [
                'sometimes',
                'required',
                'string',
                Rule::in(VehicleImage::CATEGORIES),
            ],
            'caption' => [
                'sometimes',
                'nullable',
                'string',
                'max:255',
            ],
            'is_primary' => [
                'sometimes',
                'boolean',
            ],
            'sort_order' => [
                'sometimes',
                'integer',
                'min:0',
                'max:65535',
            ],
        ];
    }

    // Controlla il limite massimo della galleria
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $vehicle = $this->route('vehicle');

            if (! $vehicle instanceof Vehicle) {
                return;
            }

            if ($vehicle->images()->count() >= 10) {
                $validator->errors()->add(
                    'image',
                    'Ogni veicolo può contenere al massimo 10 immagini.'
                );
            }
        });
    }
}
