<?php

declare(strict_types=1);

namespace App\Contracts\Services;

interface HumanVerificationProvider
{
    public function siteKey(): string;

    public function isHuman(string $token): bool;
}
