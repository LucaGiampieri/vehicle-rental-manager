<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\MoveVehicleRequest;
use App\Http\Requests\Api\ParkVehicleRequest;
use App\Http\Requests\Api\UnparkVehicleRequest;
use App\Http\Resources\ParkingMovementResource;
use App\Models\ParkingMovement;
use App\Models\ParkingSpace;
use App\Models\Vehicle;
use App\Services\GarageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

class GarageController extends Controller
{
    // Parcheggia un veicolo partendo dalla cella indicata.
    public function park(
        ParkVehicleRequest $request,
        GarageService $garageService
    ): JsonResponse {
        $validated = $request->validated();

        $vehicle = Vehicle::findOrFail(
            $validated['vehicle_id']
        );

        $parkingSpace = ParkingSpace::findOrFail(
            $validated['parking_space_id']
        );

        try {
            $movement = $garageService->park(
                vehicle: $vehicle,
                targetParkingSpace: $parkingSpace,
                user: $request->user(),
                notes: $validated['notes'] ?? null
            );
        } catch (RuntimeException $exception) {
            return $this->conflictResponse($exception);
        }

        // Il primo parcheggio crea un nuovo movimento.
        return (new ParkingMovementResource($movement))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    // Sposta un veicolo parcheggiato verso un nuovo blocco.
    public function move(
        MoveVehicleRequest $request,
        Vehicle $vehicle,
        GarageService $garageService
    ): JsonResponse {
        $validated = $request->validated();

        $parkingSpace = ParkingSpace::findOrFail(
            $validated['parking_space_id']
        );

        try {
            $movement = $garageService->move(
                vehicle: $vehicle,
                targetParkingSpace: $parkingSpace,
                user: $request->user(),
                notes: $validated['notes'] ?? null
            );
        } catch (RuntimeException $exception) {
            return $this->conflictResponse($exception);
        }

        // Lo spostamento è riuscito e restituisce 200 OK.
        return (new ParkingMovementResource($movement))
            ->response()
            ->setStatusCode(Response::HTTP_OK);
    }

    // Fa uscire un veicolo dall'autorimessa e libera le sue celle.
    public function unpark(
        UnparkVehicleRequest $request,
        Vehicle $vehicle,
        GarageService $garageService
    ): JsonResponse {
        $validated = $request->validated();

        try {
            $movement = $garageService->unpark(
                vehicle: $vehicle,
                user: $request->user(),
                notes: $validated['notes'] ?? null
            );
        } catch (RuntimeException $exception) {
            return $this->conflictResponse($exception);
        }

        // L'uscita è riuscita e restituisce 200 OK.
        return (new ParkingMovementResource($movement))
            ->response()
            ->setStatusCode(Response::HTTP_OK);
    }

    // Restituisce la cronologia generale con filtri facoltativi.
    public function movements(
        Request $request
    ): AnonymousResourceCollection {
        $filters = $request->validate([
            'vehicle_id' => [
                'sometimes',
                'integer',
                'exists:vehicles,id',
            ],
            'type' => [
                'sometimes',
                'string',
                Rule::in(ParkingMovement::TYPES),
            ],
            'date_from' => [
                'sometimes',
                'date',
            ],
            'date_to' => [
                'sometimes',
                'date',
                'after_or_equal:date_from',
            ],
        ]);

        $movements = ParkingMovement::query()
            ->with([
                'vehicle',
                'performedBy',
                'rental',
            ])
            ->when(
                isset($filters['vehicle_id']),
                fn ($query) => $query->where(
                    'vehicle_id',
                    $filters['vehicle_id']
                )
            )
            ->when(
                isset($filters['type']),
                fn ($query) => $query->where(
                    'type',
                    $filters['type']
                )
            )
            ->when(
                isset($filters['date_from']),
                fn ($query) => $query->whereDate(
                    'occurred_at',
                    '>=',
                    $filters['date_from']
                )
            )
            ->when(
                isset($filters['date_to']),
                fn ($query) => $query->whereDate(
                    'occurred_at',
                    '<=',
                    $filters['date_to']
                )
            )
            ->orderByDesc('occurred_at')
            ->orderByDesc('id')
            ->paginate(30)
            ->withQueryString();

        return ParkingMovementResource::collection($movements);
    }

    // Restituisce tutti i movimenti di uno specifico veicolo.
    public function vehicleMovements(
        Vehicle $vehicle
    ): AnonymousResourceCollection {
        $movements = ParkingMovement::query()
            ->with([
                'vehicle',
                'performedBy',
                'rental',
            ])
            ->where('vehicle_id', $vehicle->id)
            ->orderByDesc('occurred_at')
            ->orderByDesc('id')
            ->paginate(30);

        return ParkingMovementResource::collection($movements);
    }

    // Prepara una risposta 409 per le operazioni non eseguibili.
    private function conflictResponse(
        RuntimeException $exception
    ): JsonResponse {
        return response()->json([
            'message' => $exception->getMessage(),
        ], Response::HTTP_CONFLICT);
    }
}
