<?php

use App\Http\Controllers\MonitoringController;
use App\Http\Controllers\SchedulingController;
use Illuminate\Support\Facades\Route;

Route::get('/smart-scheduling', SchedulingController::class)->name('scheduling.index');
Route::post('/smart-scheduling', SchedulingController::class)->middleware('throttle:20,1')->name('scheduling.check');
Route::get('/monitoring', [MonitoringController::class, 'index'])->name('monitoring.index');
Route::get('/api/monitoring', [MonitoringController::class, 'snapshot'])->middleware('throttle:60,1')->name('monitoring.snapshot');
