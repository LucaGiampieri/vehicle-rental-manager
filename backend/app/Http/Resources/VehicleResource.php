<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class VehicleResource extends JsonResource
{
    //Trasforma il Model Vehicle nel JSON inviato al frontend
    public function toArray(Request $request): array
    {
        return [
            //Dati identificativi del veicolo
            'id' => $this->id,
            'license_plate' => $this->license_plate,
            'brand' => $this->brand,
            'model' => $this->model,
            'type' => $this->type,

            //Informazioni necessarie per l'autorimessa
            'parking_units' => $this->parking_units,

            //Informazioni tecniche ed economiche
            'year' => $this->year,
            'mileage' => $this->mileage,
            'daily_rate' => $this->daily_rate,
            'is_active' => $this->is_active,

            //I conteggi vengono aggiunti soltanto se caricati dal Controller
            'rentals_count' => $this->whenCounted('rentals'),
            'expenses_count' => $this->whenCounted('expenses'),
            'parking_spaces_count' => $this->whenCounted('parkingSpaces'),

            //Date di creazione e ultima modifica
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
