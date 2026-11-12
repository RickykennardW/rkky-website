<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BookingController;
use App\Http\Controllers\CatalogController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PlannerController;
use App\Services\ProductionReadiness;
use Illuminate\Support\Facades\Route;

Route::get('/', DashboardController::class)->name('dashboard');

Route::get('/health/campusspace', function (ProductionReadiness $readiness) {
    $ready = $readiness->applicationReady();
    $releasePath = storage_path('app/campusspace-release');
    $release = is_readable($releasePath) ? trim(file_get_contents($releasePath)) : '';

    return response()->json([
        'app' => 'CampusSpace',
        'ready' => $ready,
        'release' => preg_match('/^[0-9a-f]{40}$/D', $release) ? $release : null,
    ], $ready ? 200 : 503)
        ->header('Cache-Control', 'no-store');
})->name('health.campusspace');

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [AuthController::class, 'loginForm'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:8,1');
    Route::get('/register', [AuthController::class, 'registerForm'])->name('register');
    Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:5,1');
});

Route::middleware('auth')->group(function (): void {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::get('/email/verify', [AuthController::class, 'verificationNotice'])->name('verification.notice');
    Route::get('/email/verify/{id}/{hash}', [AuthController::class, 'verify'])
        ->middleware(['signed', 'throttle:6,1'])->name('verification.verify');
    Route::post('/email/verification-notification', [AuthController::class, 'resendVerification'])
        ->middleware('throttle:6,1')->name('verification.send');
});

Route::middleware(['auth', 'verified'])->group(function (): void {
    Route::get('/rooms', [CatalogController::class, 'rooms'])->name('rooms.index');
    Route::get('/rooms/{room}', [CatalogController::class, 'room'])->whereUuid('room')->name('rooms.show');
    Route::get('/equipment', [CatalogController::class, 'equipment'])->name('equipment.index');
    Route::get('/api/availability', [CatalogController::class, 'availability'])
        ->middleware('throttle:60,1')->name('availability');
    Route::get('/planner', [PlannerController::class, 'show'])->name('planner.show');
    Route::post('/planner/preview', [PlannerController::class, 'preview'])
        ->middleware('throttle:60,1')->name('planner.preview');

    Route::get('/book', [BookingController::class, 'create'])->name('bookings.create');
    Route::post('/bookings', [BookingController::class, 'store'])->name('bookings.store');
    Route::get('/bookings', [BookingController::class, 'index'])->name('bookings.index');
    Route::get('/bookings/{booking}', [BookingController::class, 'show'])->whereUuid('booking')->name('bookings.show');
    Route::post('/bookings/{booking}/cancel', [BookingController::class, 'cancel'])
        ->whereUuid('booking')->name('bookings.cancel');

    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('/notifications/read', [NotificationController::class, 'markRead'])->name('notifications.read');

    Route::prefix('admin')->name('admin.')->middleware('can:admin')->group(function (): void {
        Route::get('/', [AdminController::class, 'dashboard'])->name('dashboard');
        Route::get('/bookings', [BookingController::class, 'adminIndex'])->name('bookings.index');
        Route::get('/bookings/{booking}', [BookingController::class, 'adminShow'])
            ->whereUuid('booking')->name('bookings.show');
        Route::post('/bookings/{booking}/review', [BookingController::class, 'review'])
            ->whereUuid('booking')->name('bookings.review');
        Route::post('/bookings/{booking}/pickup', [BookingController::class, 'pickup'])
            ->whereUuid('booking')->name('bookings.pickup');
        Route::post('/bookings/{booking}/return', [BookingController::class, 'returnEquipment'])
            ->whereUuid('booking')->name('bookings.return');
        Route::post('/bookings/{booking}/complete', [BookingController::class, 'complete'])
            ->whereUuid('booking')->name('bookings.complete');

        Route::get('/rooms', [AdminController::class, 'rooms'])->name('rooms.index');
        Route::get('/rooms/new', [AdminController::class, 'roomEditor'])->name('rooms.create');
        Route::get('/rooms/{room}', [AdminController::class, 'roomEditor'])->whereUuid('room')->name('rooms.edit');
        Route::post('/rooms/{room?}', [AdminController::class, 'saveRoom'])->whereUuid('room')->name('rooms.save');

        Route::get('/equipment', [AdminController::class, 'equipment'])->name('equipment.index');
        Route::get('/equipment/new', [AdminController::class, 'equipmentEditor'])->name('equipment.create');
        Route::get('/equipment/{equipment}', [AdminController::class, 'equipmentEditor'])
            ->whereUuid('equipment')->name('equipment.edit');
        Route::post('/equipment/{equipment?}', [AdminController::class, 'saveEquipment'])
            ->whereUuid('equipment')->name('equipment.save');

        Route::get('/loans', [AdminController::class, 'loans'])->name('loans.index');
    });
});
