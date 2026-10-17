<?php

declare(strict_types=1);

namespace App\Enums;

enum Locale: string
{
    case English = 'en';
    case Hindi = 'hi';
    case Spanish = 'es';
    case French = 'fr';
    case German = 'de';
    case Portuguese = 'pt_BR';
    case Chinese = 'zh_CN';
    case Japanese = 'ja';
    case Russian = 'ru';
    case Korean = 'ko';
    case Nepali = 'ne';

    /**
     * The language's name in that language, so people can find their own language in the list.
     */
    public function label(): string
    {
        return match ($this) {
            self::English => 'English',
            self::Hindi => 'हिन्दी',
            self::Spanish => 'Español',
            self::French => 'Français',
            self::German => 'Deutsch',
            self::Portuguese => 'Português (Brasil)',
            self::Chinese => '中文（简体）',
            self::Japanese => '日本語',
            self::Russian => 'Русский',
            self::Korean => '한국어',
            self::Nepali => 'नेपाली',
        };
    }
}
