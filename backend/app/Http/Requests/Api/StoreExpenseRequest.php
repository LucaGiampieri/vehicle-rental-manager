<?php

namespace App\Http\Requests\Api;

use App\Models\Expense;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class StoreExpenseRequest extends FormRequest
{
    //Permette la validazione agli utenti già protetti da Sanctum
    public function authorize(): bool
    {
        return true;
    }

    //Normalizza i dati testuali
    protected function prepareForValidation(): void
    {
        $normalizedData = [];

        if (is_string($this->input('category'))) {
            $normalizedData['category'] = Str::lower(
                trim($this->input('category'))
            );
        }

        if (is_string($this->input('description'))) {
            $normalizedData['description'] = trim(
                $this->input('description')
            );
        }

        if (is_string($this->input('supplier'))) {
            $normalizedData['supplier'] = trim(
                $this->input('supplier')
            );
        }

        if (is_string($this->input('notes'))) {
            $normalizedData['notes'] = trim(
                $this->input('notes')
            );
        }

        $this->merge($normalizedData);
    }

    //Regole per la creazione
    public function rules(): array
    {
        return [
            'vehicle_id' => [
                'required',
                'integer',
                'exists:vehicles,id',
            ],
            'category' => [
                'required',
                'string',
                Rule::in(Expense::CATEGORIES),
            ],
            'description' => [
                'required',
                'string',
                'max:255',
            ],
            'amount' => [
                'required',
                'numeric',
                'min:0.01',
                'max:99999999.99',
            ],
            'expense_date' => [
                'required',
                'date_format:Y-m-d',
                'before_or_equal:today',
            ],
            'expires_on' => [
                'nullable',
                'date_format:Y-m-d',
                'after_or_equal:expense_date',
            ],
            'mileage' => [
                'nullable',
                'integer',
                'min:0',
            ],
            'supplier' => [
                'nullable',
                'string',
                'max:150',
            ],
            'notes' => [
                'nullable',
                'string',
                'max:5000',
            ],
        ];
    }
}
