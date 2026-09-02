<?php

use App\Http\Controllers\Api\CustomerController;
use App\Http\Controllers\Api\RentalController;
use App\Http\Controllers\Api\VehicleController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

//Raggruppa le rotte accessibili soltanto agli utenti autenticati
Route::middleware(['auth:sanctum'])->group(function () {
    //Restituisce i dati dell'utente che ha effettuato il login
    Route::get('/user', function (Request $request) {
        return $request->user();
    });

    //Crea tutte le rotte CRUD necessarie per gestire i veicoli
    Route::apiResource('vehicles', VehicleController::class);

    //Crea tutte le rotte CRUD necessarie per gestire i clienti
    Route::apiResource('customers', CustomerController::class);

    //Registra la consegna del mezzo
    Route::patch(
        'rentals/{rental}/activate',
        [RentalController::class, 'activate']
    )->name('rentals.activate');

    //Registra il rientro del mezzo
    Route::patch(
        'rentals/{rental}/complete',
        [RentalController::class, 'complete']
    )->name('rentals.complete');

    //Annulla una prenotazione non ancora iniziata
    Route::patch(
        'rentals/{rental}/cancel',
        [RentalController::class, 'cancel']
    )->name('rentals.cancel');

    //Crea le cinque rotte CRUD principali dei noleggi
    Route::apiResource('rentals', RentalController::class);
});
