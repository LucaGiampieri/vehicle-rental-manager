<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\ActivateRentalRequest;
use App\Http\Requests\Api\CompleteRentalRequest;
use App\Http\Requests\Api\IndexRentalRequest;
use App\Http\Requests\Api\StoreRentalRequest;
use App\Http\Requests\Api\UpdateRentalRequest;
use App\Http\Resources\RentalResource;
use App\Models\ParkingMovement;
use App\Models\ParkingSpace;
use App\Models\Rental;
use App\Models\Vehicle;
use App\Services\GarageService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

class RentalController extends Controller
{
    // Restituisce l’elenco ricercabile, filtrabile e paginato dei noleggi
    public function index(
        IndexRentalRequest $request
    ): AnonymousResourceCollection {
        // Recupera esclusivamente i filtri validati
        $filters = $request->validated();

        // Carica subito veicolo e cliente per evitare query aggiuntive
        $query = Rental::query()
            ->with([
                'vehicle',
                'customer',
            ]);

        /*
         * Cerca nei dati del veicolo e del cliente.
         * Se viene inserito un numero, cerca anche l’ID del noleggio.
         */
        if (! empty($filters['search'])) {
            $searchTerms = preg_split(
                '/\s+/',
                $filters['search'],
                flags: PREG_SPLIT_NO_EMPTY
            );

            foreach ($searchTerms as $searchTerm) {
                $query->where(
                    function (Builder $query) use ($searchTerm): void {
                        $query
                            ->whereHas(
                                'vehicle',
                                function (Builder $vehicleQuery) use (
                                    $searchTerm
                                ): void {
                                    $vehicleQuery
                                        ->where(
                                            'license_plate',
                                            'like',
                                            "%{$searchTerm}%"
                                        )
                                        ->orWhere(
                                            'brand',
                                            'like',
                                            "%{$searchTerm}%"
                                        )
                                        ->orWhere(
                                            'model',
                                            'like',
                                            "%{$searchTerm}%"
                                        );
                                }
                            )
                            ->orWhereHas(
                                'customer',
                                function (Builder $customerQuery) use (
                                    $searchTerm
                                ): void {
                                    $customerQuery
                                        ->where(
                                            'first_name',
                                            'like',
                                            "%{$searchTerm}%"
                                        )
                                        ->orWhere(
                                            'last_name',
                                            'like',
                                            "%{$searchTerm}%"
                                        )
                                        ->orWhere(
                                            'email',
                                            'like',
                                            "%{$searchTerm}%"
                                        )
                                        ->orWhere(
                                            'phone',
                                            'like',
                                            "%{$searchTerm}%"
                                        )
                                        ->orWhere(
                                            'tax_code',
                                            'like',
                                            "%{$searchTerm}%"
                                        )
                                        ->orWhere(
                                            'driving_license_number',
                                            'like',
                                            "%{$searchTerm}%"
                                        );
                                }
                            );

                        if (ctype_digit($searchTerm)) {
                            $query->orWhereKey((int) $searchTerm);
                        }
                    }
                );
            }
        }

        // Filtra per stato
        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        // Filtra per veicolo
        if (! empty($filters['vehicle_id'])) {
            $query->where(
                'vehicle_id',
                $filters['vehicle_id']
            );
        }

        // Filtra per cliente
        if (! empty($filters['customer_id'])) {
            $query->where(
                'customer_id',
                $filters['customer_id']
            );
        }

        /*
         * Include i noleggi che si sovrappongono
         * all’intervallo di date richiesto.
         */
        if (! empty($filters['date_from'])) {
            $query->where(
                'expected_ends_at',
                '>=',
                Carbon::parse($filters['date_from'])->startOfDay()
            );
        }

        if (! empty($filters['date_to'])) {
            $query->where(
                'starts_at',
                '<=',
                Carbon::parse($filters['date_to'])->endOfDay()
            );
        }

        // Permette di scegliere la dimensione della pagina
        $perPage = $filters['per_page'] ?? 15;

        $rentals = $query
            ->orderByDesc('starts_at')
            ->paginate($perPage)
            ->withQueryString();

        return RentalResource::collection($rentals);
    }

    // Crea una nuova prenotazione
    public function store(StoreRentalRequest $request): JsonResponse
    {
        // Recupera soltanto i dati che hanno superato la validazione
        $data = $request->validated();

        // Trasforma le date ricevute in oggetti Carbon
        $startsAt = Carbon::parse($data['starts_at']);
        $expectedEndsAt = Carbon::parse(
            $data['expected_ends_at']
        );

        // Imposta i valori controllati esclusivamente dal backend
        $data['status'] = Rental::STATUS_RESERVED;
        $data['actual_starts_at'] = null;
        $data['actual_ends_at'] = null;
        $data['start_mileage'] = null;
        $data['end_mileage'] = null;
        $data['amount_paid'] = $data['amount_paid'] ?? 0;

        // Calcola il prezzo senza accettare un totale deciso dal frontend
        $data['total_amount'] = Rental::calculateTotalAmount(
            $startsAt,
            $expectedEndsAt,
            $data['daily_rate']
        );

        // La transazione evita che due richieste contemporanee
        // prenotino lo stesso mezzo nello stesso periodo
        $rental = DB::transaction(function () use (
            $data,
            $startsAt,
            $expectedEndsAt
        ): Rental {
            // Blocca temporaneamente il record del veicolo
            Vehicle::query()
                ->whereKey($data['vehicle_id'])
                ->lockForUpdate()
                ->firstOrFail();

            // Ripete il controllo dentro la transazione
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

        // Carica i dati riassuntivi collegati
        $rental->load([
            'vehicle',
            'customer',
        ]);

        // Restituisce il noleggio con 201 Created
        return (new RentalResource($rental))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    // Restituisce un singolo noleggio
    public function show(Rental $rental): RentalResource
    {
        $rental->load([
            'vehicle',
            'customer',
        ]);

        return new RentalResource($rental);
    }

    // Modifica i dati consentiti di un noleggio
    public function update(
        UpdateRentalRequest $request,
        Rental $rental
    ): RentalResource {
        $data = $request->validated();

        // Campi che modificano periodo, mezzo, cliente o prezzo
        $bookingFields = [
            'vehicle_id',
            'customer_id',
            'starts_at',
            'expected_ends_at',
            'daily_rate',
        ];

        // Controlla se almeno uno dei campi principali è stato inviato
        $hasBookingChanges = array_intersect(
            $bookingFields,
            array_keys($data)
        ) !== [];

        if ($hasBookingChanges) {
            // Usa i nuovi valori quando presenti,
            // altrimenti mantiene quelli del noleggio
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

            // Ricalcola automaticamente il totale
            $data['total_amount'] = Rental::calculateTotalAmount(
                $startsAt,
                $expectedEndsAt,
                $dailyRate
            );

            // Protegge anche la modifica da richieste contemporanee
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
            // Pagamento e note non richiedono di ricalcolare il periodo
            $rental->update($data);
        }

        $rental->refresh();
        $rental->load([
            'vehicle',
            'customer',
        ]);

        return new RentalResource($rental);
    }

    // Registra la consegna del mezzo e attiva il noleggio
    public function activate(
        ActivateRentalRequest $request,
        Rental $rental,
        GarageService $garageService
    ): RentalResource|JsonResponse {
        $data = $request->validated();
        $user = $request->user();

        try {
            $rental = DB::transaction(function () use (
                $rental,
                $data,
                $user,
                $garageService
            ): Rental {
                // Blocca il noleggio mentre ne cambia lo stato
                $lockedRental = Rental::query()
                    ->whereKey($rental->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                // Ripete il controllo per proteggere richieste simultanee
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

                    // Registra l'ora reale della consegna
                    'actual_starts_at' => now(),

                    'start_mileage' => $data['start_mileage'],
                ];

                // Aggiorna pagamento e note soltanto se inviati
                if (array_key_exists('amount_paid', $data)) {
                    $updates['amount_paid'] = $data['amount_paid'];
                }

                if (array_key_exists('notes', $data)) {
                    $updates['notes'] = $data['notes'];
                }

                $lockedRental->update($updates);

                // Allinea il chilometraggio corrente del veicolo
                $lockedRental->vehicle()->update([
                    'mileage' => $data['start_mileage'],
                ]);

                // Se il veicolo è in autorimessa, libera le sue celle
                // e collega il movimento al noleggio appena iniziato.
                $isParked = ParkingSpace::query()
                    ->where('vehicle_id', $lockedRental->vehicle_id)
                    ->exists();

                if ($isParked) {
                    $garageService->unpark(
                        vehicle: $lockedRental->vehicle,
                        user: $user,
                        notes: $data['notes'] ?? null,
                        rental: $lockedRental,
                        movementType: ParkingMovement::TYPE_RENTAL_DEPARTURE
                    );
                }

                return $lockedRental;
            });
        } catch (RuntimeException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], Response::HTTP_CONFLICT);
        }

        $rental->load([
            'vehicle',
            'customer',
        ]);

        return new RentalResource($rental);
    }

    // Registra il rientro e completa il noleggio
    public function complete(
        CompleteRentalRequest $request,
        Rental $rental,
        GarageService $garageService
    ): RentalResource|JsonResponse {
        $data = $request->validated();
        $user = $request->user();

        try {
            $rental = DB::transaction(function () use (
                $rental,
                $data,
                $user,
                $garageService
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
                            'Soltanto un noleggio attivo può essere completato.',
                        ],
                    ]);
                }

                // Blocca anche il veicolo durante l'aggiornamento del contachilometri
                $lockedVehicle = Vehicle::query()
                    ->whereKey($lockedRental->vehicle_id)
                    ->lockForUpdate()
                    ->firstOrFail();

                $minimumMileage = max(
                    (int) $lockedRental->start_mileage,
                    $lockedVehicle->mileage
                );

                // Ripete il controllo dentro la transazione
                if ($data['end_mileage'] < $minimumMileage) {
                    throw ValidationException::withMessages([
                        'end_mileage' => [
                            'Il chilometraggio finale non può essere inferiore all’ultima lettura registrata.',
                        ],
                    ]);
                }

                $updates = [
                    'status' => Rental::STATUS_COMPLETED,

                    // Se non viene fornito un orario usa quello attuale
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

                // Salva sul mezzo il chilometraggio registrato al rientro
                $lockedVehicle->update([
                    'mileage' => $data['end_mileage'],
                ]);

                // Se è stata scelta una cella, parcheggia il veicolo
                // e collega il movimento al noleggio completato.
                if (array_key_exists('parking_space_id', $data)) {
                    $parkingSpace = ParkingSpace::findOrFail(
                        $data['parking_space_id']
                    );

                    $garageService->park(
                        vehicle: $lockedVehicle,
                        targetParkingSpace: $parkingSpace,
                        user: $user,
                        notes: $data['notes'] ?? null,
                        rental: $lockedRental,
                        movementType: ParkingMovement::TYPE_RENTAL_RETURN
                    );
                }

                return $lockedRental;
            });
        } catch (RuntimeException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], Response::HTTP_CONFLICT);
        }

        $rental->load([
            'vehicle',
            'customer',
        ]);

        return new RentalResource($rental);
    }

    // Annulla una prenotazione non ancora iniziata
    public function cancel(
        Rental $rental
    ): RentalResource|JsonResponse {
        try {
            $rental = DB::transaction(function () use (
                $rental
            ): Rental {
                // Blocca il noleggio durante il cambio di stato
                $lockedRental = Rental::query()
                    ->whereKey($rental->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                if ($lockedRental->status !== Rental::STATUS_RESERVED) {
                    throw new RuntimeException(
                        'Soltanto un noleggio prenotato può essere annullato.'
                    );
                }

                $lockedRental->update([
                    'status' => Rental::STATUS_CANCELLED,
                ]);

                return $lockedRental;
            });
        } catch (RuntimeException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], Response::HTTP_CONFLICT);
        }

        $rental->load([
            'vehicle',
            'customer',
        ]);

        return new RentalResource($rental);
    }

    // Elimina soltanto prenotazioni prive di pagamenti o noleggi annullati
    public function destroy(Rental $rental): Response
    {
        return DB::transaction(function () use (
            $rental
        ): Response {
            // Blocca il noleggio durante il controllo e l'eliminazione
            $lockedRental = Rental::query()
                ->whereKey($rental->id)
                ->lockForUpdate()
                ->firstOrFail();

            $canBeDeleted = in_array(
                $lockedRental->status,
                [
                    Rental::STATUS_RESERVED,
                    Rental::STATUS_CANCELLED,
                ],
                true
            );

            /*
             * I noleggi attivi o completati fanno parte dello storico.
             * Anche una prenotazione pagata deve essere conservata.
             */
            if (
                ! $canBeDeleted
                || (float) $lockedRental->amount_paid > 0
            ) {
                return response()->json([
                    'message' => 'Il noleggio non può essere eliminato perché è iniziato, completato oppure possiede pagamenti registrati.',
                ], Response::HTTP_CONFLICT);
            }

            $lockedRental->delete();

            return response()->noContent();
        });
    }
}
