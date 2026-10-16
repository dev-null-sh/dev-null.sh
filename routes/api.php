<?php

declare(strict_types=1);

use App\Http\Controllers\Cli\CommitController;
use App\Http\Controllers\Cli\KeyLookupController;
use App\Http\Controllers\Ssh\KeyOwnerController;
use App\Http\Middleware\AuthenticateSshSignature;
use Illuminate\Support\Facades\Route;

Route::prefix('cli')->group(function (): void {
    Route::post('commits', [CommitController::class, 'store'])
        ->middleware([AuthenticateSshSignature::class, 'throttle:commits'])
        ->name('cli-commit.store');
    Route::post('key-lookups', [KeyLookupController::class, 'store'])
        ->middleware('throttle:60,1')
        ->name('cli-key-lookup.store');
});

Route::prefix('ssh')->group(function (): void {
    Route::get('key-owner', [KeyOwnerController::class, 'show'])
        ->middleware('throttle:60,1')
        ->name('ssh-key-owner.show');
});
