<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Crea la cronologia dei movimenti dell'autorimessa.
     */
    public function up(): void
    {
        Schema::create('parking_movements', function (Blueprint $table) {
            // Identificativo univoco del movimento.
            $table->id();

            /*
             * Veicolo interessato dal movimento.
             * Può diventare null se il veicolo viene eliminato,
             * mentre la cronologia rimane conservata.
             */
            $table->foreignId('vehicle_id')
                ->nullable()
                ->constrained('vehicles')
                ->nullOnDelete();

            /*
             * Salva la targa anche separatamente.
             * In questo modo rimane leggibile nella cronologia
             * anche se il veicolo viene eliminato.
             */
            $table->string('vehicle_license_plate', 20);

            /*
             * Utente che ha eseguito l'operazione.
             * Può diventare null se l'utente viene eliminato.
             */
            $table->foreignId('performed_by_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            /*
             * Collega facoltativamente il movimento a un noleggio.
             * Verrà utilizzato per partenza e rientro del veicolo.
             */
            $table->foreignId('rental_id')
                ->nullable()
                ->constrained('rentals')
                ->nullOnDelete();

            /*
             * Tipologia del movimento:
             * parked, moved, unparked,
             * rental_departure oppure rental_return.
             */
            $table->string('type', 30);

            // Cella iniziale del movimento, se esistente.
            $table->foreignId('from_parking_space_id')
                ->nullable()
                ->constrained('parking_spaces')
                ->nullOnDelete();

            // Posizione iniziale salvata come fotografia storica.
            $table->string('from_zone', 50)
                ->nullable();
            $table->unsignedSmallInteger('from_row_number')
                ->nullable();
            $table->unsignedSmallInteger('from_column_number')
                ->nullable();

            // Cella di destinazione del movimento, se esistente.
            $table->foreignId('to_parking_space_id')
                ->nullable()
                ->constrained('parking_spaces')
                ->nullOnDelete();

            // Posizione finale salvata come fotografia storica.
            $table->string('to_zone', 50)
                ->nullable();
            $table->unsignedSmallInteger('to_row_number')
                ->nullable();
            $table->unsignedSmallInteger('to_column_number')
                ->nullable();

            // Numero di celle occupate dal veicolo.
            $table->unsignedTinyInteger('parking_units');

            // Annotazione facoltativa inserita dall'operatore.
            $table->text('notes')
                ->nullable();

            // Momento effettivo nel quale è avvenuto il movimento.
            $table->timestamp('occurred_at')
                ->useCurrent();

            // Data di creazione e ultima modifica tecnica del record.
            $table->timestamps();

            // Velocizza la cronologia di uno specifico veicolo.
            $table->index(
                ['vehicle_id', 'occurred_at'],
                'parking_movements_vehicle_date_index'
            );

            // Velocizza la ricerca dei movimenti collegati a un noleggio.
            $table->index(
                ['rental_id', 'occurred_at'],
                'parking_movements_rental_date_index'
            );

            // Velocizza i filtri per tipologia.
            $table->index('type');
        });
    }

    /**
     * Elimina la tabella quando la migrazione viene annullata.
     */
    public function down(): void
    {
        Schema::dropIfExists('parking_movements');
    }
};
