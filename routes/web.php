<?php

declare(strict_types=1);

use App\Http\Controllers\ApproveCommitsController;
use App\Http\Controllers\CommitApprovalController;
use App\Http\Controllers\CommitController;
use App\Http\Controllers\CommitOpenGraphImageController;
use App\Http\Controllers\CommitRejectionController;
use App\Http\Controllers\CommitReplyController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\LogController;
use App\Http\Controllers\ModerationController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\SessionController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\UserFollowController;
use App\Http\Controllers\UserFollowersController;
use App\Http\Controllers\UserFollowingController;
use App\Http\Controllers\UserLaneController;
use App\Http\Controllers\UserOpenGraphImageController;
use App\Http\Controllers\UserPreviewController;
use App\Http\Controllers\UserProfileController;
use App\Http\Controllers\UserSearchController;
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

// User, previewed for guests...
Route::get('@{user:username}', [UserController::class, 'show'])->name('user.show');
Route::get('@{user:username}/og.png', [UserOpenGraphImageController::class, 'show'])
    ->name('user-open-graph-image.show');

// Commit, previewed for guests...
Route::get('@{user:username}/commits/{commit}', [CommitController::class, 'show'])
    ->scopeBindings()
    ->name('commit.show');
Route::get('@{user:username}/commits/{commit}/og.png', [CommitOpenGraphImageController::class, 'show'])
    ->scopeBindings()
    ->name('commit-open-graph-image.show');

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
    Route::get('commits/{commit}/replies', [CommitReplyController::class, 'index'])
        ->withTrashed()
        ->name('commit-reply.index');

    // Notifications...
    Route::get('notifications', [NotificationController::class, 'index'])->name('notification.index');

    // Moderation...
    Route::middleware('can:moderate')->group(function (): void {
        Route::get('moderation', [ModerationController::class, 'show'])->name('moderation.show');
        Route::post('moderation/{commit}/approval', [CommitApprovalController::class, 'store'])
            ->name('commit-approval.store');
        Route::post('moderation/{commit}/rejection', [CommitRejectionController::class, 'store'])
            ->name('commit-rejection.store');
        Route::post('moderation/approve', ApproveCommitsController::class)
            ->name('moderation.approve');
    });

    // User Search...
    Route::get('search/users', [UserSearchController::class, 'index'])->name('user-search.index');

    // User Profile...
    Route::get('settings', fn () => to_route('user-profile.edit'));
    Route::get('settings/profile', [UserProfileController::class, 'edit'])->name('user-profile.edit');

    // User Lane...
    Route::put('settings/lane', [UserLaneController::class, 'update'])->name('user-lane.update');

    // Appearance...
    Route::get('settings/appearance', fn () => Inertia::render('appearance/update'))->name('appearance.edit');

    // User...
    Route::delete('user', [UserController::class, 'destroy'])->name('user.destroy');
    Route::get('@{user:username}/preview', [UserPreviewController::class, 'show'])->name('user-preview.show');

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

Route::fallback(fn () => abort(404));
