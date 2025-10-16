<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\ZohoInventoryController;

Route::prefix('zoho')->group(function () {
    Route::get('/health', [ZohoInventoryController::class, 'health']);
    Route::get('/items',  [ZohoInventoryController::class, 'items']);
    Route::post('/salesorders', [ZohoInventoryController::class, 'createSalesOrder']);
});

