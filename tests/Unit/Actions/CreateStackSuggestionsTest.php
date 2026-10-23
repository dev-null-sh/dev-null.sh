<?php

declare(strict_types=1);

use App\Actions\CreateStackSuggestions;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

it('remembers who is behind the stack of the user for a week', function (): void {
    $user = User::factory()->create(['username' => 'jess']);
    $contributor = User::factory()->create();

    Http::fake([
        'api.github.com/users/jess/repos*' => Http::response([['full_name' => 'jess/blog', 'fork' => false]]),
        'raw.githubusercontent.com/jess/blog/HEAD/composer.json' => Http::response(['require' => ['laravel/framework' => '^13.0']]),
        'raw.githubusercontent.com/jess/blog/HEAD/package.json' => Http::response('404: Not Found', 404),
        'repo.packagist.org/p2/laravel/framework.json' => Http::response([
            'packages' => ['laravel/framework' => [['source' => ['url' => 'https://github.com/laravel/framework.git']]]],
        ]),
        'api.github.com/repos/laravel/framework/contributors*' => Http::response([
            ['id' => (int) $contributor->github_id, 'type' => 'User'],
            ['id' => (int) $user->github_id, 'type' => 'User'],
            ['id' => 1_000_000_000, 'type' => 'User'],
        ]),
    ]);

    resolve(CreateStackSuggestions::class)->handle($user);

    expect(Cache::get(CreateStackSuggestions::key($user)))->toBe([$contributor->id]);

    $this->travel(7)->days();

    expect(Cache::has(CreateStackSuggestions::key($user)))->toBeFalse();
});
