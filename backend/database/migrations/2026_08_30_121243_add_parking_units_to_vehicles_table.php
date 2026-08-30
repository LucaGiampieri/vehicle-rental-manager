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
        Schema::table('vehicles', function (Blueprint $table) {

            //Numero di celle necessarie per parcheggiare il veicolo
            //Il valore predefinito 2 rappresenta un'automobile
            $table->unsignedTinyInteger('parking_units')
                ->default(2)
                ->after('type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('vehicles', function (Blueprint $table) {

            //Rimuove il numero di celle se la migrazione viene annullata
            $table->dropColumn('parking_units');
        });
    }
};
