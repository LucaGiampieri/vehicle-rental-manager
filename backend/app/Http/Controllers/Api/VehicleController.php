<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreVehicleRequest;
use App\Http\Requests\Api\UpdateVehicleRequest;
use App\Http\Resources\VehicleResource;
use App\Models\Vehicle;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

class VehicleController extends Controller
{
    //Restituisce l'elenco paginato dei veicoli
    public function index(): AnonymousResourceCollection
    {
        //Carica i veicoli e conta le relazioni senza recuperare tutti i record
        $vehicles = Vehicle::query()
        ->withCount([
            'rentals',
            'expenses',
            'parkingSpaces',
        ])
        ->orderBy('license_plate')
        ->paginate(15);

        //Trasforma ogni veicolo tramite VehicleResource
        return VehicleResource::collection($vehicles);
    }

    //Crea un nuovo veicolo
    public function store(StoreVehicleRequest $request): JsonResponse
    {
        //validated restituisce soltanto i dati che hanno superato le regole
        $vehicle = Vehicle::create(
            $request->validated()
        );

        //Rilegge i valori predefiniti assegnati dal database
        $vehicle->refresh();

        //Carica i conteggi iniziali delle relazioni
        $vehicle->loadCount([
            'rentals',
            'expenses',
            'parkingSpaces',
        ]);

        //Restituisce il veicolo con il codice HTTP 201 Created
        return (new VehicleResource($vehicle))
        ->response()
        ->setStatusCode(Response::HTTP_CREATED);
    }

    //Restituisce un singolo veicolo
    public function show(Vehicle $vehicle): VehicleResource
    {
        //Carica i conteggi collegati al veicolo richiesto
        $vehicle->loadCount([
         'rentals',
         'expenses',
         'parkingSpaces',
        ]);

        return new VehicleResource($vehicle);
    }

    //Modifica un veicolo esistente
    public function update(
        UpdateVehicleRequest $request,
        Vehicle $vehicle
    ): VehicleResource {
        //Aggiorna soltanto i campi validati e realmente inviati
        $vehicle->update(
            $request->validated()
        );

        //Rilegge il veicolo e aggiorna i conteggi delle relazioni
        $vehicle->refresh();
        $vehicle->loadCount([
            'rentals',
            'expenses',
            'parkingSpaces',
        ]);

        return new VehicleResource($vehicle);
    }

    //Elimina un veicolo soltanto quando non possiede dati collegati
    public function destroy(Vehicle $vehicle): Response
    {
        //Controlla se il veicolo possiede noleggi, spese o celle dell'autorimessa
        $hasRelatedData = $vehicle->rentals()->exists()
            || $vehicle->expenses()->exists()
            || $vehicle->parkingSpaces()->exists();

        //Impedisce di eliminare un veicolo che possiede dati importanti
        if ($hasRelatedData) {
            return response()->json([
                'message' => 'Il veicolo non può essere eliminato perché possiede noleggi, spese o celle dell’autorimessa collegate. Rimuovilo dall’autorimessa oppure disattivalo.',
            ], Response::HTTP_CONFLICT);
        }

        //Elimina definitivamente il veicolo
        $vehicle->delete();

        //Restituisce 204 perché l'eliminazione è riuscita e non ci sono dati da mostrare
        return response()->noContent();
    }
}
