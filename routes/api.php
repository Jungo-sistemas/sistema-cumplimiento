<?php

use App\Http\Controllers\Api\AssetController;
use App\Http\Controllers\Api\AssetTypeController;
use Illuminate\Support\Facades\Route;

Route::middleware('api.token')->prefix('v1')->group(function () {
    Route::get('asset-types', [AssetTypeController::class, 'index']);
    Route::get('asset-types/{slug}/requirements', [AssetTypeController::class, 'requirements']);
    Route::get('companies/{company}/assets', [AssetController::class, 'index']);
});
