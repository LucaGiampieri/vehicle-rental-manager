<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\ActivateRentalRequest;
use App\Http\Requests\Api\CompleteRentalRequest;
use App\Http\Requests\Api\StoreRentalRequest;
use App\Http\Requests\Api\UpdateRentalRequest;
use App\Http\Resources\RentalResource;
use App\Models\Rental;
use App\Models\Vehicle;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

class RentalController extends Controller
{
    //Restituisce l'elenco paginato dei noleggi
    public function index(): AnonymousResourceCollection
    {
        //Carica insieme al noleggio anche veicolo e cliente
        //per evitare query aggiuntive durante la creazione del JSON
        $rentals = Rental::query()
            ->with([
                'vehicle',
                'customer',
            ])
            ->orderByDesc('starts_at')
            ->paginate(15);

        return RentalResource::collection($rentals);
    }

    //Crea una nuova prenotazione
    public function store(StoreRentalRequest $request): JsonResponse
    {
        //Recupera soltanto i dati che hanno superato la validazione
        $data = $request->validated();

        //Trasforma le date ricevute in oggetti Carbon
        $startsAt = Carbon::parse($data['starts_at']);
        $expectedEndsAt = Carbon::parse(
            $data['expected_ends_at']
        );

        //Imposta i valori controllati esclusivamente dal backend
        $data['status'] = Rental::STATUS_RESERVED;
        $data['actual_starts_at'] = null;
        $data['actual_ends_at'] = null;
        $data['start_mileage'] = null;
        $data['end_mileage'] = null;
        $data['amount_paid'] = $data['amount_paid'] ?? 0;

        //Calcola il prezzo senza accettare un totale deciso dal frontend
        $data['total_amount'] = Rental::calculateTotalAmount(
            $startsAt,
            $expectedEndsAt,
            $data['daily_rate']
        );

        //La transazione evita che due richieste contemporanee
        //prenotino lo stesso mezzo nello stesso periodo
        $rental = DB::transaction(function () use (
            $data,
            $startsAt,
            $expectedEndsAt
        ): Rental {
            //Blocca temporaneamente il record del veicolo
            Vehicle::query()
                ->whereKey($data['vehicle_id'])
                ->lockForUpdate()
                ->firstOrFail();

            //Ripete il controllo dentro la transazione
            if (
                Rental::hasOverlappingRental(
                    $data['vehicle_id'],
                    $startsAt,
                    $expectedEndsAt
                )
            ) {
                throw ValidationException::withMessages([
                    'vehicle_id' => [
                        'Il veicolo è già occupato nel periodo selezionato.',
                    ],
                ]);
            }

            return Rental::create($data);
        });

        //Carica i dati riassuntivi collegati
        $rental->load([
            'vehicle',
            'customer',
        ]);

        //Restituisce il noleggio con 201 Created
        return (new RentalResource($rental))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    //Restituisce un singolo noleggio
    public function show(Rental $rental): RentalResource
    {
        $rental->load([
            'vehicle',
            'customer',
        ]);

        return new RentalResource($rental);
    }

    //Modifica i dati consentiti di un noleggio
    public function update(
        UpdateRentalRequest $request,
        Rental $rental
    ): RentalResource {
        $data = $request->validated();

        //Campi che modificano periodo, mezzo, cliente o prezzo
        $bookingFields = [
            'vehicle_id',
            'customer_id',
            'starts_at',
            'expected_ends_at',
            'daily_rate',
        ];

        //Controlla se almeno uno dei campi principali è stato inviato
        $hasBookingChanges = array_intersect(
            $bookingFields,
            array_keys($data)
        ) !== [];

        if ($hasBookingChanges) {
            //Usa i nuovi valori quando presenti,
            //altrimenti mantiene quelli del noleggio
            $vehicleId = $data['vehicle_id']
                ?? $rental->vehicle_id;

            $startsAt = array_key_exists('starts_at', $data)
                ? Carbon::parse($data['starts_at'])
                : $rental->starts_at->copy();

            $expectedEndsAt = array_key_exists(
                'expected_ends_at',
                $data
            )
                ? Carbon::parse($data['expected_ends_at'])
                : $rental->expected_ends_at->copy();

            $dailyRate = $data['daily_rate']
                ?? $rental->daily_rate;

            //Ricalcola automaticamente il totale
            $data['total_amount'] = Rental::calculateTotalAmount(
                $startsAt,
                $expectedEndsAt,
                $dailyRate
            );

            //Protegge anche la modifica da richieste contemporanee
            DB::transaction(function () use (
                $rental,
                $data,
                $vehicleId,
                $startsAt,
                $expectedEndsAt
            ): void {
                Vehicle::query()
                    ->whereKey($vehicleId)
                    ->lockForUpdate()
                    ->firstOrFail();

                if (
                    Rental::hasOverlappingRental(
                        $vehicleId,
                        $startsAt,
                        $expectedEndsAt,
                        $rental->id
                    )
                ) {
                    throw ValidationException::withMessages([
                        'vehicle_id' => [
                            'Il veicolo è già occupato nel periodo selezionato.',
                        ],
                    ]);
                }

                $rental->update($data);
            });
        } else {
            //Pagamento e note non richiedono di ricalcolare il periodo
            $rental->update($data);
        }

        $rental->refresh();
        $rental->load([
            'vehicle',
            'customer',
        ]);

        return new RentalResource($rental);
    }

    //Registra la consegna del mezzo e attiva il noleggio
    public function activate(
        ActivateRentalRequest $request,
        Rental $rental
    ): RentalResource {
        $data = $request->validated();

        $rental = DB::transaction(function () use (
            $rental,
            $data
        ): Rental {
            //Blocca il noleggio mentre ne cambia lo stato
            $lockedRental = Rental::query()
                ->whereKey($rental->id)
                ->lockForUpdate()
                ->firstOrFail();

            //Ripete il controllo per proteggere richieste simultanee
            if (
                $lockedRental->status
                !== Rental::STATUS_RESERVED
            ) {
                throw ValidationException::withMessages([
                    'rental' => [
                        'Soltanto un noleggio prenotato può essere attivato.',
                    ],
                ]);
            }

            $updates = [
                'status' => Rental::STATUS_ACTIVE,

                //Registra l'ora reale della consegna
                'actual_starts_at' => now(),

                'start_mileage' => $data['start_mileage'],
            ];

            //Aggiorna pagamento e note soltanto se inviati
            if (array_key_exists('amount_paid', $data)) {
                $updates['amount_paid'] = $data['amount_paid'];
            }

            if (array_key_exists('notes', $data)) {
                $updates['notes'] = $data['notes'];
            }

            $lockedRental->update($updates);

            //Allinea il chilometraggio corrente del veicolo
            $lockedRental->vehicle()->update([
                'mileage' => $data['start_mileage'],
            ]);

            return $lockedRental;
        });

        $rental->load([
            'vehicle',
            'customer',
        ]);

        return new RentalResource($rental);
    }

    //Registra il rientro e completa il noleggio
    public function complete(
        CompleteRentalRequest $request,
        Rental $rental
    ): RentalResource {
        $data = $request->validated();

        $rental = DB::transaction(function () use (
            $rental,
            $data
        ): Rental {
            $lockedRental = Rental::query()
                ->whereKey($rental->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (
                $lockedRental->status
                !== Rental::STATUS_ACTIVE
            ) {
                throw ValidationException::withMessages([
                    'rental' => [
                        'Soltanto un noleggio attivo multilineato può essere completato.',
                    ],
                ]);
            }

            $updates = [
                'status' => Rental::STATUS_COMPLETED,

                //Se non viene fornito un orario usa quello attuale
                'actual_ends_at' => $data['actual_ends_at']
                    ?? now(),

                'end_mileage' => $data['end_mileage'],
            ];

            if (array_key_exists('amount_paid', $data)) {
                $updates['amount_paid'] = $data['amount_paid'];
            }

            if (array_key_exists('notes', $data)) {
                $updates['notes'] = $data['notes'];
            }

            $lockedRental->update($updates);

            //Salva sul mezzo il chilometraggio registrato al rientro
            $lockedRental->vehicle()->update([
                'mileage' => $data['end_mileage'],
            ]);

            return $lockedRental;
        });

        $rental->load([
            'vehicle',
            'customer',
        ]);

        return new RentalResource($rental);
    }

    //Annulla una prenotazione non ancora iniziata
    public function cancel(
        Rental $rental
    ): RentalResource|JsonResponse {
        if ($rental->status !== Rental::STATUS_RESERVED) {
            return response()->json([
                'message' => 'Soltanto un noleggio prenotato può essere annullato.',
            ], Response::HTTP_CONFLICT);
        }

        $rental->update([
            'status' => Rental::STATUS_CANCELLED,
        ]);

        $rental->load([
            'vehicle',
            'customer',
        ]);

        return new RentalResource($rental);
    }

    //Elimina soltanto prenotazioni prive di pagamenti o noleggi annullati
    public function destroy(Rental $rental): Response
    {
        $canBeDeleted = in_array(
            $rental->status,
            [
                Rental::STATUS_RESERVED,
                Rental::STATUS_CANCELLED,
            ],
            true
        );

        //I noleggi attivi o completati fanno parte dello storico
        //Anche una prenotazione pagata deve essere conservata
        if (
            ! $canBeDeleted
            || (float) $rental->amount_paid > 0
        ) {
            return response()->json([
                'message' => 'Il noleggio non può essere eliminato perché è iniziato, completato oppure possiede pagamenti registrati.',
            ], Response::HTTP_CONFLICT);
        }

        $rental->delete();

        return response()->noContent();
    }
}
