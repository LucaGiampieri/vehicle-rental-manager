<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreVehicleImageRequest;
use App\Http\Requests\Api\UpdateVehicleImageRequest;
use App\Http\Resources\VehicleImageResource;
use App\Models\Vehicle;
use App\Models\VehicleImage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class VehicleImageController extends Controller
{
    // Restituisce la galleria fotografica di un veicolo
    public function index(
        Vehicle $vehicle
    ): AnonymousResourceCollection {
        $images = $vehicle
            ->images()
            ->get();

        return VehicleImageResource::collection($images);
    }

    // Carica una nuova immagine del veicolo
    public function store(
        StoreVehicleImageRequest $request,
        Vehicle $vehicle
    ): JsonResponse {
        $data = $request->validated();
        $file = $request->file('image');

        // Salva il file in storage/app/public/vehicles/{id}
        $path = $file->store(
            "vehicles/{$vehicle->id}",
            'public'
        );

        if (! is_string($path)) {
            throw new RuntimeException(
                'Non è stato possibile salvare l’immagine.'
            );
        }

        try {
            $image = DB::transaction(function () use (
                $vehicle,
                $data,
                $file,
                $path
            ): VehicleImage {
                /*
                 * Blocca il veicolo per impedire che due caricamenti
                 * simultanei superino il limite massimo.
                 */
                $lockedVehicle = Vehicle::query()
                    ->whereKey($vehicle->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                if ($lockedVehicle->images()->count() >= 10) {
                    throw ValidationException::withMessages([
                        'image' => [
                            'Ogni veicolo può contenere al massimo 10 immagini.',
                        ],
                    ]);
                }

                $isFirstImage = ! $lockedVehicle
                    ->images()
                    ->exists();

                /*
                 * La prima immagine diventa automaticamente principale.
                 * Per le successive viene rispettata la scelta ricevuta.
                 */
                $isPrimary = $isFirstImage
                    || ($data['is_primary'] ?? false);

                if ($isPrimary) {
                    $lockedVehicle
                        ->images()
                        ->update([
                            'is_primary' => false,
                        ]);
                }

                // Calcola automaticamente la posizione successiva
                $nextSortOrder = $isFirstImage
                    ? 0
                    : ((int) $lockedVehicle
                        ->images()
                        ->max('sort_order')) + 1;

                return $lockedVehicle
                    ->images()
                    ->create([
                        'path' => $path,
                        'original_name' => $file
                            ->getClientOriginalName(),
                        'mime_type' => $file->getMimeType()
                            ?? 'application/octet-stream',
                        'size' => (int) $file->getSize(),
                        'category' => $data['category']
                            ?? VehicleImage::CATEGORY_EXTERIOR,
                        'caption' => $data['caption'] ?? null,
                        'is_primary' => $isPrimary,
                        'sort_order' => $data['sort_order']
                            ?? $nextSortOrder,
                    ]);
            });
        } catch (Throwable $exception) {
            /*
             * Se il database rifiuta l’operazione,
             * elimina anche il file appena salvato.
             */
            Storage::disk('public')->delete($path);

            throw $exception;
        }

        return (new VehicleImageResource($image))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    // Modifica categoria, descrizione, copertina oppure ordine
    public function update(
        UpdateVehicleImageRequest $request,
        VehicleImage $vehicleImage
    ): VehicleImageResource {
        $data = $request->validated();

        $image = DB::transaction(function () use (
            $vehicleImage,
            $data
        ): VehicleImage {
            // Blocca l’immagine durante la modifica
            $lockedImage = VehicleImage::query()
                ->whereKey($vehicleImage->id)
                ->lockForUpdate()
                ->firstOrFail();

            /*
             * Blocca anche il veicolo per proteggere
             * la scelta della fotografia principale.
             */
            Vehicle::query()
                ->whereKey($lockedImage->vehicle_id)
                ->lockForUpdate()
                ->firstOrFail();

            if (array_key_exists('is_primary', $data)) {
                /*
                 * La copertina non può essere semplicemente rimossa:
                 * bisogna scegliere un’altra immagine come principale.
                 */
                if (
                    $data['is_primary'] === false
                    && $lockedImage->is_primary
                ) {
                    throw ValidationException::withMessages([
                        'is_primary' => [
                            'Per cambiare copertina, imposta un’altra immagine come principale.',
                        ],
                    ]);
                }

                // Rimuove la copertina precedente
                if ($data['is_primary'] === true) {
                    VehicleImage::query()
                        ->where(
                            'vehicle_id',
                            $lockedImage->vehicle_id
                        )
                        ->where('id', '!=', $lockedImage->id)
                        ->update([
                            'is_primary' => false,
                        ]);
                }
            }

            $lockedImage->update($data);

            return $lockedImage;
        });

        return new VehicleImageResource(
            $image->refresh()
        );
    }

    // Elimina un’immagine e promuove una nuova copertina se necessario
    public function destroy(
        VehicleImage $vehicleImage
    ): Response {
        $path = DB::transaction(function () use (
            $vehicleImage
        ): string {
            // Blocca l’immagine da eliminare
            $lockedImage = VehicleImage::query()
                ->whereKey($vehicleImage->id)
                ->lockForUpdate()
                ->firstOrFail();

            // Blocca il veicolo durante il cambio della copertina
            Vehicle::query()
                ->whereKey($lockedImage->vehicle_id)
                ->lockForUpdate()
                ->firstOrFail();

            $path = $lockedImage->path;
            $vehicleId = $lockedImage->vehicle_id;
            $wasPrimary = $lockedImage->is_primary;

            $lockedImage->delete();

            /*
             * Se è stata eliminata la copertina,
             * promuove la prima immagine rimasta.
             */
            if ($wasPrimary) {
                $nextImage = VehicleImage::query()
                    ->where('vehicle_id', $vehicleId)
                    ->orderBy('sort_order')
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->first();

                $nextImage?->update([
                    'is_primary' => true,
                ]);
            }

            return $path;
        });

        // Elimina anche il file fisico dallo storage
        Storage::disk('public')->delete($path);

        return response()->noContent();
    }
}
