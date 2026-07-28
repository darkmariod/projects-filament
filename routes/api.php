<?php

use App\Http\Controllers\Api\PrintQueueAgentController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Rutas para el agente de impresión Windows.
| Autenticadas por X-Agent-Key header.
|
*/

Route::prefix('agent')->middleware('agent.auth')->group(function () {
    Route::get('status',                          [PrintQueueAgentController::class, 'status']);
    Route::get('pending',                         [PrintQueueAgentController::class, 'pending']);
    Route::post('print-label',                    [PrintQueueAgentController::class, 'printSingleLabel']);
    Route::post('{queueId}/complete',             [PrintQueueAgentController::class, 'completeQueue']);
    Route::post('{queueId}/item/{itemId}/complete', [PrintQueueAgentController::class, 'completeItem']);
    Route::post('{queueId}/item/{itemId}/failed',   [PrintQueueAgentController::class, 'failItem']);
});
