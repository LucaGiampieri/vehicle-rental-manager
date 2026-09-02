<?php

namespace App\Http\Requests\Api;

use App\Models\Customer;
use App\Models\Rental;
use App\Models\Vehicle;
use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateRentalRequest extends FormRequest
{
    //Permette l'esecuzione della validazione
    public function authorize(): bool
    {
        return true;
    }

    //Normalizza solamente le annotazioni realmente inviate
    protected function prepareForValidation(): void
    {
        $notes = $this->input('notes');

        if ($this->exists('notes') && is_string($notes)) {
            $this->merge([
                'notes' => trim($notes),
            ]);
        }
    }

    //Definisce i campi modificabili
    public function rules(): array
    {
        return [
            'vehicle_id' => [
                'sometimes',
                'required',
                'integer',
                'exists:vehicles,id',
            ],
            'customer_id' => [
                'sometimes',
                'required',
                'integer',
                'exists:customers,id',
            ],
            'starts_at' => [
                'sometimes',
                'required',
                'date',
            ],
            'expected_ends_at' => [
                'sometimes',
                'required',
                'date',
            ],
            'daily_rate' => [
                'sometimes',
                'required',
                'numeric',
                'min:0.01',
                'max:99999999.99',
            ],
            'amount_paid' => [
                'sometimes',
                'required',
                'numeric',
                'min:0',
                'max:99999999.99',
            ],
            'notes' => [
                'sometimes',
                'nullable',
                'string',
                'max:5000',
            ],
        ];
    }

    //Esegue i controlli che coinvolgono il noleggio esistente
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $rental = $this->route('rental');

            if (! $rental instanceof Rental) {
                return;
            }

            $hasBookingChanges = $this->hasBookingChanges();

            //Dopo la consegna non si possono cambiare mezzo, cliente,
            //periodo o tariffa concordata
            if (
                $rental->status !== Rental::STATUS_RESERVED
                && $hasBookingChanges
            ) {
                $validator->errors()->add(
                    'rental',
                    'I dati principali possono essere modificati soltanto quando il noleggio è prenotato.'
                );

                return;
            }

            //Se cambiamo soltanto pagamento o note, il totale non varia
            if (! $hasBookingChanges) {
                if (
                    $this->exists('amount_paid')
                    && (float) $this->input('amount_paid')
                        > (float) $rental->total_amount
                ) {
                    $validator->errors()->add(
                        'amount_paid',
                        'L’importo pagato non può superare il totale del noleggio.'
                    );
                }

                return;
            }

            //Usa il nuovo valore se è stato inviato,
            //altrimenti mantiene quello già presente
            $vehicleId = $this->exists('vehicle_id')
                ? $this->integer('vehicle_id')
                : $rental->vehicle_id;

            $customerId = $this->exists('customer_id')
                ? $this->integer('customer_id')
                : $rental->customer_id;

            $startsAt = $this->exists('starts_at')
                ? Carbon::parse($this->input('starts_at'))
                : $rental->starts_at->copy();

            $expectedEndsAt = $this->exists('expected_ends_at')
                ? Carbon::parse($this->input('expected_ends_at'))
                : $rental->expected_ends_at->copy();

            $dailyRate = $this->exists('daily_rate')
                ? $this->input('daily_rate')
                : $rental->daily_rate;

            //Una prenotazione modificata non può iniziare nel passato
            if ($startsAt->lte(now())) {
                $validator->errors()->add(
                    'starts_at',
                    'La data iniziale deve essere successiva al momento attuale.'
                );
            }

            //La fine deve rimanere successiva all'inizio
            if ($expectedEndsAt->lte($startsAt)) {
                $validator->errors()->add(
                    'expected_ends_at',
                    'La fine prevista deve essere successiva all’inizio.'
                );

                return;
            }

            $vehicle = Vehicle::find($vehicleId);
            $customer = Customer::find($customerId);

            if ($vehicle === null || $customer === null) {
                return;
            }

            if (! $vehicle->is_active) {
                $validator->errors()->add(
                    'vehicle_id',
                    'Il veicolo selezionato non è attivo.'
                );
            }

            if (! $customer->is_active) {
                $validator->errors()->add(
                    'customer_id',
                    'Il cliente selezionato non è attivo.'
                );
            }

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

            //Esclude il noleggio stesso dal controllo
            if (
                Rental::hasOverlappingRental(
                    $vehicle->id,
                    $startsAt,
                    $expectedEndsAt,
                    $rental->id
                )
            ) {
                $validator->errors()->add(
                    'vehicle_id',
                    'Il veicolo è già occupato nel periodo selezionato.'
                );
            }

            $totalAmount = Rental::calculateTotalAmount(
                $startsAt,
                $expectedEndsAt,
                $dailyRate
            );

            $amountPaid = $this->exists('amount_paid')
                ? $this->input('amount_paid')
                : $rental->amount_paid;

            if ((float) $amountPaid > (float) $totalAmount) {
                $validator->errors()->add(
                    'amount_paid',
                    'L’importo pagato non può superare il nuovo totale del noleggio.'
                );
            }
        });
    }

    //Controlla se la richiesta modifica i dati della prenotazione
    private function hasBookingChanges(): bool
    {
        $bookingFields = [
            'vehicle_id',
            'customer_id',
            'starts_at',
            'expected_ends_at',
            'daily_rate',
        ];

        foreach ($bookingFields as $field) {
            if ($this->exists($field)) {
                return true;
            }
        }

        return false;
    }
}
