<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Crea la tabella delle immagini dei veicoli
    public function up(): void
    {
        Schema::create('vehicle_images', function (Blueprint $table) {
            $table->id();

            // Veicolo al quale appartiene la fotografia
            $table->foreignId('vehicle_id')
                ->constrained('vehicles')
                ->cascadeOnDelete();

            // Percorso del file salvato nello storage
            $table->string('path')
                ->unique();

            // Informazioni originali del file caricato
            $table->string('original_name');
            $table->string('mime_type', 100);
            $table->unsignedBigInteger('size');

            /*
             * Categoria della fotografia:
             * exterior, interior, plate, damage oppure other.
             */
            $table->string('category', 30)
                ->default('exterior');

            // Descrizione facoltativa della fotografia
            $table->string('caption')
                ->nullable();

            // Indica l’immagine mostrata come copertina
            $table->boolean('is_primary')
                ->default(false);

            // Permette di ordinare le immagini nella galleria
            $table->unsignedSmallInteger('sort_order')
                ->default(0);

            $table->timestamps();

            // Velocizza il recupero della copertina
            $table->index(
                ['vehicle_id', 'is_primary'],
                'vehicle_images_primary_index'
            );

            // Velocizza l’ordinamento della galleria
            $table->index(
                ['vehicle_id', 'sort_order'],
                'vehicle_images_order_index'
            );
        });
    }

    // Elimina la tabella durante un rollback
    public function down(): void
    {
        Schema::dropIfExists('vehicle_images');
    }
};
