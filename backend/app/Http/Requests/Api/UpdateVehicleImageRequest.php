<?php

namespace App\Http\Requests\Api;

use App\Models\VehicleImage;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateVehicleImageRequest extends FormRequest
{
    // L’accesso è già protetto dal middleware Sanctum
    public function authorize(): bool
    {
        return true;
    }

    // Normalizza i dati ricevuti tramite FormData o JSON
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
            $caption = trim($this->input('caption'));

            $normalizedData['caption'] = $caption === ''
                ? null
                : $caption;
        }

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

    // Definisce i dati modificabili
    public function rules(): array
    {
        return [
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

    // Impedisce richieste di modifica completamente vuote
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            if (
                ! $this->hasAny([
                    'category',
                    'caption',
                    'is_primary',
                    'sort_order',
                ])
            ) {
                $validator->errors()->add(
                    'image',
                    'Invia almeno un dato da modificare.'
                );
            }
        });
    }
}
