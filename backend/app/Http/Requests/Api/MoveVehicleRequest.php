<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class MoveVehicleRequest extends FormRequest
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

    //Dati necessari per indicare la nuova cella iniziale.
    public function rules(): array
    {
        return [
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
