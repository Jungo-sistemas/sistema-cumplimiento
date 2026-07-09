<?php

use App\Http\Controllers\Api\AssetController;
use App\Http\Controllers\Api\AssetTypeController;
use App\Http\Controllers\Api\CompanyController;
use Illuminate\Support\Facades\Route;

Route::middleware('api.token')->prefix('v1')->group(function () {
    Route::get('asset-types', [AssetTypeController::class, 'index']);
    Route::get('asset-types/{slug}/requirements', [AssetTypeController::class, 'requirements']);
    Route::get('companies', [CompanyController::class, 'index']);
    Route::get('companies/{company}/assets', [AssetController::class, 'index']);
});
