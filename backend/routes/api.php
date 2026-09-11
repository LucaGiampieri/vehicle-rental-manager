<?php

use App\Http\Controllers\Api\CustomerController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\ExpenseController;
use App\Http\Controllers\Api\GarageController;
use App\Http\Controllers\Api\ParkingSpaceController;
use App\Http\Controllers\Api\RentalController;
use App\Http\Controllers\Api\VehicleController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Raggruppa le rotte accessibili soltanto agli utenti autenticati
Route::middleware(['auth:sanctum'])->group(function () {
    // Restituisce i dati dell'utente che ha effettuato il login
    Route::get('/user', function (Request $request) {
        return $request->user();
    });

    // Crea tutte le rotte CRUD necessarie per gestire i veicoli
    Route::apiResource('vehicles', VehicleController::class);

    // Crea tutte le rotte CRUD necessarie per gestire i clienti
    Route::apiResource('customers', CustomerController::class);

    // Crea tutte le rotte CRUD necessarie per gestire le spese
    Route::apiResource('expenses', ExpenseController::class);

    // Crea le rotte CRUD per gestire le celle dell'autorimessa
    Route::apiResource('parking-spaces', ParkingSpaceController::class);

    // Parcheggia un veicolo nel blocco che parte dalla cella selezionata
    Route::post(
        'garage/park',
        [GarageController::class, 'park']
    )->name('garage.park');

    // Sposta un veicolo verso un nuovo blocco di celle
    Route::patch(
        'garage/vehicles/{vehicle}/move',
        [GarageController::class, 'move']
    )->name('garage.vehicles.move');

    // Fa uscire il veicolo e libera tutte le sue celle
    Route::patch(
        'garage/vehicles/{vehicle}/unpark',
        [GarageController::class, 'unpark']
    )->name('garage.vehicles.unpark');

    // Restituisce la cronologia generale dei movimenti
    Route::get(
        'garage/movements',
        [GarageController::class, 'movements']
    )->name('garage.movements.index');

    // Restituisce la cronologia di uno specifico veicolo
    Route::get(
        'garage/vehicles/{vehicle}/movements',
        [GarageController::class, 'vehicleMovements']
    )->name('garage.vehicles.movements');

    // Restituisce il riepilogo economico e operativo
    Route::get(
        'dashboard',
        [DashboardController::class, 'index']
    )->name('dashboard.index');

    // Registra la consegna del mezzo
    Route::patch(
        'rentals/{rental}/activate',
        [RentalController::class, 'activate']
    )->name('rentals.activate');

    // Registra il rientro del mezzo
    Route::patch(
        'rentals/{rental}/complete',
        [RentalController::class, 'complete']
    )->name('rentals.complete');

    // Annulla una prenotazione non ancora iniziata
    Route::patch(
        'rentals/{rental}/cancel',
        [RentalController::class, 'cancel']
    )->name('rentals.cancel');

    // Crea le cinque rotte CRUD principali dei noleggi
    Route::apiResource('rentals', RentalController::class);
});
