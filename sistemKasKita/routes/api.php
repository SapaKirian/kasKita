<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\CohortController;
use App\Http\Controllers\Api\ClassRoomController;
use App\Http\Controllers\Api\V1\DueController;
use App\Http\Controllers\Api\V1\BillController;
use App\Http\Controllers\Api\V1\PaymentController;
use App\Http\Controllers\Api\RemittanceController;
use App\Http\Controllers\Api\CashTransactionController;
use App\Http\Controllers\Api\CashReportController;

/*
|--------------------------------------------------------------------------
| API Routes - KasKita
|--------------------------------------------------------------------------
*/

Route::prefix('v1')->group(function () {

    /*
    |--------------------------------------------------------------------------
    | Public Routes
    |--------------------------------------------------------------------------
    */

    Route::post('/login', [AuthController::class, 'login']);


    /*
    |--------------------------------------------------------------------------
    | Protected Routes
    |--------------------------------------------------------------------------
    */

    Route::middleware('auth:sanctum')->group(function () {

        // Authentication
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/me', [AuthController::class, 'me']);


        // Dashboard
        Route::get('/dashboard', function (Request $request) {
            return response()->json([
                'message' => 'Selamat datang di KasKita.',
                'user' => $request->user(),
            ]);
        });


        // Role: Bendahara Angkatan
        Route::middleware('role:bendahara_angkatan')
            ->get('/bendahara-angkatan', function () {
                return response()->json([
                    'message' => 'Akses bendahara angkatan berhasil.',
                ]);
            });


        // Role: Bendahara Kelas
        Route::middleware('role:bendahara_kelas')
            ->get('/bendahara-kelas', function () {
                return response()->json([
                    'message' => 'Akses bendahara kelas berhasil.',
                ]);
            });


        // Role: Anggota
        Route::middleware('role:anggota')
            ->get('/anggota', function () {
                return response()->json([
                    'message' => 'Akses anggota berhasil.',
                ]);
            });


        // User Management
        Route::prefix('users')->controller(UserController::class)->group(function () {
            Route::get('/', 'index');
            Route::post('/', 'store');
            Route::get('/{user}', 'show');
            Route::put('/{user}', 'update');
            Route::patch('/{user}/status', 'toggleStatus');
        });
        // Cohort Management
        Route::middleware('role:bendahara_angkatan')
            ->prefix('cohorts')
            ->controller(CohortController::class)
            ->group(function () {
                Route::get('/', 'index');
                Route::post('/', 'store');
                Route::get('/{cohort}', 'show');
                Route::put('/{cohort}', 'update');
                Route::delete('/{cohort}', 'destroy');
            });
        // Class Management
        Route::middleware('role:bendahara_angkatan')
            ->prefix('classes')
            ->controller(ClassRoomController::class)
            ->group(function () {
                Route::get('/', 'index');
                Route::post('/', 'store');

                Route::get('/{classRoom}/available-treasurers', 'availableTreasurers');
                Route::put('/{classRoom}/treasurer', 'assignTreasurer');

                Route::get('/{classRoom}', 'show');
                Route::put('/{classRoom}', 'update');
                Route::delete('/{classRoom}', 'destroy');
            });
        Route::middleware('role:bendahara_angkatan')
            ->prefix('dues')
            ->controller(DueController::class)
            ->group(function () {
                Route::get('/', 'index');
                Route::post('/', 'store');
                Route::get('/{due}', 'show');
                Route::put('/{due}', 'update');
                Route::patch('/{due}/status', 'updateStatus');
                Route::delete('/{due}', 'destroy');
            });
        Route::middleware('role:bendahara_angkatan')
            ->prefix('bills')
            ->controller(BillController::class)
            ->group(function () {
                Route::get('/', 'index');
                Route::post('/', 'store');
                Route::get('/{bill}', 'show');
            });
        Route::middleware('role:bendahara_kelas')
            ->prefix('remittances')
            ->controller(RemittanceController::class)
            ->group(function () {
                Route::post('/', 'store');
            });

        // Remittance - Bendahara Angkatan
        Route::middleware('role:bendahara_angkatan')
            ->prefix('remittances')
            ->controller(RemittanceController::class)
            ->group(function () {
                Route::get('/', 'index');
                Route::get('/{remittance}', 'show');
                Route::patch('/{remittance}/verify', 'verify');
            });
        Route::middleware('role:bendahara_angkatan')
            ->prefix('cash-transactions')
            ->controller(CashTransactionController::class)
            ->group(function () {
                Route::get('/', 'index');
                Route::get('/summary', 'summary');
                Route::post('/', 'store');
            });
        Route::middleware('role:bendahara_angkatan')
            ->prefix('reports')
            ->controller(CashReportController::class)
            ->group(function () {
                Route::get('/cash-flow', 'cashFlow');
                Route::get('/cash-flow/pdf', 'exportPdf');
                Route::get('/cash-flow/excel', 'exportExcel');
            });
    });

});
