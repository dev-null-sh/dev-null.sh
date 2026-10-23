<?php

declare(strict_types=1);

use App\Actions\CreateStackSuggestions;
use App\Jobs\SuggestDependencyContributorsJob;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

it('suggests who is behind the stack of the user', function (): void {
    $user = User::factory()->create(['username' => 'jess']);

    Http::fake(['api.github.com/users/jess/repos*' => Http::response([])]);

    dispatch_sync(new SuggestDependencyContributorsJob($user));

    expect(Cache::get(CreateStackSuggestions::key($user)))->toBe([]);
});
