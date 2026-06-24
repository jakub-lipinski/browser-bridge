<?php

use App\Http\Controllers\Api\BookmarkBackupController;
use App\Http\Controllers\Api\BookmarkSnapshotController;
use App\Http\Controllers\Api\BookmarkSyncProfileController;
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
    Route::get('/bookmarks', [BookmarkSnapshotController::class, 'bookmarks']);
    Route::get('/bookmarks/search', [BookmarkSnapshotController::class, 'search']);
    Route::post('/bookmarks/backup', [BookmarkBackupController::class, 'store']);
    Route::get('/bookmarks/export', [BookmarkBackupController::class, 'export']);
    Route::delete('/bookmarks/device/{device}', [BookmarkSnapshotController::class, 'destroyForDevice']);

    Route::get('/bookmark-sync/profiles', [BookmarkSyncProfileController::class, 'index']);
    Route::post('/bookmark-sync/profiles', [BookmarkSyncProfileController::class, 'store']);
    Route::put('/bookmark-sync/profiles/{bookmarkSyncProfile}', [BookmarkSyncProfileController::class, 'update']);
    Route::delete('/bookmark-sync/profiles/{bookmarkSyncProfile}', [BookmarkSyncProfileController::class, 'destroy']);
    Route::post('/bookmark-sync/profiles/{bookmarkSyncProfile}/preview', [BookmarkSyncProfileController::class, 'preview']);
    Route::post('/bookmark-sync/profiles/{bookmarkSyncProfile}/run', [BookmarkSyncProfileController::class, 'run']);
    Route::get('/bookmark-sync/runs', [BookmarkSyncProfileController::class, 'runs']);
    Route::get('/bookmark-sync/runs/{bookmarkSyncRun}', [BookmarkSyncProfileController::class, 'showRun']);

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
