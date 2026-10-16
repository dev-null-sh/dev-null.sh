<?php

declare(strict_types=1);

use App\Jobs\RevokeAppleToken;
use App\Services\AppleTokens;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

beforeEach(function (): void {
    configureAppleKey();
});

it('revokes the refresh token at apple', function (): void {
    Http::fake([AppleTokens::RevokeUrl => Http::response()]);

    dispatch_sync(new RevokeAppleToken('the-refresh-token'));

    Http::assertSent(fn (Request $request): bool => $request->url() === AppleTokens::RevokeUrl && $request['token'] === 'the-refresh-token');
});

it('keeps the token encrypted on the queue, and tries again while apple cannot be reached', function (): void {
    Exceptions::fake();

    Http::fakeSequence(AppleTokens::RevokeUrl)
        ->pushFailedConnection()
        ->push();

    Queue::connection('database')->push(new RevokeAppleToken('the-refresh-token'));

    expect(DB::table('jobs')->sole()->payload)->not->toContain('the-refresh-token');

    $this->artisan('queue:work', ['connection' => 'database', '--once' => true])->assertSuccessful();
    $this->travel(59)->seconds();
    $this->artisan('queue:work', ['connection' => 'database', '--once' => true])->assertSuccessful();

    Http::assertSentCount(1);

    $this->travel(1)->seconds();
    $this->artisan('queue:work', ['connection' => 'database', '--once' => true])->assertSuccessful();

    Http::assertSentCount(2);

    expect(DB::table('jobs')->count())->toBe(0);
});
