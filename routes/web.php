<?php

declare(strict_types=1);

use App\Http\Controllers\CommitApprovalController;
use App\Http\Controllers\CommitController;
use App\Http\Controllers\CommitRejectionController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\LogController;
use App\Http\Controllers\ModerationController;
use App\Http\Controllers\SessionController;
use App\Http\Controllers\UserCardController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\UserFollowController;
use App\Http\Controllers\UserFollowersController;
use App\Http\Controllers\UserFollowingController;
use App\Http\Controllers\UserProfileController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::middleware('guest')->group(function (): void {
    Route::get('/', [HomeController::class, 'show'])->name('home');

    // Session...
    Route::get('login', [SessionController::class, 'create'])
        ->name('login');
    Route::get('auth/github/callback', [SessionController::class, 'store'])
        ->middleware('throttle:10,1')
        ->name('login.store');
});

Route::middleware('auth')->group(function (): void {
    // Session...
    Route::post('logout', [SessionController::class, 'destroy'])
        ->name('logout');

    // Log...
    Route::get('log/{log}', [LogController::class, 'show'])->name('log.show');

    // Commit...
    Route::post('commits', [CommitController::class, 'store'])
        ->middleware('throttle:commits')
        ->name('commit.store');
    Route::delete('commits/{commit}', [CommitController::class, 'destroy'])
        ->name('commit.destroy');

    // Moderation...
    Route::middleware('can:moderate')->group(function (): void {
        Route::get('moderation', [ModerationController::class, 'show'])->name('moderation.show');
        Route::post('moderation/{commit}/approval', [CommitApprovalController::class, 'store'])
            ->name('commit-approval.store');
        Route::post('moderation/{commit}/rejection', [CommitRejectionController::class, 'store'])
            ->name('commit-rejection.store');
    });

    // User Profile...
    Route::get('settings', fn () => to_route('user-profile.edit'));
    Route::get('settings/profile', [UserProfileController::class, 'edit'])->name('user-profile.edit');

    // Appearance...
    Route::get('settings/appearance', fn () => Inertia::render('appearance/update'))->name('appearance.edit');

    // User...
    Route::delete('user', [UserController::class, 'destroy'])->name('user.destroy');
    Route::get('@{user:username}', [UserController::class, 'show'])->name('user.show');
    Route::get('@{user:username}/card', [UserCardController::class, 'show'])->name('user-card.show');

    Route::get('@{user:username}/following', [UserFollowingController::class, 'index'])
        ->name('user-following.index');
    Route::get('@{user:username}/followers', [UserFollowersController::class, 'index'])
        ->name('user-followers.index');

    // User Follow...
    Route::post('@{user:username}/follow', [UserFollowController::class, 'store'])
        ->name('user-follow.store');
    Route::delete('@{user:username}/follow', [UserFollowController::class, 'destroy'])
        ->name('user-follow.destroy');
});
