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

            //Tariffa giornaliera di base in euro
            //nullable permette di lasciare la tariffa da impostare
            $table->decimal('daily_rate', 10, 2)
                ->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('vehicles', function (Blueprint $table) {

            //Rimuove la colonna se annulliamo questa migrazione
            $table->dropColumn('daily_rate');
        });
    }
};
