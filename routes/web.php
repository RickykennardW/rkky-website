<?php

use App\Http\Controllers\RegistrationController;
use Illuminate\Support\Facades\Route;

Route::get('/', [RegistrationController::class, 'index'])->name('registration.index');
Route::post('/register', [RegistrationController::class, 'store'])
    ->middleware('throttle:registrations')
    ->name('registration.store');
Route::get('/registration/success', [RegistrationController::class, 'success'])->name('registration.success');
Route::get('/registration/confirm/{registration}', [RegistrationController::class, 'confirm'])
    ->middleware(['throttle:registration-confirmations', 'signed'])
    ->name('registration.confirm');
