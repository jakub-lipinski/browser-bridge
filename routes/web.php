<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DashboardHistoryController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('dashboard');
});

Route::get('/dashboard', DashboardController::class)->name('dashboard');
Route::delete('/dashboard/history', DashboardHistoryController::class)->name('dashboard.history.destroy');
