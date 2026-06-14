<?php

use Illuminate\Support\Facades\Route;

// API routes for the CMS module. Prefixed with /api/v1 by ModulesServiceProvider.
Route::prefix('api/v1')->middleware('api')->group(function (): void {
    //
});
