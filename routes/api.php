<?php

use App\Http\Controllers\Api\CandleController;
use App\Http\Controllers\Api\SignalController;
use App\Http\Controllers\Api\SymbolController;
use Illuminate\Support\Facades\Route;

Route::get('/symbols', [SymbolController::class, 'index']);
Route::get('/candles', [CandleController::class, 'index']);
Route::get('/signals/latest', [SignalController::class, 'latest']);
Route::get('/signals', [SignalController::class, 'index']);
