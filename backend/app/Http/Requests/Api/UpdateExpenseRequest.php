<?php

namespace App\Http\Requests\Api;

use App\Models\Expense;
use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateExpenseRequest extends FormRequest
{
    // Permette la validazione agli utenti già protetti da Sanctum
    public function authorize(): bool
    {
        return true;
    }

    // Normalizza solamente i campi inviati
    protected function prepareForValidation(): void
    {
        $normalizedData = [];

        if (
            $this->exists('category')
            && is_string($this->input('category'))
        ) {
            $normalizedData['category'] = Str::lower(
                trim($this->input('category'))
            );
        }

        if (
            $this->exists('description')
            && is_string($this->input('description'))
        ) {
            $normalizedData['description'] = trim(
                $this->input('description')
            );
        }

        if (
            $this->exists('supplier')
            && is_string($this->input('supplier'))
        ) {
            $normalizedData['supplier'] = trim(
                $this->input('supplier')
            );
        }

        if (
            $this->exists('notes')
            && is_string($this->input('notes'))
        ) {
            $normalizedData['notes'] = trim(
                $this->input('notes')
            );
        }

        $this->merge($normalizedData);
    }

    // Regole per la modifica parziale
    public function rules(): array
    {
        return [
            'vehicle_id' => [
                'sometimes',
                'required',
                'integer',
                'exists:vehicles,id',
            ],
            'category' => [
                'sometimes',
                'required',
                'string',
                Rule::in(Expense::CATEGORIES),
            ],
            'description' => [
                'sometimes',
                'required',
                'string',
                'max:255',
            ],
            'amount' => [
                'sometimes',
                'required',
                'numeric',
                'min:0.01',
                'max:99999999.99',
            ],
            'expense_date' => [
                'sometimes',
                'required',
                'date_format:Y-m-d',
                'before_or_equal:today',
            ],
            'expires_on' => [
                'sometimes',
                'nullable',
                'date_format:Y-m-d',
            ],
            'mileage' => [
                'sometimes',
                'nullable',
                'integer',
                'min:0',
            ],
            'supplier' => [
                'sometimes',
                'nullable',
                'string',
                'max:150',
            ],
            'notes' => [
                'sometimes',
                'nullable',
                'string',
                'max:5000',
            ],
        ];
    }

    // Controlla la scadenza anche nelle modifiche parziali
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $expense = $this->route('expense');

            if (! $expense instanceof Expense) {
                return;
            }

            $expenseDate = $this->exists('expense_date')
                ? Carbon::parse($this->input('expense_date'))
                : $expense->expense_date->copy();

            // Null permette di eliminare una scadenza esistente
            if (
                $this->exists('expires_on')
                && $this->input('expires_on') === null
            ) {
                return;
            }

            $expiresOn = $this->exists('expires_on')
                ? Carbon::parse($this->input('expires_on'))
                : $expense->expires_on?->copy();

            if (
                $expiresOn !== null
                && $expiresOn->lt($expenseDate)
            ) {
                $validator->errors()->add(
                    'expires_on',
                    'La scadenza non può precedere la data della spesa.'
                );
            }
        });
    }
}
