<?php

namespace Tests\Feature\Models;

use App\Models\Vehicle;
use App\Models\VehicleImage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VehicleImageTest extends TestCase
{
    use RefreshDatabase;

    // Verifica la relazione tra veicolo e immagini
    public function test_vehicle_and_image_relationships_work(): void
    {
        $vehicle = Vehicle::factory()->create();

        $image = VehicleImage::factory()
            ->for($vehicle)
            ->create();

        $this->assertTrue(
            $image->vehicle->is($vehicle)
        );

        $this->assertTrue(
            $vehicle->images->contains($image)
        );
    }

    // Verifica copertina e ordine della galleria
    public function test_vehicle_images_are_ordered_with_primary_first(): void
    {
        $vehicle = Vehicle::factory()->create();

        $secondImage = VehicleImage::factory()
            ->for($vehicle)
            ->create([
                'sort_order' => 2,
            ]);

        $primaryImage = VehicleImage::factory()
            ->for($vehicle)
            ->primary()
            ->create([
                'sort_order' => 5,
            ]);

        $firstImage = VehicleImage::factory()
            ->for($vehicle)
            ->create([
                'sort_order' => 1,
            ]);

        $this->assertSame(
            [
                $primaryImage->id,
                $firstImage->id,
                $secondImage->id,
            ],
            $vehicle
                ->images()
                ->pluck('id')
                ->all()
        );

        $this->assertTrue(
            $vehicle->primaryImage->is($primaryImage)
        );
    }

    // Verifica l’eliminazione dei record collegati
    public function test_vehicle_images_are_deleted_with_vehicle(): void
    {
        $vehicle = Vehicle::factory()->create();

        $image = VehicleImage::factory()
            ->for($vehicle)
            ->create();

        $vehicle->delete();

        $this->assertDatabaseMissing('vehicle_images', [
            'id' => $image->id,
        ]);
    }
}
