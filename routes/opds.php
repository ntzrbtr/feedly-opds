<?php

declare(strict_types=1);

use App\Http\Controllers\OpdsController;
use Illuminate\Support\Facades\Route;

Route::prefix('opds')->middleware('opds.token')->group(function (): void {
    Route::get('/', [OpdsController::class, 'catalog'])->name('opds.catalog');
    Route::get('/saved', [OpdsController::class, 'saved'])->name('opds.saved');
    Route::get('/entry/{entryId}', [OpdsController::class, 'entry'])->name('opds.entry')
        ->where('entryId', '.+');
    Route::get('/download/{entryId}', [OpdsController::class, 'download'])->name('opds.download')
        ->where('entryId', '.+');
});
