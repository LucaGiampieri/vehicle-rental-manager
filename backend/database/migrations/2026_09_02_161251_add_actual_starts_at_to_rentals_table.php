<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    //Aggiunge l'orario effettivo di consegna del mezzo
    public function up(): void
    {
        Schema::table('rentals', function (Blueprint $table) {
            $table->dateTime('actual_starts_at')
                ->nullable()
                ->after('starts_at');
        });
    }

    //Rimuove la colonna in caso di rollback
    public function down(): void
    {
        Schema::table('rentals', function (Blueprint $table) {
            $table->dropColumn('actual_starts_at');
        });
    }
};
