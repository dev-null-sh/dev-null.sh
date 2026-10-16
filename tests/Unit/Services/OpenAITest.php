<?php

declare(strict_types=1);

use App\Services\OpenAI;
use Illuminate\Support\Facades\Http;

it('asks openai nothing for nothing to embed or judge', function (): void {
    Http::fake();

    $openAI = resolve(OpenAI::class);

    expect($openAI->embedMany([]))->toBe([])
        ->and($openAI->decideRelevance('a query', []))->toBe([]);

    Http::assertNothingSent();
});
