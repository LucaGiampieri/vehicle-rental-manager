<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use Illuminate\Support\Facades\Route;

// Permette di effettuare il login
Route::post(
    '/login',
    [AuthenticatedSessionController::class, 'store']
)
    ->middleware('guest')
    ->name('login');

// Permette di richiedere il recupero della password
Route::post(
    '/forgot-password',
    [PasswordResetLinkController::class, 'store']
)
    ->middleware('guest')
    ->name('password.email');

// Permette di impostare una nuova password
Route::post(
    '/reset-password',
    [NewPasswordController::class, 'store']
)
    ->middleware('guest')
    ->name('password.store');

// Permette all’utente autenticato di effettuare il logout
Route::post(
    '/logout',
    [AuthenticatedSessionController::class, 'destroy']
)
    ->middleware('auth')
    ->name('logout');
