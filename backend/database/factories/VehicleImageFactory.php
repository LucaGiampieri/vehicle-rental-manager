<?php

namespace Database\Factories;

use App\Models\Vehicle;
use App\Models\VehicleImage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<VehicleImage>
 */
class VehicleImageFactory extends Factory
{
    // Definisce i dati predefiniti di un’immagine fittizia
    public function definition(): array
    {
        return [
            'vehicle_id' => Vehicle::factory(),
            'path' => 'vehicles/'.fake()->unique()->uuid().'.jpg',
            'original_name' => 'vehicle.jpg',
            'mime_type' => 'image/jpeg',
            'size' => fake()->numberBetween(
                50_000,
                5 * 1024 * 1024
            ),
            'category' => fake()->randomElement(
                VehicleImage::CATEGORIES
            ),
            'caption' => fake()->optional()->sentence(),
            'is_primary' => false,
            'sort_order' => 0,
        ];
    }

    // Permette di creare facilmente un’immagine principale
    public function primary(): static
    {
        return $this->state(fn () => [
            'is_primary' => true,
        ]);
    }
}
