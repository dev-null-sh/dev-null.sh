<?php

declare(strict_types=1);

use App\Http\Middleware\StripNullBytes;
use App\Services\NullBytes;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

it('takes NUL bytes out of the query and form inputs, nested ones included', function (): void {
    $request = Request::create('/?q=a%00b', 'POST', ['body' => 'x'.chr(0).'y', 'poll' => ['options' => ['one'.chr(0), 'two']], 'count' => 3]);

    new StripNullBytes(new NullBytes())->handle($request, function (Request $request): Response {
        expect($request->query('q'))->toBe('ab')
            ->and($request->input('body'))->toBe('xy')
            ->and($request->input('poll.options'))->toBe(['one', 'two'])
            ->and($request->input('count'))->toBe(3);

        return response('OK');
    });
});

it('takes NUL bytes out of a JSON body but leaves the raw content a signature covers', function (): void {
    $content = (string) json_encode(['body' => 'x'.chr(0).'y']);
    $request = Request::create('/', 'POST', server: ['CONTENT_TYPE' => 'application/json'], content: $content);

    new StripNullBytes(new NullBytes())->handle($request, function (Request $request) use ($content): Response {
        expect($request->input('body'))->toBe('xy')
            ->and($request->getContent())->toBe($content);

        return response('OK');
    });
});
