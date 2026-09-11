<?php

namespace App\Http\Requests\Api;

use App\Models\ParkingSpace;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class UpdateParkingSpaceRequest extends FormRequest
{
    // Permette l'esecuzione della validazione.
    // L'accesso è comunque protetto dal middleware auth:sanctum.
    public function authorize(): bool
    {
        return true;
    }

    // Normalizza soltanto i campi realmente inviati.
    protected function prepareForValidation(): void
    {
        $normalizedData = [];

        // Recupera la cella attraverso il Route Model Binding.
        $parkingSpace = $this->route('parking_space');

        if ($this->has('label')) {
            $label = Str::upper(
                trim((string) $this->input('label'))
            );

            $normalizedData['label'] =
                $label !== '' ? $label : null;
        }

        if ($this->has('zone')) {
            $normalizedData['zone'] = Str::lower(
                trim((string) $this->input('zone'))
            );
        }

        if ($this->has('notes')) {
            $notes = $this->input('notes');

            if (is_string($notes)) {
                $notes = trim($notes);

                if ($notes === '') {
                    $notes = null;
                }
            }

            $normalizedData['notes'] = $notes;
        }

        /*
         * Se viene modificata una parte della posizione, recupera dalla cella
         * attuale gli altri valori. Questo permette di controllare sempre
         * l'unicità di zona + riga + colonna.
         */
        if (
            $parkingSpace instanceof ParkingSpace
            && $this->hasAny([
                'zone',
                'row_number',
                'column_number',
            ])
        ) {
            $normalizedData['zone'] =
                $normalizedData['zone']
                ?? $parkingSpace->zone;

            $normalizedData['row_number'] =
                $this->has('row_number')
                ? $this->input('row_number')
                : $parkingSpace->row_number;

            $normalizedData['column_number'] =
                $this->has('column_number')
                ? $this->input('column_number')
                : $parkingSpace->column_number;
        }

        $this->merge($normalizedData);
    }

    // Definisce le regole per modificare una cella esistente.
    public function rules(): array
    {
        $parkingSpace = $this->route('parking_space');

        return [
            // L'etichetta può rimanere quella della cella modificata,
            // ma non può appartenere a un'altra cella.
            'label' => [
                'sometimes',
                'nullable',
                'string',
                'max:20',
                Rule::unique('parking_spaces', 'label')
                    ->ignore($parkingSpace),
            ],

            'zone' => [
                'sometimes',
                'required',
                'string',
                'max:50',
            ],

            // Controlla che la nuova posizione non sia già occupata
            // da un'altra cella.
            'row_number' => [
                'sometimes',
                'required',
                'integer',
                'min:1',
                'max:65535',
                Rule::unique('parking_spaces', 'row_number')
                    ->where(
                        fn ($query) => $query
                            ->where('zone', $this->input('zone'))
                            ->where(
                                'column_number',
                                $this->input('column_number')
                            )
                    )
                    ->ignore($parkingSpace),
            ],

            'column_number' => [
                'sometimes',
                'required',
                'integer',
                'min:1',
                'max:65535',
            ],

            'is_active' => [
                'sometimes',
                'boolean',
            ],

            'notes' => [
                'sometimes',
                'nullable',
                'string',
                'max:5000',
            ],
        ];
    }
}
