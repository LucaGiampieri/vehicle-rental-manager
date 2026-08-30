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
        Schema::create('parking_spaces', function (Blueprint $table) {

            //Identificativo tecnico univoco della cella
            $table->id();

            //Nome visibile facoltativo
            $table->string('label', 20)
                ->nullable()
                ->unique();

            //Zona dell'autorimessa a cui appartiene la cella
            $table->string('zone', 50)
                ->default('main');

            //Riga occupata dalla cella nella mappa grafica
            $table->unsignedSmallInteger('row_number');

            //Colonna occupata dalla cella nella mappa grafica
            $table->unsignedSmallInteger('column_number');

            //Veicolo che occupa attualmente la cella
            //Lo stesso veicolo può comparire in più celle
            $table->foreignId('vehicle_id')
                ->nullable()
                ->constrained('vehicles')
                ->nullOnDelete();

            //Indica se la cella può essere utilizzata
            $table->boolean('is_active')
                ->default(true);

            //Informazioni su ostacoli, accessi o limitazioni
            $table->text('notes')
                ->nullable();

            //Data di creazione e ultima modifica
            $table->timestamps();

            //Impedisce di sovrapporre due celle nella stessa zona
            $table->unique(
                ['zone', 'row_number', 'column_number'],
                'parking_spaces_position_unique'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //Elimina le celle e il loro collegamento con vehicles
        Schema::dropIfExists('parking_spaces');
    }
};
