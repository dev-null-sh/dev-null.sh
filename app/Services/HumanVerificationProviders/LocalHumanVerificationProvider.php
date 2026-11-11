<?php

declare(strict_types=1);

namespace App\Services\HumanVerificationProviders;

use App\Contracts\Services\HumanVerificationProvider;

final readonly class LocalHumanVerificationProvider implements HumanVerificationProvider
{
    private const string ALWAYS_PASSING_SITE_KEY = '1x00000000000000000000AA';

    public function siteKey(): string
    {
        return self::ALWAYS_PASSING_SITE_KEY;
    }

    public function isHuman(string $token): bool
    {
        return $token !== '';
    }
}
