<?php

use App\Http\Controllers\SupportDashboardController;
use App\Http\Controllers\SalesDocumentsSyncController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/support', [SupportDashboardController::class, 'index'])
    ->name('support.dashboard');

Route::post('/support/sync', [SupportDashboardController::class, 'run'])
    ->name('support.sync.run');

Route::post('/support/failures/{item}/resolve', [SupportDashboardController::class, 'resolveFailure'])
    ->name('support.failures.resolve');

Route::post('/support/logs/laravel/clear', [SupportDashboardController::class, 'clearLaravelLog'])
    ->name('support.logs.laravel.clear');

Route::post('/support/sales-documents/sync', [SalesDocumentsSyncController::class, 'run'])
    ->name('support.sales-documents.sync.run');

Route::post('/support/sales-documents/failures/{item}/resolve', [SalesDocumentsSyncController::class, 'resolveFailure'])
    ->name('support.sales-documents.failures.resolve');
