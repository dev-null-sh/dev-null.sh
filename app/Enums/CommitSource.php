<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Where a commit was created, not the operating system of a web browser.
 * Native apps identify themselves when the user approves their device.
 */
enum CommitSource: string
{
    case Web = 'web';
    case Ios = 'ios';
    case Android = 'android';
    case Macos = 'macos';
    case Cli = 'cli';
    case Api = 'api';

    public static function fromAppId(?string $appId): self
    {
        if ($appId === null || ! str_contains($appId, ':')) {
            return self::Api;
        }

        return match (explode(':', $appId, 2)[0]) {
            'ios' => self::Ios,
            'android' => self::Android,
            'macos' => self::Macos,
            default => self::Api,
        };
    }
}
