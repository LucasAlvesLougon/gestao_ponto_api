<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\ReportController;
use App\Http\Controllers\Api\V1\SummaryController;
use App\Http\Controllers\Api\V1\TimeEntryController;
use App\Http\Controllers\Api\V1\WorkScheduleController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes - v1
|--------------------------------------------------------------------------
*/

Route::prefix('v1')->group(function () {
    // Rotas de Autenticação Públicas (com Rate Limiting contra força bruta)
    Route::prefix('auth')->middleware('throttle:15,1')->group(function () {
        Route::post('/register', [AuthController::class, 'register']);
        Route::post('/login', [AuthController::class, 'login']);
        Route::post('/google', [AuthController::class, 'googleLogin']);
    });

    // Rotas Protegidas por Token Sanctum
    Route::middleware('auth:sanctum')->group(function () {
        // Usuário & Perfil
        Route::prefix('auth')->group(function () {
            Route::post('/logout', [AuthController::class, 'logout']);
            Route::get('/me', [AuthController::class, 'me']);
            Route::put('/me', [AuthController::class, 'update']);
        });

        // Controle de Ponto (CRUD de Time Entries)
        Route::apiResource('time-entries', TimeEntryController::class)->only([
            'index', 'store', 'update', 'destroy'
        ]);

        // Resumos e Cálculos de Saldo (Daily & Monthly)
        Route::prefix('summary')->group(function () {
            Route::get('/daily', [SummaryController::class, 'daily']);
            Route::get('/monthly', [SummaryController::class, 'monthly']);
        });

        // Configuração de Jornada (Meta de Horas)
        Route::prefix('work-schedules')->group(function () {
            Route::get('/', [WorkScheduleController::class, 'show']);
            Route::post('/', [WorkScheduleController::class, 'store']);
        });

        // Exportação de Relatórios (CSV & Impressão/PDF)
        Route::prefix('reports')->group(function () {
            Route::get('/csv', [ReportController::class, 'csv']);
            Route::get('/print', [ReportController::class, 'print']);
        });
    });
});
