<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect('/chart');
});

Route::get('/chart', function () {
    return view('chart');
});
