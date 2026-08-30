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
        Schema::create('customers', function (Blueprint $table) {

            //Identificativo univoco del cliente
            $table->id();

            //Nome del cliente
            $table->string('first_name', 100);

            //Cognome del cliente
            $table->string('last_name', 100);

            //Data di nascita
            $table->date('birth_date')
                ->nullable();

            //Indirizzo email
            $table->string('email')
                ->nullable()
                ->unique();

            //Numero telefonico
            $table->string('phone', 30)
                ->nullable();

            //Codice fiscale o identificativo fiscale
            $table->string('tax_code', 32)
                ->nullable()
                ->unique();

            //Numero della patente
            $table->string('driving_license_number', 50)
                ->unique();

            //Data di scadenza della patente
            $table->date('driving_license_expiry_date');

            //Indirizzo completo del cliente
            $table->string('address')
                ->nullable();

            //Informazioni aggiuntive utili all'operatore
            $table->text('notes')
                ->nullable();

            //Indica se il cliente può essere utilizzato nei noleggi
            $table->boolean('is_active')
                ->default(true);

            //Data di creazione e ultima modifica
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //Elimina la tabella se la migrazione viene annullata
        Schema::dropIfExists('customers');
    }
};
