<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Admin\AdminSettingsController;

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])->name('login.store');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');

    Route::middleware('admin')->prefix('admin')->group(function () {
        Route::get('/settings', [AdminSettingsController::class, 'edit'])->name('admin.settings');
        Route::put('/password', [AdminSettingsController::class, 'updatePassword'])->name('admin.password.update');
    });

    Route::get('/', function () {
        return redirect('/chart');
    });

    Route::get('/chart', function () {
        return view('chart');
    })->name('chart');
});
