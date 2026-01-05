<?php

use App\Http\Controllers\Api\CandleController;
use App\Http\Controllers\Api\CandleSyncController;
use App\Http\Controllers\Api\OverlayController;
use App\Http\Controllers\Api\SignalController;
use App\Http\Controllers\Api\SymbolController;
use Illuminate\Support\Facades\Route;

Route::get('/symbols', [SymbolController::class, 'index']);
Route::get('/candles', [CandleController::class, 'index']);
Route::post('/sync-candles/all', [CandleSyncController::class, 'queueAll']);
Route::get('/sync-candles/status-all', [CandleSyncController::class, 'statusAll']);
Route::get('/overlays', [OverlayController::class, 'show']);
Route::get('/signals/latest', [SignalController::class, 'latest']);
Route::post('/signals/review', [SignalController::class, 'review']);
Route::get('/signals', [SignalController::class, 'index']);
