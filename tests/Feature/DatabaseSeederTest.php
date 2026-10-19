<?php

declare(strict_types=1);

use App\Contracts\Services\DnsResolver;
use App\Enums\InboxTab;
use App\Models\User;
use App\Queries\ConversationsQuery;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    Http::fake([
        '*' => Http::response(
            '<html><meta property="og:description" content="Demo"><meta property="og:image" content="https://example.com/og.png"></html>',
            200,
            ['Content-Type' => 'text/html'],
        ),
    ]);

    $this->app->instance(DnsResolver::class, new readonly class implements DnsResolver
    {
        public function resolve(string $host): array
        {
            return ['93.184.216.34'];
        }

        public function txt(string $host): ?array
        {
            return [];
        }
    });
});

it('gives the local developer people to follow and followers', function (): void {
    $this->seed(DatabaseSeeder::class);

    $localDeveloper = User::query()->where('github_id', 'local')->sole();

    expect($localDeveloper->username)->toBe('local-dev')
        ->and($localDeveloper->following()->count())->toBe(3)
        ->and($localDeveloper->followers()->count())->toBe(5);
});

it('reuses an existing local developer', function (): void {
    $localDeveloper = User::factory()->create(['github_id' => 'local']);

    $this->seed(DatabaseSeeder::class);

    expect(User::query()->where('github_id', 'local')->sole()->id)->toBe($localDeveloper->id)
        ->and($localDeveloper->following()->count())->toBe(3)
        ->and($localDeveloper->followers()->count())->toBe(5);
});

it('gives the local developer conversations to read and reply to', function (bool $requests, int $fetched, int $unread): void {
    config(['remotes.requests' => $requests]);

    $this->seed(DatabaseSeeder::class);

    $localDeveloper = User::query()->where('github_id', 'local')->sole();
    $conversations = resolve(ConversationsQuery::class);

    expect($conversations->get($localDeveloper)['conversations'])->toHaveCount(4)
        ->and($conversations->get($localDeveloper, InboxTab::Fetched)['conversations'])->toHaveCount($fetched)
        ->and($conversations->unreadCount($localDeveloper))->toBe($unread);
})->with([
    'requests off' => [false, 0, 3],
    'requests on' => [true, 1, 4],
]);
