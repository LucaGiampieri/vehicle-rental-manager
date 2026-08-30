<?php

namespace App\Http\Requests\Api;

use App\Models\Vehicle;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class UpdateVehicleRequest extends FormRequest
{
    //Permette l'esecuzione della validazione
    //L'accesso è comunque protetto dal middleware auth:sanctum
    public function authorize(): bool
    {
        return true;
    }

    //Normalizza soltanto i campi realmente inviati
    protected function prepareForValidation(): void
    {
        $normalizedData = [];

        //Normalizza la targa soltanto se è presente nella richiesta
        if ($this->has('license_plate')) {
            $normalizedData['license_plate'] = Str::upper(
                trim((string) $this->input('license_plate'))
            );
        }

        //Normalizza la marca soltanto se è presente
        if ($this->has('brand')) {
            $normalizedData['brand'] = trim(
                (string) $this->input('brand')
            );
        }

        //Normalizza il modello soltanto se è presente
        if ($this->has('model')) {
            $normalizedData['model'] = trim(
                (string) $this->input('model')
            );
        }

        //Normalizza il tipo soltanto se è presente
        if ($this->has('type')) {
            $normalizedData['type'] = Str::lower(
                trim((string) $this->input('type'))
            );
        }

        //Reinserisce nella richiesta soltanto i dati normalizzati
        $this->merge($normalizedData);
    }

    //Definisce le regole per modificare un veicolo esistente
    public function rules(): array
    {
        return [
            //La targa può rimanere quella del veicolo modificato
            //ma non può appartenere a un altro veicolo
            'license_plate' => [
                'sometimes',
                'required',
                'string',
                'max:20',
                Rule::unique('vehicles', 'license_plate')
                    ->ignore($this->route('vehicle')),
            ],

            //sometimes valida il campo soltanto se viene inviato
            'brand' => [
                'sometimes',
                'required',
                'string',
                'max:50',
            ],
            'model' => [
                'sometimes',
                'required',
                'string',
                'max:80',
            ],
            'type' => [
                'sometimes',
                'required',
                'string',
                Rule::in(Vehicle::TYPES),
            ],
            'parking_units' => [
                'sometimes',
                'required',
                'integer',
                Rule::in(Vehicle::ALLOWED_PARKING_UNITS),
            ],
            'year' => [
                'sometimes',
                'nullable',
                'integer',
                'min:1900',
                'max:'.(now()->year + 1),
            ],
            'mileage' => [
                'sometimes',
                'required',
                'integer',
                'min:0',
            ],
            'daily_rate' => [
                'sometimes',
                'nullable',
                'numeric',
                'min:0',
                'max:99999999.99',
            ],
            'is_active' => [
                'sometimes',
                'boolean',
            ],
        ];
    }
}
