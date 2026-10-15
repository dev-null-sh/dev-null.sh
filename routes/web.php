<?php

declare(strict_types=1);

use App\Http\Controllers\PostController;
use App\Http\Controllers\SessionController;
use App\Http\Controllers\TimelineController;
use App\Http\Controllers\TopicController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\UserFollowController;
use App\Http\Controllers\UserProfileController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::middleware('guest')->group(function (): void {
    Route::get('/', fn () => Inertia::render('welcome'))->name('home');

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

    // Timeline...
    Route::get('timelines/{timeline}', [TimelineController::class, 'show'])->name('timeline.show');

    // Post...
    Route::post('posts', [PostController::class, 'store'])
        ->middleware('throttle:posts')
        ->name('post.store');
    Route::delete('posts/{post}', [PostController::class, 'destroy'])
        ->name('post.destroy');

    // Topic...
    Route::get('topics/{topic:name}', [TopicController::class, 'show'])->name('topic.show');

    // User Profile...
    Route::get('settings', fn () => to_route('user-profile.edit'));
    Route::get('settings/profile', [UserProfileController::class, 'edit'])->name('user-profile.edit');

    // Appearance...
    Route::get('settings/appearance', fn () => Inertia::render('appearance/update'))->name('appearance.edit');

    // User...
    Route::delete('user', [UserController::class, 'destroy'])->name('user.destroy');
    Route::get('@{user:username}', [UserController::class, 'show'])->name('user.show');

    // User Follow...
    Route::post('@{user:username}/follow', [UserFollowController::class, 'store'])
        ->name('user-follow.store');
    Route::delete('@{user:username}/follow', [UserFollowController::class, 'destroy'])
        ->name('user-follow.destroy');
});
