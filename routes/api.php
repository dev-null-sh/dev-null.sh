<?php

declare(strict_types=1);

use App\Exceptions\ApiException;
use App\Http\Controllers\Api\BookmarkController;
use App\Http\Controllers\Api\CommitController as ApiCommitController;
use App\Http\Controllers\Api\CommitPastelController;
use App\Http\Controllers\Api\CommitReplyController;
use App\Http\Controllers\Api\CommitReportController;
use App\Http\Controllers\Api\CommitStarController;
use App\Http\Controllers\Api\CurrentUserController;
use App\Http\Controllers\Api\DeviceController;
use App\Http\Controllers\Api\LogController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\NotificationGroupController;
use App\Http\Controllers\Api\UserBlockController;
use App\Http\Controllers\Api\UserCommitController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\UserFollowController;
use App\Http\Controllers\Api\UserFollowersController;
use App\Http\Controllers\Api\UserFollowingController;
use App\Http\Controllers\Api\UserLaneController;
use App\Http\Controllers\Api\UserMuteController;
use App\Http\Controllers\Api\UserPasteisModeController;
use App\Http\Controllers\Api\UserProfileController;
use App\Http\Controllers\Cli\CommitController;
use App\Http\Controllers\Cli\KeyLookupController;
use App\Http\Middleware\AuthenticateSignedRequest;
use App\Http\Middleware\AuthenticateSshSignature;
use App\Http\Middleware\ThrottleCommits;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Route;

Route::prefix('cli')->group(function (): void {
    Route::post('commits', [CommitController::class, 'store'])
        ->middleware([AuthenticateSshSignature::class, ThrottleCommits::class])
        ->name('cli-commit.store');
    Route::post('key-lookups', [KeyLookupController::class, 'store'])
        ->middleware('throttle:60,1')
        ->name('cli-key-lookup.store');
});

Route::prefix('v1')->middleware('throttle:api')->group(function (): void {
    // Public, or as the web shows them to the user when signed by the mobile app...
    Route::middleware(AuthenticateSignedRequest::optional())->group(function (): void {
        Route::get('users/{user:username}', [UserController::class, 'show'])
            ->name('api-user.show');
        Route::get('users/{user:username}/commits', [UserCommitController::class, 'index'])
            ->name('api-user-commit.index');
        Route::get('users/{user:username}/followers', [UserFollowersController::class, 'index'])
            ->name('api-user-followers.index');
        Route::get('users/{user:username}/following', [UserFollowingController::class, 'index'])
            ->name('api-user-following.index');
        Route::get('commits/{commit}', [ApiCommitController::class, 'show'])
            ->name('api-commit.show');
    });

    // Signing in: the app redeems the code it got on the web, signed with a key that isn't approved yet...
    Route::post('devices', [DeviceController::class, 'store'])
        ->withoutMiddleware('throttle:api')
        ->middleware('throttle:devices')
        ->name('api-device.store');

    // Signed by the mobile app with an SSH key that the user approved as a device...
    Route::middleware(AuthenticateSignedRequest::class)->group(function (): void {
        Route::get('me', [CurrentUserController::class, 'show'])->name('api-current-user.show');
        Route::delete('me', [CurrentUserController::class, 'destroy'])->name('api-current-user.destroy');

        // Pastéis...
        Route::put('me/pasteis-mode', [UserPasteisModeController::class, 'update'])->name('api-user-pasteis-mode.update');

        // User Profile: mirrored from GitHub again, as Sync from GitHub does on the web...
        Route::put('me/github', [UserProfileController::class, 'update'])
            ->middleware('throttle:profile-syncs')
            ->name('api-user-profile.update');

        // User Lane...
        Route::put('me/lane', [UserLaneController::class, 'update'])->name('api-user-lane.update');

        // Log...
        Route::get('log/{log}', [LogController::class, 'show'])->name('api-log.show');

        // Commit...
        Route::post('commits', [ApiCommitController::class, 'store'])
            ->middleware(ThrottleCommits::class)
            ->name('api-commit.store');
        Route::delete('commits/{commit}', [ApiCommitController::class, 'destroy'])->name('api-commit.destroy');

        // Commit Reply...
        Route::get('commits/{commit}/replies', [CommitReplyController::class, 'index'])
            ->withTrashed()
            ->name('api-commit-reply.index');

        // Commit Report: a commit that doesn't exist or was deleted gets the same answer as one the user may not see...
        Route::post('commits/{commit}/reports', [CommitReportController::class, 'store'])
            ->middleware('throttle:commit-reports')
            ->missing(fn (): JsonResponse => ApiException::commitUnavailable()->render())
            ->name('api-commit-report.store');

        // Commit Pastel...
        Route::post('commits/{commit}/pasteis', [CommitPastelController::class, 'store'])
            ->middleware('throttle:60,1')
            ->name('api-commit-pastel.store');

        // Private saved commits...
        Route::get('saved', [BookmarkController::class, 'index'])->name('api-bookmark.index');
        Route::put('commits/{commit}/bookmark', [BookmarkController::class, 'store'])
            ->middleware('throttle:bookmarks')
            ->name('api-bookmark.store');
        Route::delete('commits/{commit}/bookmark', [BookmarkController::class, 'destroy'])
            ->middleware('throttle:bookmarks')
            ->name('api-bookmark.destroy');

        // Commit Star...
        Route::post('commits/{commit}/star', [CommitStarController::class, 'store'])
            ->middleware('throttle:stars')
            ->name('api-commit-star.store');
        Route::delete('commits/{commit}/star', [CommitStarController::class, 'destroy'])
            ->middleware('throttle:stars')
            ->name('api-commit-star.destroy');

        // User Follow...
        Route::post('users/{user:username}/follow', [UserFollowController::class, 'store'])
            ->middleware('throttle:follows')
            ->name('api-user-follow.store');
        Route::delete('users/{user:username}/follow', [UserFollowController::class, 'destroy'])
            ->middleware('throttle:follows')
            ->name('api-user-follow.destroy');

        // User Mute...
        Route::get('me/muted', [UserMuteController::class, 'index'])->name('api-user-mute.index');
        Route::post('users/{user:username}/mute', [UserMuteController::class, 'store'])->name('api-user-mute.store');
        Route::delete('users/{user:username}/mute', [UserMuteController::class, 'destroy'])->name('api-user-mute.destroy');

        // User Block...
        Route::get('me/blocked', [UserBlockController::class, 'index'])->name('api-user-block.index');
        Route::post('users/{user:username}/block', [UserBlockController::class, 'store'])->name('api-user-block.store');
        Route::delete('users/{user:username}/block', [UserBlockController::class, 'destroy'])->name('api-user-block.destroy');

        // Notifications...
        Route::get('notifications', [NotificationController::class, 'index'])->name('api-notification.index');
        Route::get('notifications/{notification}/group', [NotificationGroupController::class, 'show'])->name('api-notification-group.show');
    });
});
