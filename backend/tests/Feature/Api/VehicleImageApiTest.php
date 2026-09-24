<?php

namespace Tests\Feature\Api;

use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleImage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class VehicleImageApiTest extends TestCase
{
    use RefreshDatabase;

    // Impedisce agli utenti non autenticati di accedere alle immagini
    public function test_guest_cannot_access_vehicle_images(): void
    {
        $vehicle = Vehicle::factory()->create();

        $response = $this->getJson(
            "/api/vehicles/{$vehicle->id}/images"
        );

        $response->assertUnauthorized();
    }

    // Restituisce la galleria con la foto principale per prima
    public function test_authenticated_user_can_list_vehicle_images(): void
    {
        $this->authenticateUser();

        $vehicle = Vehicle::factory()->create();

        $secondaryImage = VehicleImage::factory()
            ->for($vehicle)
            ->create([
                'sort_order' => 0,
            ]);

        $primaryImage = VehicleImage::factory()
            ->for($vehicle)
            ->primary()
            ->create([
                'sort_order' => 10,
            ]);

        // Questa immagine appartiene a un altro veicolo
        VehicleImage::factory()->create();

        $response = $this->getJson(
            "/api/vehicles/{$vehicle->id}/images"
        );

        $response->assertOk();
        $response->assertJsonCount(2, 'data');

        $response->assertJsonPath(
            'data.0.id',
            $primaryImage->id
        );

        $response->assertJsonPath(
            'data.1.id',
            $secondaryImage->id
        );

        $response->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'vehicle_id',
                    'url',
                    'original_name',
                    'mime_type',
                    'size',
                    'category',
                    'caption',
                    'is_primary',
                    'sort_order',
                    'created_at',
                    'updated_at',
                ],
            ],
        ]);
    }

    // Carica la prima immagine e la rende automaticamente principale
    public function test_authenticated_user_can_upload_first_vehicle_image(): void
    {
        Storage::fake('public');
        $this->authenticateUser();

        $vehicle = Vehicle::factory()->create();

        $response = $this
            ->withHeader('Accept', 'application/json')
            ->post(
                "/api/vehicles/{$vehicle->id}/images",
                [
                    'image' => UploadedFile::fake()
                        ->image('fiat-panda.jpg'),
                    'category' => '  EXTERIOR  ',
                    'caption' => '  Vista anteriore  ',
                    'is_primary' => 'false',
                ]
            );

        $response->assertCreated();
        $response->assertJsonPath(
            'data.category',
            VehicleImage::CATEGORY_EXTERIOR
        );
        $response->assertJsonPath(
            'data.caption',
            'Vista anteriore'
        );
        $response->assertJsonPath(
            'data.is_primary',
            true
        );

        $image = VehicleImage::firstOrFail();

        $this->assertSame(
            $vehicle->id,
            $image->vehicle_id
        );
        $this->assertSame(
            'fiat-panda.jpg',
            $image->original_name
        );
        $this->assertTrue($image->is_primary);

        Storage::disk('public')->assertExists(
            $image->path
        );
    }

    // Una nuova copertina sostituisce quella precedente
    public function test_uploading_primary_image_replaces_previous_primary(): void
    {
        Storage::fake('public');
        $this->authenticateUser();

        $vehicle = Vehicle::factory()->create();

        $firstResponse = $this
            ->withHeader('Accept', 'application/json')
            ->post(
                "/api/vehicles/{$vehicle->id}/images",
                [
                    'image' => UploadedFile::fake()
                        ->image('first.jpg'),
                ]
            );

        $firstResponse->assertCreated();

        $firstImage = VehicleImage::findOrFail(
            $firstResponse->json('data.id')
        );

        $secondResponse = $this
            ->withHeader('Accept', 'application/json')
            ->post(
                "/api/vehicles/{$vehicle->id}/images",
                [
                    'image' => UploadedFile::fake()
                        ->image('second.jpg'),
                    'is_primary' => 'true',
                ]
            );

        $secondResponse->assertCreated();

        $secondImage = VehicleImage::findOrFail(
            $secondResponse->json('data.id')
        );

        $this->assertFalse(
            $firstImage->fresh()->is_primary
        );

        $this->assertTrue(
            $secondImage->is_primary
        );

        $this->assertSame(
            1,
            VehicleImage::query()
                ->where('vehicle_id', $vehicle->id)
                ->where('is_primary', true)
                ->count()
        );
    }

    // Rifiuta file non validi oppure superiori a 5 MB
    public function test_vehicle_image_requires_valid_file(): void
    {
        Storage::fake('public');
        $this->authenticateUser();

        $vehicle = Vehicle::factory()->create();

        $invalidTypeResponse = $this
            ->withHeader('Accept', 'application/json')
            ->post(
                "/api/vehicles/{$vehicle->id}/images",
                [
                    'image' => UploadedFile::fake()->create(
                        'document.pdf',
                        100,
                        'application/pdf'
                    ),
                ]
            );

        $invalidTypeResponse->assertUnprocessable();
        $invalidTypeResponse->assertJsonValidationErrors([
            'image',
        ]);

        $largeFileResponse = $this
            ->withHeader('Accept', 'application/json')
            ->post(
                "/api/vehicles/{$vehicle->id}/images",
                [
                    'image' => UploadedFile::fake()
                        ->image('large.jpg')
                        ->size(10241),
                ]
            );

        $largeFileResponse->assertUnprocessable();
        $largeFileResponse->assertJsonValidationErrors([
            'image',
        ]);

        $this->assertDatabaseCount('vehicle_images', 0);
    }

    // Impedisce di superare dieci immagini per veicolo
    public function test_vehicle_cannot_have_more_than_ten_images(): void
    {
        Storage::fake('public');
        $this->authenticateUser();

        $vehicle = Vehicle::factory()->create();

        VehicleImage::factory()
            ->count(10)
            ->for($vehicle)
            ->create();

        $response = $this
            ->withHeader('Accept', 'application/json')
            ->post(
                "/api/vehicles/{$vehicle->id}/images",
                [
                    'image' => UploadedFile::fake()
                        ->image('eleventh.jpg'),
                ]
            );

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors([
            'image',
        ]);

        $this->assertDatabaseCount(
            'vehicle_images',
            10
        );
    }

    // Modifica categoria, descrizione e posizione nella galleria
    public function test_authenticated_user_can_update_vehicle_image(): void
    {
        $this->authenticateUser();

        $image = VehicleImage::factory()->create([
            'category' => VehicleImage::CATEGORY_EXTERIOR,
            'caption' => 'Descrizione iniziale',
            'sort_order' => 0,
        ]);

        $response = $this->patchJson(
            "/api/vehicle-images/{$image->id}",
            [
                'category' => '  INTERIOR  ',
                'caption' => '  Sedili posteriori  ',
                'sort_order' => 3,
            ]
        );

        $response->assertOk();
        $response->assertJsonPath(
            'data.category',
            VehicleImage::CATEGORY_INTERIOR
        );
        $response->assertJsonPath(
            'data.caption',
            'Sedili posteriori'
        );
        $response->assertJsonPath(
            'data.sort_order',
            3
        );

        $this->assertDatabaseHas('vehicle_images', [
            'id' => $image->id,
            'category' => VehicleImage::CATEGORY_INTERIOR,
            'caption' => 'Sedili posteriori',
            'sort_order' => 3,
        ]);

        // Una richiesta vuota deve essere rifiutata
        $emptyResponse = $this->patchJson(
            "/api/vehicle-images/{$image->id}",
            []
        );

        $emptyResponse->assertUnprocessable();
        $emptyResponse->assertJsonValidationErrors([
            'image',
        ]);
    }

    // Permette di scegliere una nuova copertina
    public function test_vehicle_image_can_become_primary(): void
    {
        $this->authenticateUser();

        $vehicle = Vehicle::factory()->create();

        $oldPrimary = VehicleImage::factory()
            ->for($vehicle)
            ->primary()
            ->create();

        $newPrimary = VehicleImage::factory()
            ->for($vehicle)
            ->create();

        $response = $this->patchJson(
            "/api/vehicle-images/{$newPrimary->id}",
            [
                'is_primary' => 'true',
            ]
        );

        $response->assertOk();
        $response->assertJsonPath(
            'data.is_primary',
            true
        );

        $this->assertFalse(
            $oldPrimary->fresh()->is_primary
        );

        $this->assertTrue(
            $newPrimary->fresh()->is_primary
        );

        $this->assertSame(
            $newPrimary->id,
            $vehicle->fresh()->primaryImage->id
        );

        /*
         * La copertina non può essere rimossa
         * senza sceglierne prima un’altra.
         */
        $unsetResponse = $this->patchJson(
            "/api/vehicle-images/{$newPrimary->id}",
            [
                'is_primary' => false,
            ]
        );

        $unsetResponse->assertUnprocessable();
        $unsetResponse->assertJsonValidationErrors([
            'is_primary',
        ]);

        $this->assertTrue(
            $newPrimary->fresh()->is_primary
        );
    }

    // Elimina il file e promuove la prima immagine rimasta
    public function test_deleting_primary_image_promotes_next_image(): void
    {
        Storage::fake('public');
        $this->authenticateUser();

        $vehicle = Vehicle::factory()->create();

        $primaryPath = "vehicles/{$vehicle->id}/primary.jpg";
        $nextPath = "vehicles/{$vehicle->id}/next.jpg";

        Storage::disk('public')->put(
            $primaryPath,
            'primary image content'
        );

        Storage::disk('public')->put(
            $nextPath,
            'next image content'
        );

        $primaryImage = VehicleImage::factory()
            ->for($vehicle)
            ->primary()
            ->create([
                'path' => $primaryPath,
                'sort_order' => 0,
            ]);

        $nextImage = VehicleImage::factory()
            ->for($vehicle)
            ->create([
                'path' => $nextPath,
                'sort_order' => 1,
            ]);

        $response = $this->deleteJson(
            "/api/vehicle-images/{$primaryImage->id}"
        );

        $response->assertNoContent();

        $this->assertDatabaseMissing('vehicle_images', [
            'id' => $primaryImage->id,
        ]);

        $this->assertTrue(
            $nextImage->fresh()->is_primary
        );

        Storage::disk('public')->assertMissing(
            $primaryPath
        );

        Storage::disk('public')->assertExists(
            $nextPath
        );
    }

    // Crea e autentica un utente fittizio
    private function authenticateUser(): void
    {
        $user = User::factory()->create();

        Sanctum::actingAs($user);
    }
}
