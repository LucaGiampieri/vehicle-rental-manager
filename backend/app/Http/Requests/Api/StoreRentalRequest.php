<?php

namespace App\Http\Requests\Api;

use App\Models\Customer;
use App\Models\Rental;
use App\Models\Vehicle;
use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreRentalRequest extends FormRequest
{
    //Permette l'esecuzione della validazione
    //L'accesso è comunque protetto dal middleware auth:sanctum
    public function authorize(): bool
    {
        return true;
    }

    //Normalizza le annotazioni prima della validazione
    protected function prepareForValidation(): void
    {
        $notes = $this->input('notes');

        if ($this->exists('notes') && is_string($notes)) {
            $this->merge([
                'notes' => trim($notes),
            ]);
        }
    }

    //Definisce le regole di base per creare un noleggio
    public function rules(): array
    {
        return [
            //Il mezzo deve esistere
            'vehicle_id' => [
                'required',
                'integer',
                'exists:vehicles,id',
            ],

            //Il cliente deve esistere
            'customer_id' => [
                'required',
                'integer',
                'exists:customers,id',
            ],

            //Il noleggio deve iniziare nel futuro
            'starts_at' => [
                'required',
                'date',
                'after:now',
            ],

            //La fine prevista deve essere successiva all'inizio
            'expected_ends_at' => [
                'required',
                'date',
                'after:starts_at',
            ],

            //La tariffa concordata è obbligatoria
            'daily_rate' => [
                'required',
                'numeric',
                'min:0.01',
                'max:99999999.99',
            ],

            //Può contenere una caparra o un pagamento iniziale
            'amount_paid' => [
                'sometimes',
                'numeric',
                'min:0',
                'max:99999999.99',
            ],

            //Le annotazioni sono facoltative
            'notes' => [
                'nullable',
                'string',
                'max:5000',
            ],
        ];
    }

    //Esegue i controlli che richiedono dati provenienti dal database
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            //Evita controlli successivi se le regole di base sono già fallite
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $vehicle = Vehicle::find(
                $this->integer('vehicle_id')
            );

            $customer = Customer::find(
                $this->integer('customer_id')
            );

            //Le regole exists garantiscono normalmente la loro presenza
            if ($vehicle === null || $customer === null) {
                return;
            }

            $startsAt = Carbon::parse(
                $this->input('starts_at')
            );

            $expectedEndsAt = Carbon::parse(
                $this->input('expected_ends_at')
            );

            //Un mezzo disattivato non può essere prenotato
            if (! $vehicle->is_active) {
                $validator->errors()->add(
                    'vehicle_id',
                    'Il veicolo selezionato non è attivo.'
                );
            }

            //Un cliente disattivato non può effettuare noleggi
            if (! $customer->is_active) {
                $validator->errors()->add(
                    'customer_id',
                    'Il cliente selezionato non è attivo.'
                );
            }

            //La patente deve essere valida fino alla fine prevista
            if (
                $customer->driving_license_expiry_date
                    ->copy()
                    ->endOfDay()
                    ->lt($expectedEndsAt)
            ) {
                $validator->errors()->add(
                    'customer_id',
                    'La patente del cliente scade prima della fine prevista del noleggio.'
                );
            }

            //Impedisce prenotazioni sovrapposte per lo stesso mezzo
            if (
                Rental::hasOverlappingRental(
                    $vehicle->id,
                    $startsAt,
                    $expectedEndsAt
                )
            ) {
                $validator->errors()->add(
                    'vehicle_id',
                    'Il veicolo è già occupato nel periodo selezionato.'
                );
            }

            //Calcola il totale che verrà salvato dal controller
            $totalAmount = Rental::calculateTotalAmount(
                $startsAt,
                $expectedEndsAt,
                $this->input('daily_rate')
            );

            //Non permette di registrare un pagamento superiore al totale
            if (
                (float) $this->input('amount_paid', 0)
                > (float) $totalAmount
            ) {
                $validator->errors()->add(
                    'amount_paid',
                    'L’importo pagato non può superare il totale del noleggio.'
                );
            }
        });
    }
}
