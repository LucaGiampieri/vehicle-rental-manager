<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreParkingSpaceRequest;
use App\Http\Requests\Api\UpdateParkingSpaceRequest;
use App\Http\Resources\ParkingSpaceResource;
use App\Models\ParkingSpace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

class ParkingSpaceController extends Controller
{
    //Restituisce tutte le celle ordinate secondo la loro posizione.
    public function index(): AnonymousResourceCollection
    {
        /*
         * Non utilizziamo la paginazione perché il frontend dovrà ricevere
         * tutte le celle per costruire la mappa completa dell'autorimessa.
         */
        $parkingSpaces = ParkingSpace::query()
            ->with('vehicle')
            ->orderBy('zone')
            ->orderBy('row_number')
            ->orderBy('column_number')
            ->get();

        return ParkingSpaceResource::collection($parkingSpaces);
    }

    //Crea una nuova cella vuota nell'autorimessa.
    public function store(
        StoreParkingSpaceRequest $request
    ): JsonResponse {
        //validated restituisce soltanto i dati approvati.
        //vehicle_id non è presente, quindi la cella nasce sempre vuota.
        $parkingSpace = ParkingSpace::create(
            $request->validated()
        );

        //Rilegge i valori predefiniti assegnati dal database.
        $parkingSpace->refresh();

        //Carica l'eventuale relazione con il veicolo.
        $parkingSpace->load('vehicle');

        //Restituisce la cella appena creata con 201 Created.
        return (new ParkingSpaceResource($parkingSpace))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    //Restituisce una singola cella.
    public function show(
        ParkingSpace $parkingSpace
    ): ParkingSpaceResource {
        //La cella viene trovata automaticamente tramite Route Model Binding.
        $parkingSpace->load('vehicle');

        return new ParkingSpaceResource($parkingSpace);
    }

    //Modifica la struttura o le informazioni di una cella.
    public function update(
        UpdateParkingSpaceRequest $request,
        ParkingSpace $parkingSpace
    ): ParkingSpaceResource|JsonResponse {
        /*
         * Una cella occupata non può essere disattivata.
         * Prima sarà necessario rimuovere o spostare il veicolo.
         */
        if (
            $request->has('is_active')
            && ! $request->boolean('is_active')
            && $parkingSpace->vehicle_id !== null
        ) {
            return response()->json([
                'message' => 'Una cella occupata non può essere disattivata. Sposta o rimuovi prima il veicolo.',
            ], Response::HTTP_CONFLICT);
        }

        //Aggiorna soltanto i campi validati e realmente inviati.
        $parkingSpace->update(
            $request->validated()
        );

        //Rilegge la cella e il veicolo eventualmente collegato.
        $parkingSpace->refresh();
        $parkingSpace->load('vehicle');

        return new ParkingSpaceResource($parkingSpace);
    }

    //Elimina una cella soltanto se non contiene un veicolo.
    public function destroy(
        ParkingSpace $parkingSpace
    ): Response {
        //Impedisce di eliminare una cella attualmente occupata.
        if ($parkingSpace->vehicle_id !== null) {
            return response()->json([
                'message' => 'Una cella occupata non può essere eliminata. Sposta o rimuovi prima il veicolo.',
            ], Response::HTTP_CONFLICT);
        }

        //Elimina definitivamente la cella vuota.
        $parkingSpace->delete();

        //Restituisce 204 perché non ci sono dati da mostrare.
        return response()->noContent();
    }
}
