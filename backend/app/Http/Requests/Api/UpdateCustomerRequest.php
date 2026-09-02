<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class UpdateCustomerRequest extends FormRequest
{
    //Permette l'esecuzione della validazione
    //L'accesso è comunque protetto dal middleware auth:sanctum
    public function authorize(): bool
    {
        return true;
    }

    //Normalizza solamente i campi realmente inviati
    protected function prepareForValidation(): void
    {
        $normalizedData = [];

        if ($this->exists('first_name')) {
            $firstName = $this->input('first_name');

            if (is_string($firstName)) {
                $normalizedData['first_name'] = trim($firstName);
            }
        }

        if ($this->exists('last_name')) {
            $lastName = $this->input('last_name');

            if (is_string($lastName)) {
                $normalizedData['last_name'] = trim($lastName);
            }
        }

        if ($this->exists('email')) {
            $email = $this->input('email');

            if (is_string($email)) {
                $normalizedData['email'] = Str::lower(trim($email));
            }
        }

        if ($this->exists('phone')) {
            $phone = $this->input('phone');

            if (is_string($phone)) {
                $normalizedData['phone'] = trim($phone);
            }
        }

        if ($this->exists('tax_code')) {
            $taxCode = $this->input('tax_code');

            if (is_string($taxCode)) {
                $normalizedData['tax_code'] = Str::upper(trim($taxCode));
            }
        }

        if ($this->exists('driving_license_number')) {
            $licenseNumber = $this->input('driving_license_number');

            if (is_string($licenseNumber)) {
                $normalizedData['driving_license_number'] = Str::upper(
                    trim($licenseNumber)
                );
            }
        }

        if ($this->exists('address')) {
            $address = $this->input('address');

            if (is_string($address)) {
                $normalizedData['address'] = trim($address);
            }
        }

        if ($this->exists('notes')) {
            $notes = $this->input('notes');

            if (is_string($notes)) {
                $normalizedData['notes'] = trim($notes);
            }
        }

        $this->merge($normalizedData);
    }

    //Definisce le regole per modificare un cliente esistente
    public function rules(): array
    {
        $customer = $this->route('customer');

        return [
            //Sometimes significa: valida il campo soltanto se viene inviato
            'first_name' => [
                'sometimes',
                'required',
                'string',
                'max:100',
            ],
            'last_name' => [
                'sometimes',
                'required',
                'string',
                'max:100',
            ],
            'birth_date' => [
                'sometimes',
                'nullable',
                'date_format:Y-m-d',
                'before_or_equal:today',
            ],
            'email' => [
                'sometimes',
                'nullable',
                'string',
                'email',
                'max:255',
                Rule::unique('customers', 'email')->ignore($customer),
            ],
            'phone' => [
                'sometimes',
                'nullable',
                'string',
                'max:30',
            ],
            'tax_code' => [
                'sometimes',
                'nullable',
                'string',
                'max:32',
                Rule::unique('customers', 'tax_code')->ignore($customer),
            ],
            'driving_license_number' => [
                'sometimes',
                'required',
                'string',
                'max:50',
                Rule::unique('customers', 'driving_license_number')
                    ->ignore($customer),
            ],
            'driving_license_expiry_date' => [
                'sometimes',
                'required',
                'date_format:Y-m-d',
            ],
            'address' => [
                'sometimes',
                'nullable',
                'string',
                'max:255',
            ],
            'notes' => [
                'sometimes',
                'nullable',
                'string',
                'max:5000',
            ],
            'is_active' => [
                'sometimes',
                'boolean',
            ],
        ];
    }
}
