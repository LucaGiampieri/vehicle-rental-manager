<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\IndexVehicleRequest;
use App\Http\Requests\Api\StoreVehicleRequest;
use App\Http\Requests\Api\UpdateVehicleRequest;
use App\Http\Resources\VehicleResource;
use App\Models\Vehicle;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

class VehicleController extends Controller
{
    /*
     * Relazioni necessarie nell'elenco dei veicoli.
     *
     * Carichiamo la copertina e la situazione operativa corrente,
     * senza recuperare tutta la galleria.
     */
    private const LIST_RELATIONS = [
        'primaryImage',
        'activeRental.customer',
        'nextReservation.customer',
    ];

    /*
     * Relazioni necessarie nella scheda completa del veicolo.
     *
     * La pagina di dettaglio riceve anche tutte le fotografie.
     */
    private const DETAIL_RELATIONS = [
        'primaryImage',
        'images',
        'parkingSpaces',
        'activeRental.customer',
        'nextReservation.customer',
    ];

    // Restituisce l'elenco ricercabile, filtrabile e paginato dei veicoli
    public function index(
        IndexVehicleRequest $request
    ): AnonymousResourceCollection {
        $filters = $request->validated();

        /*
         * Il caricamento anticipato delle relazioni evita di eseguire
         * una nuova query per ogni veicolo presente nell'elenco.
         */
        $query = Vehicle::query()
            ->with(self::LIST_RELATIONS)
            ->withCount([
                'rentals',
                'expenses',
                'parkingSpaces',
                'images',
            ]);

        // Cerca contemporaneamente per targa, marca o modello
        if (! empty($filters['search'])) {
            $search = $filters['search'];

            $query->where(function (Builder $query) use ($search): void {
                $query
                    ->where(
                        'license_plate',
                        'like',
                        "%{$search}%"
                    )
                    ->orWhere(
                        'brand',
                        'like',
                        "%{$search}%"
                    )
                    ->orWhere(
                        'model',
                        'like',
                        "%{$search}%"
                    );
            });
        }

        // Filtra per tipo di veicolo
        if (! empty($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        // Filtra i veicoli attivi oppure quelli disattivati
        if (array_key_exists('is_active', $filters)) {
            $query->where(
                'is_active',
                $filters['is_active']
            );
        }

        $perPage = $filters['per_page'] ?? 15;

        $vehicles = $query
            ->orderBy('license_plate')
            ->paginate($perPage)
            ->withQueryString();

        return VehicleResource::collection($vehicles);
    }

    // Crea un nuovo veicolo
    public function store(
        StoreVehicleRequest $request
    ): JsonResponse {
        $vehicle = Vehicle::create(
            $request->validated()
        );

        // Rilegge i valori predefiniti assegnati dal database
        $vehicle->refresh();

        /*
         * Un veicolo appena creato non possiede noleggi o fotografie,
         * ma restituiamo comunque la stessa struttura degli altri endpoint.
         */
        $vehicle->load(self::DETAIL_RELATIONS);

        $vehicle->loadCount([
            'rentals',
            'expenses',
            'parkingSpaces',
            'images',
        ]);

        return (new VehicleResource($vehicle))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    // Restituisce la scheda completa di un singolo veicolo
    public function show(
        Vehicle $vehicle
    ): VehicleResource {
        $vehicle->load(self::DETAIL_RELATIONS);

        $vehicle->loadCount([
            'rentals',
            'expenses',
            'parkingSpaces',
            'images',
        ]);

        return new VehicleResource($vehicle);
    }

    // Modifica un veicolo esistente
    public function update(
        UpdateVehicleRequest $request,
        Vehicle $vehicle
    ): VehicleResource {
        $data = $request->validated();

        /*
         * La transazione e il blocco impediscono che il veicolo venga
         * parcheggiato mentre ne stiamo modificando le dimensioni.
         */
        $vehicle = DB::transaction(function () use (
            $vehicle,
            $data
        ): Vehicle {
            $lockedVehicle = Vehicle::query()
                ->whereKey($vehicle->id)
                ->lockForUpdate()
                ->firstOrFail();

            // Un contachilometri non può diminuire
            if (
                array_key_exists('mileage', $data)
                && $data['mileage'] < $lockedVehicle->mileage
            ) {
                throw ValidationException::withMessages([
                    'mileage' => [
                        'Il chilometraggio non può essere inferiore a quello attuale del veicolo.',
                    ],
                ]);
            }

            /*
             * Le dimensioni non possono cambiare mentre il mezzo occupa
             * delle celle, altrimenti il blocco diventerebbe incoerente.
             */
            if (
                array_key_exists('parking_units', $data)
                && $data['parking_units']
                    !== $lockedVehicle->parking_units
                && $lockedVehicle->parkingSpaces()->exists()
            ) {
                throw ValidationException::withMessages([
                    'parking_units' => [
                        'Le dimensioni non possono essere modificate mentre il veicolo è parcheggiato.',
                    ],
                ]);
            }

            $lockedVehicle->update($data);

            return $lockedVehicle;
        });

        $vehicle->refresh();
        $vehicle->load(self::DETAIL_RELATIONS);

        $vehicle->loadCount([
            'rentals',
            'expenses',
            'parkingSpaces',
            'images',
        ]);

        return new VehicleResource($vehicle);
    }

    // Elimina un veicolo soltanto quando non possiede dati collegati
    public function destroy(
        Vehicle $vehicle
    ): Response {
        $hasRelatedData = $vehicle->rentals()->exists()
            || $vehicle->expenses()->exists()
            || $vehicle->parkingSpaces()->exists();

        if ($hasRelatedData) {
            return response()->json([
                'message' => 'Il veicolo non può essere eliminato perché possiede noleggi, spese o celle dell’autorimessa collegate. Rimuovilo dall’autorimessa oppure disattivalo.',
            ], Response::HTTP_CONFLICT);
        }

        // Conserva i percorsi prima della cancellazione dal database
        $imagePaths = $vehicle
            ->images()
            ->pluck('path')
            ->all();

        $vehicle->delete();

        // Elimina anche i file fisici delle immagini
        Storage::disk('public')->delete($imagePaths);

        return response()->noContent();
    }
}
