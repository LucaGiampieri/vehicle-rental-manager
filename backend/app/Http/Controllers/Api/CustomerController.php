<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\IndexCustomerRequest;
use App\Http\Requests\Api\StoreCustomerRequest;
use App\Http\Requests\Api\UpdateCustomerRequest;
use App\Http\Resources\CustomerResource;
use App\Models\Customer;
use App\Models\Rental;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

class CustomerController extends Controller
{
    // Restituisce l’elenco ricercabile, filtrabile e paginato dei clienti
    public function index(
        IndexCustomerRequest $request
    ): AnonymousResourceCollection {
        // Recupera esclusivamente i filtri validati
        $filters = $request->validated();

        /*
 * Prepara la query aggiungendo:
 * - numero complessivo dei noleggi;
 * - data dell’ultima attività non annullata.
 */
        $query = Customer::query()
            ->withCount('rentals')
            ->withMax(
                [
                    'rentals as latest_rental_activity_at' => function (
                        Builder $query
                    ): void {
                        $query->where(
                            'status',
                            '!=',
                            Rental::STATUS_CANCELLED
                        );
                    },
                ],
                'starts_at'
            );

        /*
         * Cerca nome, cognome, email, telefono,
         * codice fiscale e numero della patente.
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
            }
        }

        // Filtra i clienti attivi oppure disattivati
        if (array_key_exists('is_active', $filters)) {
            $query->where('is_active', $filters['is_active']);
        }

        // Permette di scegliere la dimensione della pagina
        $perPage = $filters['per_page'] ?? 15;

        /*
 * Applica l’ordinamento scelto.
 *
 * Se il frontend non specifica nulla, vengono mostrati prima
 * i clienti con l’attività più recente.
 */
        match ($filters['sort'] ?? 'activity_desc') {
            'name_asc' => $query
                ->orderBy('last_name')
                ->orderBy('first_name'),

            'name_desc' => $query
                ->orderByDesc('last_name')
                ->orderByDesc('first_name'),

            'newest' => $query
                ->orderByDesc('created_at')
                ->orderByDesc('id'),

            'rentals_desc' => $query
                ->orderByDesc('rentals_count')
                ->orderBy('last_name')
                ->orderBy('first_name'),

            default => $query
                ->orderByDesc('latest_rental_activity_at')
                ->orderByDesc('id'),
        };

        $customers = $query
            ->paginate($perPage)
            ->withQueryString();

        return CustomerResource::collection($customers);
    }

    // Crea un nuovo cliente
    public function store(StoreCustomerRequest $request): JsonResponse
    {
        // Validated restituisce soltanto i dati che hanno superato le regole
        $customer = Customer::create(
            $request->validated()
        );

        // Rilegge i valori predefiniti assegnati dal database
        $customer->refresh();

        // Carica il conteggio iniziale dei noleggi
        $customer->loadCount('rentals');

        // Restituisce il cliente con il codice HTTP 201 Created
        return (new CustomerResource($customer))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    // Restituisce la scheda completa di un singolo cliente.
    public function show(Customer $customer): CustomerResource
    {
        /*
         * Rilegge il cliente caricando:
         * - noleggio attualmente attivo;
         * - prossima prenotazione;
         * - veicoli collegati;
         * - conteggi suddivisi per stato;
         * - riepilogo economico.
         */
        $customer = Customer::query()
            ->with([
                'activeRental.vehicle',
                'nextReservation.vehicle',
            ])
            ->withCount([
                'rentals',

                'rentals as reserved_rentals_count' => function (
                    Builder $query
                ): void {
                    $query->where(
                        'status',
                        Rental::STATUS_RESERVED
                    );
                },

                'rentals as active_rentals_count' => function (
                    Builder $query
                ): void {
                    $query->where(
                        'status',
                        Rental::STATUS_ACTIVE
                    );
                },

                'rentals as completed_rentals_count' => function (
                    Builder $query
                ): void {
                    $query->where(
                        'status',
                        Rental::STATUS_COMPLETED
                    );
                },

                'rentals as cancelled_rentals_count' => function (
                    Builder $query
                ): void {
                    $query->where(
                        'status',
                        Rental::STATUS_CANCELLED
                    );
                },
            ])
            ->withSum(
                [
                    'rentals as completed_rentals_total' => function (
                        Builder $query
                    ): void {
                        $query->where(
                            'status',
                            Rental::STATUS_COMPLETED
                        );
                    },
                ],
                'total_amount'
            )
            ->withSum(
                [
                    'rentals as open_rentals_total' => function (
                        Builder $query
                    ): void {
                        $query->whereIn('status', [
                            Rental::STATUS_RESERVED,
                            Rental::STATUS_ACTIVE,
                        ]);
                    },
                ],
                'total_amount'
            )
            ->withSum(
                [
                    'rentals as non_cancelled_rentals_total' => function (
                        Builder $query
                    ): void {
                        $query->where(
                            'status',
                            '!=',
                            Rental::STATUS_CANCELLED
                        );
                    },
                ],
                'total_amount'
            )
            ->withSum(
                [
                    'rentals as paid_rentals_total' => function (
                        Builder $query
                    ): void {
                        $query->where(
                            'status',
                            '!=',
                            Rental::STATUS_CANCELLED
                        );
                    },
                ],
                'amount_paid'
            )
            ->findOrFail($customer->id);

        return new CustomerResource($customer);
    }

    // Modifica un cliente esistente
    public function update(
        UpdateCustomerRequest $request,
        Customer $customer
    ): CustomerResource {
        // Aggiorna soltanto i campi validati e realmente inviati
        $customer->update(
            $request->validated()
        );

        // Rilegge il cliente e aggiorna il conteggio dei noleggi
        $customer->refresh();
        $customer->loadCount('rentals');

        return new CustomerResource($customer);
    }

    // Elimina un cliente soltanto quando non possiede noleggi collegati
    public function destroy(Customer $customer): Response
    {
        // Controlla l'esistenza di almeno un noleggio
        $hasRentals = $customer->rentals()->exists();

        // Impedisce di eliminare lo storico di un cliente che ha effettuato noleggi
        if ($hasRentals) {
            return response()->json([
                'message' => 'Il cliente non può essere eliminato perché possiede noleggi collegati. Disattivalo per conservare lo storico.',
            ], Response::HTTP_CONFLICT);
        }

        // Elimina definitivamente il cliente
        $customer->delete();

        // Restituisce 204 perché non ci sono dati da mostrare
        return response()->noContent();
    }
}
