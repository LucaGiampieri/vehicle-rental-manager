<?php

namespace App\Http\Requests\Api;

use App\Models\Vehicle;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class StoreVehicleRequest extends FormRequest
{
    //Permette l'esecuzione della validazione
    //L'accesso è comunque protetto dal middleware auth:sanctum
    public function authorize(): bool
    {
        return true;
    }

    //Normalizza alcuni dati prima di validarli
    protected function prepareForValidation(): void
    {
        $this->merge([
            //Rimuove gli spazi esterni e salva la targa in maiuscolo
            'license_plate' => Str::upper(
                trim((string) $this->input('license_plate'))
            ),

            //Rimuove gli spazi esterni da marca e modello
            'brand' => trim(
                (string) $this->input('brand')
            ),
            'model' => trim(
                (string) $this->input('model')
            ),

            //Salva il tipo in minuscolo
            'type' => Str::lower(
                trim((string) $this->input('type'))
            ),
        ]);
    }

    //Definisce le regole per creare un nuovo veicolo
    public function rules(): array
    {
        return [
            //La targa è obbligatoria e non può essere duplicata
            'license_plate' => [
                'required',
                'string',
                'max:20',
                'unique:vehicles,license_plate',
            ],

            //Marca e modello sono obbligatori
            'brand' => [
                'required',
                'string',
                'max:50',
            ],
            'model' => [
                'required',
                'string',
                'max:80',
            ],

            //Il tipo deve appartenere all'elenco definito nel Model
            'type' => [
                'required',
                'string',
                Rule::in(Vehicle::TYPES),
            ],

            //Il numero di celle deve essere 1, 2, 4 oppure 8
            'parking_units' => [
                'required',
                'integer',
                Rule::in(Vehicle::ALLOWED_PARKING_UNITS),
            ],

            //L'anno può essere omesso ma deve essere realistico
            'year' => [
                'nullable',
                'integer',
                'min:1900',
                'max:'.(now()->year + 1),
            ],

            //Il chilometraggio è obbligatorio e non può essere negativo
            'mileage' => [
                'required',
                'integer',
                'min:0',
            ],

            //La tariffa può essere aggiunta anche successivamente
            'daily_rate' => [
                'nullable',
                'numeric',
                'min:0',
                'max:99999999.99',
            ],

            //Se non viene inviato, il database utilizza true
            'is_active' => [
                'sometimes',
                'boolean',
            ],
        ];
    }
}
