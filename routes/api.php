<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\FarmAnalysisController;

Route::prefix('farm-analysis')->group(function () {
    Route::get('/poultry/laying', [FarmAnalysisController::class, 'analyzePoultryLaying']);
    Route::post('/swine/breeding', [FarmAnalysisController::class, 'analyzeSwineBreeding']);
    Route::post('/sales', [FarmAnalysisController::class, 'analyzeSales']);
    Route::post('/batch', [FarmAnalysisController::class, 'batchAnalysis']);
    Route::post('/advanced', [FarmAnalysisController::class, 'advancedAnalysis']);
});