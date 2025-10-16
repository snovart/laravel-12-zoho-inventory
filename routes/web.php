<?php

use Illuminate\Support\Facades\Route;

Route::get('/', fn () => view('welcome'));

// One Blade for all SPA routes under /zoho/inventory
Route::prefix('/inventory')->group(function () {
    Route::view('/salesorders', 'zoho.inventory.index')->name('salesorders.index');
    Route::view('/salesorders/{any}', 'zoho.inventory.index')->where('any', '.*');
});
