<?php

use Illuminate\Support\Facades\Route;

// API routes for the Theme module. Prefixed with /api/v1 by ModulesServiceProvider.
Route::prefix('api/v1')->middleware('api')->group(function (): void {
    //
});
