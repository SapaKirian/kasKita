<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\PaymentController;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/payment-proofs/{proof}', [
    PaymentController::class,
    'downloadProof',
])
    ->middleware('auth')
    ->name('payment-proofs.download');