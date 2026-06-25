<?php

use App\Http\Controllers\Dashboard\DeviceController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DashboardHistoryController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('dashboard');
});

Route::get('/dashboard', DashboardController::class)->name('dashboard');
Route::get('/dashboard/bookmarks', [DashboardController::class, 'bookmarks'])->name('dashboard.bookmarks');
Route::get('/dashboard/history', [DashboardController::class, 'history'])->name('dashboard.history');
Route::delete('/dashboard/history', DashboardHistoryController::class)->name('dashboard.history.destroy');

Route::delete('/dashboard/devices/{device}', [DeviceController::class, 'destroy'])->name('dashboard.device.destroy');
Route::delete('/dashboard/devices/{device}/purge', [DeviceController::class, 'purge'])->name('dashboard.device.purge');
