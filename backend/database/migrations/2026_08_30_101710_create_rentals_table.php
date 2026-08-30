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
        Schema::create('rentals', function (Blueprint $table) {

            //Identificativo univoco del noleggio
            $table->id();

            //Mezzo associato al noleggio
            $table->foreignId('vehicle_id')
                ->constrained('vehicles')
                ->restrictOnDelete();

            //Cliente associato al noleggio
            $table->foreignId('customer_id')
                ->constrained('customers')
                ->restrictOnDelete();

            //Stato attuale del noleggio
            $table->string('status', 20)
                ->default('reserved');

            //Data e ora previste per la consegna del mezzo
            $table->dateTime('starts_at');

            //Data e ora previste per la riconsegna
            $table->dateTime('expected_ends_at');

            //Data e ora della riconsegna effettiva
            $table->dateTime('actual_ends_at')
                ->nullable();

            //Tariffa giornaliera concordata al momento della prenotazione
            $table->decimal('daily_rate', 10, 2);

            //Importo totale concordato per il noleggio
            $table->decimal('total_amount', 10, 2);

            //Importo realmente pagato dal cliente
            $table->decimal('amount_paid', 10, 2)
                ->default(0);

            //Chilometraggio registrato alla consegna del mezzo
            $table->unsignedInteger('start_mileage')
                ->nullable();

            //Chilometraggio registrato alla riconsegna del mezzo
            $table->unsignedInteger('end_mileage')
                ->nullable();

            //Informazioni aggiuntive sul noleggio
            $table->text('notes')
                ->nullable();

            //Data di creazione e ultima modifica
            $table->timestamps();

            //Velocizza la ricerca delle prenotazioni di un mezzo
            //in un determinato intervallo di tempo
            $table->index(
                ['vehicle_id', 'starts_at', 'expected_ends_at'],
                'rentals_vehicle_period_index'
            );

            //Velocizza i filtri per stato
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //Elimina la tabella e le sue chiavi esterne
        Schema::dropIfExists('rentals');
    }
};
