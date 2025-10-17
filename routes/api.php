<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\ZohoInventoryController;

Route::prefix('zoho')->group(function () {
    Route::get('/health', [ZohoInventoryController::class, 'health']);
    Route::get('/items',  [ZohoInventoryController::class, 'items']);
    Route::post('/salesorders', [ZohoInventoryController::class, 'createSalesOrder']);
    Route::get('/salesorders', [ZohoInventoryController::class, 'listSalesOrders']);
    Route::get('/salesorders/{id}',  [ZohoInventoryController::class, 'getSalesOrder']);
    Route::get('/items/{id}', [ZohoInventoryController::class, 'getItem']);
    Route::get('/contacts', [ZohoInventoryController::class, 'contacts']);
    Route::get('/contacts/{id}', [ZohoInventoryController::class, 'getContact']);
});

