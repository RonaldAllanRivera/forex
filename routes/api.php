<?php

use App\Http\Controllers\Api\CandleController;
use App\Http\Controllers\Api\CandleSyncController;
use App\Http\Controllers\Api\OverlayController;
use App\Http\Controllers\Api\SignalController;
use App\Http\Controllers\Api\SymbolController;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth'])->group(function () {
    Route::get('/symbols', [SymbolController::class, 'index']);
    Route::get('/candles', [CandleController::class, 'index']);
    Route::post('/sync-candles/all', [CandleSyncController::class, 'queueAll']);
    Route::get('/sync-candles/status-all', [CandleSyncController::class, 'statusAll']);
    Route::get('/overlays', [OverlayController::class, 'show']);
    Route::get('/signals/latest', [SignalController::class, 'latest']);
    Route::post('/signals/review', [SignalController::class, 'review']);
    Route::get('/signals', [SignalController::class, 'index']);

    Route::get('/health', function () {
        return response()->json([
            'data' => [
                'sync' => [
                    'D1' => Cache::get('forex:last_sync:D1'),
                    'W1' => Cache::get('forex:last_sync:W1'),
                    'MN1' => Cache::get('forex:last_sync:MN1'),
                ],
                'signals' => [
                    'D1' => Cache::get('forex:last_signals:D1'),
                    'W1' => Cache::get('forex:last_signals:W1'),
                    'MN1' => Cache::get('forex:last_signals:MN1'),
                ],
                'email' => [
                    'last' => Cache::get('forex:last_email'),
                    'last_error' => Cache::get('forex:last_email_error'),
                ],
            ],
        ])->header('Cache-Control', 'no-store');
    });
});
