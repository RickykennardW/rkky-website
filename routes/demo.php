<?php

use App\Http\Controllers\DemoController;
use App\Http\Controllers\MonitoringController;
use App\Http\Controllers\PlannerController;
use Illuminate\Support\Facades\Route;

Route::get('/', [DemoController::class, 'dashboard'])->name('dashboard');
Route::get('/login', [DemoController::class, 'login'])->name('login');
Route::redirect('/register', '/login')->name('register');
Route::post('/login', [DemoController::class, 'enter'])->middleware('throttle:20,1')->block();
Route::post('/logout', [DemoController::class, 'logout'])->middleware('auth')->block()->name('logout');

Route::middleware('auth')->group(function (): void {
    require __DIR__.'/scheduling.php';
    Route::post('/monitoring/sample', [MonitoringController::class, 'sample'])->middleware('throttle:10,1')->block()->name('monitoring.sample');
});

Route::controller(DemoController::class)->middleware('auth')->group(function (): void {
    Route::get('/rooms', 'rooms')->name('rooms.index');
    Route::get('/rooms/{room}', 'room')->whereUuid('room')->name('rooms.show');
    Route::get('/equipment', 'equipment')->name('equipment.index');
    Route::get('/api/availability', 'availability')->middleware('throttle:60,1')->name('availability');
    Route::get('/book', 'create')->name('bookings.create');
    Route::post('/bookings', 'store')->block()->name('bookings.store');
    Route::get('/bookings', 'bookings')->name('bookings.index');
    Route::get('/bookings/{booking}', 'booking')->name('bookings.show');
    Route::post('/bookings/{booking}/cancel', 'cancel')->block()->name('bookings.cancel');
    Route::get('/planner', [PlannerController::class, 'show'])->name('planner.show');
    Route::post('/planner/preview', [PlannerController::class, 'preview'])->middleware('throttle:60,1')->name('planner.preview');

    Route::prefix('admin')->name('admin.')->middleware('can:admin')->group(function (): void {
        Route::get('/', 'admin')->name('dashboard');
        Route::get('/bookings', 'bookings')->name('bookings.index');
        Route::get('/bookings/{booking}', 'booking')->name('bookings.show');
        Route::post('/bookings/{booking}/review', 'review')->block()->name('bookings.review');
    });
});
