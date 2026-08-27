<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('vehicles', function (Blueprint $table) {

            $table->id();

            //Targa
            //unique impedisce di registrare due mezzi con la stessa targa
            $table->string('license_plate', 20)
            ->unique();

            //Marca del mezzo
            $table->string('brand', 50);

            //Modello del mezzo
            $table->string('model', 80);

            //Tipo del mezzo
            $table->string('type', 20);

            //Anno del mezzo
            //nullable permette di lasciarlo senza valore
            $table->unsignedSmallInteger('year')
            ->nullable();

            //Chilometraggio
            $table->unsignedInteger('mileage');

            //Indica sel mezzo fa ancora parte della flotta attiva
            $table->boolean('is_active')
            ->default(true);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vehicles');
    }
};
