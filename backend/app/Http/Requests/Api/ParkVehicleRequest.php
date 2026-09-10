<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class ParkVehicleRequest extends FormRequest
{
    //L'accesso è già protetto dal middleware auth:sanctum.
    public function authorize(): bool
    {
        return true;
    }

    //Normalizza l'eventuale annotazione.
    protected function prepareForValidation(): void
    {
        if ($this->has('notes')) {
            $notes = $this->input('notes');

            if (is_string($notes)) {
                $notes = trim($notes);

                if ($notes === '') {
                    $notes = null;
                }
            }

            $this->merge([
                'notes' => $notes,
            ]);
        }
    }

    //Dati necessari per parcheggiare un veicolo.
    public function rules(): array
    {
        return [
            'vehicle_id' => [
                'required',
                'integer',
                'exists:vehicles,id',
            ],
            'parking_space_id' => [
                'required',
                'integer',
                'exists:parking_spaces,id',
            ],
            'notes' => [
                'nullable',
                'string',
                'max:5000',
            ],
        ];
    }
}
