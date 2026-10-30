<?php

declare(strict_types=1);

use App\Contracts\Services\ToolDecisionProvider;
use App\Services\ToolDecisionProviders\NullToolDecisionProvider;

it('is the tool decision provider in testing', function (): void {
    expect(resolve(ToolDecisionProvider::class))->toBeInstanceOf(NullToolDecisionProvider::class);
});

it('needs no tool for any message', function (): void {
    expect(new NullToolDecisionProvider()->tools('@tom how many members are here?', []))->toBeEmpty();
});
