<?php

declare(strict_types=1);

namespace App\Enums;

enum GifProvider: string
{
    case Giphy = 'giphy';
    case Klipy = 'klipy';

    public function label(): string
    {
        return match ($this) {
            self::Giphy => 'GIPHY',
            self::Klipy => 'KLIPY',
        };
    }

    /**
     * @return list<string>
     */
    public function mediaHostPatterns(): array
    {
        return match ($this) {
            self::Giphy => ['/^media\d*\.giphy\.com$/'],
            self::Klipy => ['/^static\d*\.klipy\.com$/'],
        };
    }

    /**
     * @return list<string>
     */
    public function pageHostPatterns(): array
    {
        return match ($this) {
            self::Giphy => ['/^(www\.)?giphy\.com$/'],
            self::Klipy => ['/^(www\.)?klipy\.com$/'],
        };
    }
}
