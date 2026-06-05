<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\EcommerceSyncController;
use App\Http\Controllers\Api\FirebirdController;
use App\Http\Controllers\Api\MicrosipController;

Route::middleware('api.key')->prefix('v1')->group(function () {
    Route::get('/health', [FirebirdController::class, 'health']);
    Route::get('/firebird/tables', [FirebirdController::class, 'tables']);
    Route::get('/firebird/tables/{table}', [FirebirdController::class, 'table']);
    Route::post('/sync/ecommerce/check', [EcommerceSyncController::class, 'check']);

    Route::prefix('microsip')->group(function () {
        Route::get('/ping', [MicrosipController::class, 'ping']);
    });
});
