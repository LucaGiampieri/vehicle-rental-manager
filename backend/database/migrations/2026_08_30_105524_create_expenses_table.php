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
        Schema::create('expenses', function (Blueprint $table) {

            //Identificativo univoco della spesa
            $table->id();

            //Veicolo associato alla spesa
            $table->foreignId('vehicle_id')
                ->constrained('vehicles')
                ->restrictOnDelete();

            //Tipologia della spesa
            $table->string('category', 30);

            //Descrizione leggibile della spesa
            $table->string('description');

            //Importo realmente sostenuto
            $table->decimal('amount', 10, 2);

            //Data in cui la spesa è stata sostenuta
            $table->date('expense_date');

            //Eventuale data di scadenza
            //Viene usata per bollo, assicurazione e revisione
            $table->date('expires_on')
                ->nullable();

            //Chilometraggio del veicolo al momento della spesa
            $table->unsignedInteger('mileage')
                ->nullable();

            //Officina, assicurazione o altro fornitore
            $table->string('supplier', 150)
                ->nullable();

            //Informazioni aggiuntive
            $table->text('notes')
                ->nullable();

            //Data di creazione e ultima modifica
            $table->timestamps();

            //Velocizza il calcolo dei costi di un mezzo per periodo
            $table->index(
                ['vehicle_id', 'expense_date'],
                'expenses_vehicle_date_index'
            );

            //Velocizza i filtri per categoria
            $table->index('category');

            //Velocizza la ricerca delle prossime scadenze
            $table->index('expires_on');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //Elimina la tabella e il collegamento con vehicles
        Schema::dropIfExists('expenses');
    }
};
