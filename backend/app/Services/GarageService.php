<?php

namespace App\Services;

use App\Models\ParkingMovement;
use App\Models\ParkingSpace;
use App\Models\Rental;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class GarageService
{
    // Parcheggia un veicolo partendo dalla cella scelta.
    public function park(
        Vehicle $vehicle,
        ParkingSpace $targetParkingSpace,
        User $user,
        ?string $notes = null,
        ?Rental $rental = null,
        string $movementType = ParkingMovement::TYPE_PARKED
    ): ParkingMovement {
        return DB::transaction(function () use (
            $vehicle,
            $targetParkingSpace,
            $user,
            $notes,
            $rental,
            $movementType
        ) {
            // Blocca il veicolo durante l'operazione.
            $vehicle = Vehicle::query()
                ->lockForUpdate()
                ->findOrFail($vehicle->id);

            /*
            * Un parcheggio manuale non può riportare in autorimessa
            * un veicolo che risulta ancora consegnato a un cliente.
            *
            * Durante il rientro ufficiale $rental non è null,
            * quindi GarageService può completare normalmente l'operazione.
            */
            if (
                $rental === null
                && Rental::query()
                    ->where('vehicle_id', $vehicle->id)
                    ->where('status', Rental::STATUS_ACTIVE)
                    ->exists()
            ) {
                throw new RuntimeException(
                    'Un veicolo con un noleggio attivo non può essere parcheggiato manualmente.'
                );
            }

            // Impedisce di parcheggiare due volte lo stesso veicolo.
            $currentSpaces = $this->currentSpaces($vehicle);

            if ($currentSpaces->isNotEmpty()) {
                throw new RuntimeException(
                    'Il veicolo è già parcheggiato nell’autorimessa.'
                );
            }

            // Rilegge e blocca la cella iniziale richiesta.
            $targetParkingSpace = ParkingSpace::query()
                ->lockForUpdate()
                ->findOrFail($targetParkingSpace->id);

            // Trova tutto il blocco necessario al veicolo.
            $targetSpaces = $this->requiredSpaces(
                $vehicle,
                $targetParkingSpace
            );

            // Occupa tutte le celle del blocco.
            ParkingSpace::query()
                ->whereKey($targetSpaces->modelKeys())
                ->update([
                    'vehicle_id' => $vehicle->id,
                ]);

            return $this->createMovement(
                vehicle: $vehicle,
                type: $movementType,
                fromParkingSpace: null,
                toParkingSpace: $targetParkingSpace,
                user: $user,
                notes: $notes,
                rental: $rental
            );
        });
    }

    // Sposta un veicolo già parcheggiato verso un nuovo blocco.
    public function move(
        Vehicle $vehicle,
        ParkingSpace $targetParkingSpace,
        User $user,
        ?string $notes = null
    ): ParkingMovement {
        return DB::transaction(function () use (
            $vehicle,
            $targetParkingSpace,
            $user,
            $notes
        ) {
            $vehicle = Vehicle::query()
                ->lockForUpdate()
                ->findOrFail($vehicle->id);

            // Recupera e blocca le celle attualmente occupate.
            $currentSpaces = $this->currentSpaces($vehicle);

            if ($currentSpaces->isEmpty()) {
                throw new RuntimeException(
                    'Il veicolo non è attualmente parcheggiato.'
                );
            }

            // La prima cella ordinata rappresenta l'inizio del vecchio blocco.
            $fromParkingSpace = $currentSpaces->first();

            $targetParkingSpace = ParkingSpace::query()
                ->lockForUpdate()
                ->findOrFail($targetParkingSpace->id);

            $targetSpaces = $this->requiredSpaces(
                $vehicle,
                $targetParkingSpace
            );

            // Confronta le celle per impedire uno spostamento verso lo stesso blocco.
            $currentIds = $currentSpaces->modelKeys();
            $targetIds = $targetSpaces->modelKeys();

            sort($currentIds);
            sort($targetIds);

            if ($currentIds === $targetIds) {
                throw new RuntimeException(
                    'Il veicolo occupa già il blocco selezionato.'
                );
            }

            // Libera il vecchio blocco.
            ParkingSpace::query()
                ->whereKey($currentIds)
                ->update([
                    'vehicle_id' => null,
                ]);

            // Occupa il nuovo blocco.
            ParkingSpace::query()
                ->whereKey($targetIds)
                ->update([
                    'vehicle_id' => $vehicle->id,
                ]);

            return $this->createMovement(
                vehicle: $vehicle,
                type: ParkingMovement::TYPE_MOVED,
                fromParkingSpace: $fromParkingSpace,
                toParkingSpace: $targetParkingSpace,
                user: $user,
                notes: $notes
            );
        });
    }

    // Rimuove dall'autorimessa un veicolo parcheggiato.
    public function unpark(
        Vehicle $vehicle,
        User $user,
        ?string $notes = null,
        ?Rental $rental = null,
        string $movementType = ParkingMovement::TYPE_UNPARKED
    ): ParkingMovement {
        return DB::transaction(function () use (
            $vehicle,
            $user,
            $notes,
            $rental,
            $movementType
        ) {
            $vehicle = Vehicle::query()
                ->lockForUpdate()
                ->findOrFail($vehicle->id);

            $currentSpaces = $this->currentSpaces($vehicle);

            if ($currentSpaces->isEmpty()) {
                throw new RuntimeException(
                    'Il veicolo non è attualmente parcheggiato.'
                );
            }

            $fromParkingSpace = $currentSpaces->first();

            // Libera tutte le celle occupate dal veicolo.
            ParkingSpace::query()
                ->whereKey($currentSpaces->modelKeys())
                ->update([
                    'vehicle_id' => null,
                ]);

            return $this->createMovement(
                vehicle: $vehicle,
                type: $movementType,
                fromParkingSpace: $fromParkingSpace,
                toParkingSpace: null,
                user: $user,
                notes: $notes,
                rental: $rental
            );
        });
    }

    // Recupera e blocca le celle occupate attualmente dal veicolo.
    private function currentSpaces(Vehicle $vehicle): Collection
    {
        return ParkingSpace::query()
            ->where('vehicle_id', $vehicle->id)
            ->orderBy('zone')
            ->orderBy('row_number')
            ->orderBy('column_number')
            ->lockForUpdate()
            ->get();
    }

    // Trova il blocco rettangolare necessario al veicolo.
    private function requiredSpaces(
        Vehicle $vehicle,
        ParkingSpace $startingSpace
    ): Collection {
        [$requiredRows, $requiredColumns] =
            $this->blockDimensions($vehicle->parking_units);

        $positions = [];

        // Genera tutte le coordinate richieste dal blocco.
        for ($rowOffset = 0; $rowOffset < $requiredRows; $rowOffset++) {
            for (
                $columnOffset = 0;
                $columnOffset < $requiredColumns;
                $columnOffset++
            ) {
                $positions[] = [
                    $startingSpace->row_number + $rowOffset,
                    $startingSpace->column_number + $columnOffset,
                ];
            }
        }

        // Recupera le celle corrispondenti nella stessa zona.
        $spaces = ParkingSpace::query()
            ->where('zone', $startingSpace->zone)
            ->where(function (Builder $query) use ($positions) {
                foreach ($positions as [$rowNumber, $columnNumber]) {
                    $query->orWhere(
                        function (Builder $positionQuery) use (
                            $rowNumber,
                            $columnNumber
                        ) {
                            $positionQuery
                                ->where('row_number', $rowNumber)
                                ->where('column_number', $columnNumber);
                        }
                    );
                }
            })
            ->orderBy('row_number')
            ->orderBy('column_number')
            ->lockForUpdate()
            ->get();

        $requiredCount = $requiredRows * $requiredColumns;

        // Il blocco deve contenere tutte le coordinate richieste.
        if ($spaces->count() !== $requiredCount) {
            throw new RuntimeException(
                'Il blocco selezionato non contiene tutte le celle necessarie.'
            );
        }

        // Tutte le celle devono essere utilizzabili.
        if (
            $spaces->contains(
                fn (ParkingSpace $space) => ! $space->is_active
            )
        ) {
            throw new RuntimeException(
                'Il blocco selezionato contiene una cella disattivata.'
            );
        }

        /*
         * Durante uno spostamento sono ammesse le celle già occupate
         * dallo stesso veicolo, ma non quelle appartenenti ad altri veicoli.
         */
        if (
            $spaces->contains(
                fn (ParkingSpace $space) => $space->vehicle_id !== null
                    && (int) $space->vehicle_id !== (int) $vehicle->id
            )
        ) {
            throw new RuntimeException(
                'Il blocco selezionato contiene celle già occupate.'
            );
        }

        return $spaces;
    }

    // Stabilisce la forma del blocco in base alle dimensioni del veicolo.
    private function blockDimensions(int $parkingUnits): array
    {
        return match ($parkingUnits) {
            1 => [1, 1],
            2 => [1, 2],
            4 => [2, 2],
            8 => [2, 4],
            default => throw new RuntimeException(
                'Il veicolo possiede un numero di unità di parcheggio non valido.'
            ),
        };
    }

    // Registra la fotografia storica del movimento.
    private function createMovement(
        Vehicle $vehicle,
        string $type,
        ?ParkingSpace $fromParkingSpace,
        ?ParkingSpace $toParkingSpace,
        User $user,
        ?string $notes,
        ?Rental $rental = null
    ): ParkingMovement {
        $movement = ParkingMovement::create([
            'vehicle_id' => $vehicle->id,
            'vehicle_license_plate' => $vehicle->license_plate,
            'performed_by_user_id' => $user->id,
            'rental_id' => $rental?->id,
            'type' => $type,

            'from_parking_space_id' => $fromParkingSpace?->id,
            'from_zone' => $fromParkingSpace?->zone,
            'from_row_number' => $fromParkingSpace?->row_number,
            'from_column_number' => $fromParkingSpace?->column_number,

            'to_parking_space_id' => $toParkingSpace?->id,
            'to_zone' => $toParkingSpace?->zone,
            'to_row_number' => $toParkingSpace?->row_number,
            'to_column_number' => $toParkingSpace?->column_number,

            'parking_units' => $vehicle->parking_units,
            'notes' => $notes,
            'occurred_at' => now(),
        ]);

        // Prepara le relazioni utilizzate dal Resource.
        $movement->load([
            'vehicle',
            'performedBy',
            'rental',
        ]);

        return $movement;
    }
}
