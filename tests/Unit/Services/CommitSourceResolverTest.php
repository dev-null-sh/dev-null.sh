<?php

declare(strict_types=1);

use App\Enums\CommitSource;
use App\Services\CommitSourceResolver;
use Illuminate\Http\Request;

it('resolves an explicit client source', function (CommitSource $expected): void {
    $request = Request::create('/', server: ['HTTP_X_DEVNULL_CLIENT' => $expected->value]);

    expect(resolve(CommitSourceResolver::class)->from($request))->toBe($expected);
})->with(CommitSource::cases());

it('recognizes established client product names', function (string $agent, CommitSource $expected): void {
    $request = Request::create('/', server: ['HTTP_USER_AGENT' => $agent]);

    expect(resolve(CommitSourceResolver::class)->from($request))->toBe($expected);
})->with([
    ['DevNull-Mac/1.0', CommitSource::Macos],
    ['devnull-tui/1.2.3', CommitSource::Tui],
]);

it('leaves unknown client identifiers unlabelled', function (array $server): void {
    $request = Request::create('/', server: $server);

    expect(resolve(CommitSourceResolver::class)->from($request))->toBeNull();
})->with([
    'no identifier' => [[]],
    'unknown header' => [['HTTP_X_DEVNULL_CLIENT' => 'browser']],
    'generic browser' => [['HTTP_USER_AGENT' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7)']],
    'near mac product' => [['HTTP_USER_AGENT' => 'DevNull-Mac']],
]);
