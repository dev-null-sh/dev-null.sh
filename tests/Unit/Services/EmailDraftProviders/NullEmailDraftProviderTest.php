<?php

declare(strict_types=1);

use App\Contracts\Services\EmailDraftProvider;
use App\Services\EmailDraftProviders\NullEmailDraftProvider;

it('is the email draft provider in testing', function (): void {
    expect(resolve(EmailDraftProvider::class))->toBeInstanceOf(NullEmailDraftProvider::class);
});

it('writes nothing', function (): void {
    expect(new NullEmailDraftProvider()->draft(7, []))->toBe(['subject' => '', 'body' => '']);
});
