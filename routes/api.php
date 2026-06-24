<?php

use App\Http\Controllers\Api\BookmarkSnapshotController;
use App\Http\Controllers\Api\DeviceController;
use App\Http\Controllers\Api\HistoryController;
use App\Http\Controllers\Api\TabCommandController;
use App\Http\Controllers\Api\TabSnapshotController;
use Illuminate\Support\Facades\Route;

Route::middleware('browserbridge.token')->group(function (): void {
    Route::post('/device/register', [DeviceController::class, 'register']);
    Route::get('/devices', [DeviceController::class, 'index']);

    Route::post('/bookmarks/snapshot', [BookmarkSnapshotController::class, 'store']);
    Route::get('/bookmarks/snapshots', [BookmarkSnapshotController::class, 'index']);

    Route::post('/tabs/snapshot', [TabSnapshotController::class, 'store']);
    Route::get('/tabs/snapshots', [TabSnapshotController::class, 'index']);
    Route::post('/tabs/send', [TabCommandController::class, 'send']);
    Route::get('/tabs/incoming', [TabCommandController::class, 'incoming']);
    Route::post('/tabs/{tabCommand}/opened', [TabCommandController::class, 'opened']);
    Route::post('/tabs/{tabCommand}/dismissed', [TabCommandController::class, 'dismissed']);

    Route::post('/history/batch', [HistoryController::class, 'batch']);
    Route::get('/history/search', [HistoryController::class, 'search']);
    Route::delete('/history', [HistoryController::class, 'destroy']);
    Route::delete('/history/device/{device}', [HistoryController::class, 'destroyForDevice']);
});
