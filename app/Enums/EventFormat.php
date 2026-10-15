<?php

declare(strict_types=1);

namespace App\Enums;

enum EventFormat: string
{
    case Onsite = 'onsite';
    case Online = 'online';
    case Hybrid = 'hybrid';

    public function label(): string
    {
        return match ($this) {
            self::Onsite => 'on-site',
            self::Online => 'online',
            self::Hybrid => 'hybrid',
        };
    }

    public function hasVenue(): bool
    {
        return $this !== self::Online;
    }

    public function hasStream(): bool
    {
        return $this !== self::Onsite;
    }
}
