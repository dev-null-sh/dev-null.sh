<?php

declare(strict_types=1);

use App\Actions\StoreCommitImages;
use App\Actions\VerifySignedCommit;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

arch()->preset()->php();
arch()->preset()->strict();
arch()->preset()->laravel();
arch()->preset()->security()->ignoring([
    'assert',
]);

arch('controllers')
    ->expect('App\Http\Controllers')
    ->not->toBeUsed();

arch('no policies or gates')
    ->expect('App')
    ->not->toHaveSuffix('Policy')
    ->not->toUse([
        Gate::class,
        Illuminate\Contracts\Auth\Access\Gate::class,
        HandlesAuthorization::class,
        AuthorizesRequests::class,
    ]);

arch('controllers leave authorization and validation to form requests')
    ->expect('App\Http\Controllers')
    ->not->toUse([
        'abort',
        'abort_if',
        'abort_unless',
        'throw_if',
        'throw_unless',
        'validator',
        Validator::class,
        ValidationException::class,
    ]);

// Signed commits are verified by middleware, before the user is known, and images can only fail to decode once they are processed...
arch('actions leave authorization and validation to form requests')
    ->expect('App\Actions')
    ->not->toUse([
        'abort',
        'abort_if',
        'abort_unless',
        'throw_if',
        'throw_unless',
        'validator',
        Validator::class,
        ValidationException::class,
    ])
    ->ignoring([
        VerifySignedCommit::class,
        StoreCommitImages::class,
    ]);

//
