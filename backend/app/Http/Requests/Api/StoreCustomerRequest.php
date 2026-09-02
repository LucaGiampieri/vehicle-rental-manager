<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class StoreCustomerRequest extends FormRequest
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
        $normalizedData = [
            //Rimuove gli spazi esterni da nome e cognome
            'first_name' => trim(
                (string) $this->input('first_name')
            ),
            'last_name' => trim(
                (string) $this->input('last_name')
            ),

            //Salva il numero della patente in maiuscolo
            'driving_license_number' => Str::upper(
                trim((string) $this->input('driving_license_number'))
            ),
        ];

        //Normalizza l'email soltanto se è stata inviata
        if ($this->exists('email')) {
            $email = $this->input('email');

            $normalizedData['email'] = $email === null
                ? null
                : Str::lower(trim((string) $email));
        }

        //Normalizza il telefono soltanto se è stato inviato
        if ($this->exists('phone')) {
            $phone = $this->input('phone');

            $normalizedData['phone'] = $phone === null
                ? null
                : trim((string) $phone);
        }

        //Normalizza il codice fiscale soltanto se è stato inviato
        if ($this->exists('tax_code')) {
            $taxCode = $this->input('tax_code');

            $normalizedData['tax_code'] = $taxCode === null
                ? null
                : Str::upper(trim((string) $taxCode));
        }

        //Normalizza l'indirizzo soltanto se è stato inviato
        if ($this->exists('address')) {
            $address = $this->input('address');

            $normalizedData['address'] = $address === null
                ? null
                : trim((string) $address);
        }

        //Rimuove gli spazi esterni dalle note senza alterarne il contenuto
        if ($this->exists('notes')) {
            $notes = $this->input('notes');

            $normalizedData['notes'] = $notes === null
                ? null
                : trim((string) $notes);
        }

        $this->merge($normalizedData);
    }

    //Definisce le regole per creare un nuovo cliente
    public function rules(): array
    {
        return [
            //Nome e cognome sono obbligatori
            'first_name' => [
                'required',
                'string',
                'max:100',
            ],
            'last_name' => [
                'required',
                'string',
                'max:100',
            ],

            //La data di nascita è facoltativa ma non può essere futura
            'birth_date' => [
                'nullable',
                'date_format:Y-m-d',
                'before_or_equal:today',
            ],

            //L'email è facoltativa ma non può essere duplicata
            'email' => [
                'nullable',
                'string',
                'email',
                'max:255',
                Rule::unique('customers', 'email'),
            ],

            //Il numero di telefono è facoltativo
            'phone' => [
                'nullable',
                'string',
                'max:30',
            ],

            //Il codice fiscale può contenere anche identificativi esteri
            'tax_code' => [
                'nullable',
                'string',
                'max:32',
                Rule::unique('customers', 'tax_code'),
            ],

            //Il numero della patente è obbligatorio e univoco
            'driving_license_number' => [
                'required',
                'string',
                'max:50',
                Rule::unique('customers', 'driving_license_number'),
            ],

            //La scadenza è obbligatoria
            'driving_license_expiry_date' => [
                'required',
                'date_format:Y-m-d',
            ],

            //Indirizzo e note possono essere aggiunti successivamente
            'address' => [
                'nullable',
                'string',
                'max:255',
            ],
            'notes' => [
                'nullable',
                'string',
                'max:5000',
            ],

            //Se non viene inviato, il database utilizza true
            'is_active' => [
                'sometimes',
                'boolean',
            ],
        ];
    }
}
