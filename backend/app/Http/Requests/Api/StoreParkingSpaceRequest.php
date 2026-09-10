<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class StoreParkingSpaceRequest extends FormRequest
{
    //Permette l'esecuzione della validazione.
    //L'accesso è comunque protetto dal middleware auth:sanctum.
    public function authorize(): bool
    {
        return true;
    }

    //Normalizza i dati prima di eseguire la validazione.
    protected function prepareForValidation(): void
    {
        //Trasforma un'etichetta vuota in null.
        $label = Str::upper(
            trim((string) $this->input('label'))
        );

        //Se la zona non viene inviata, utilizza la zona principale.
        $zone = Str::lower(
            trim((string) $this->input('zone', 'main'))
        );

        $notes = $this->input('notes');

        //Rimuove gli spazi esterni soltanto se notes è una stringa.
        if (is_string($notes)) {
            $notes = trim($notes);

            if ($notes === '') {
                $notes = null;
            }
        }

        $this->merge([
            'label' => $label !== '' ? $label : null,
            'zone' => $zone,
            'notes' => $notes,
        ]);
    }

    //Definisce le regole per creare una nuova cella.
    public function rules(): array
    {
        return [
            //L'etichetta è facoltativa, ma non può essere duplicata.
            'label' => [
                'nullable',
                'string',
                'max:20',
                'unique:parking_spaces,label',
            ],

            //La zona è obbligatoria e può contenere massimo 50 caratteri.
            'zone' => [
                'required',
                'string',
                'max:50',
            ],

            //La riga deve essere un numero compatibile con unsignedSmallInteger.
            //La regola unique controlla l'intera posizione:
            //zona + riga + colonna.
            'row_number' => [
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
                    ),
            ],

            //La colonna è obbligatoria e deve essere positiva.
            'column_number' => [
                'required',
                'integer',
                'min:1',
                'max:65535',
            ],

            //Se non viene inviato, il database utilizza true.
            'is_active' => [
                'sometimes',
                'boolean',
            ],

            //Le annotazioni sono facoltative.
            'notes' => [
                'nullable',
                'string',
                'max:5000',
            ],
        ];
    }
}
