<?php

use App\Http\Controllers\EditalController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'app');

Route::post('/editais/analyze', [EditalController::class, 'analyze'])
    ->name('editais.analyze');
