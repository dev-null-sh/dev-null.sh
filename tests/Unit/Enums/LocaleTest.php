<?php

declare(strict_types=1);

use App\Enums\Locale;

it('has the languages devnull is translated into', function (): void {
    expect(array_column(Locale::cases(), 'value', 'name'))->toBe([
        'English' => 'en',
        'Hindi' => 'hi',
        'Spanish' => 'es',
        'French' => 'fr',
        'German' => 'de',
        'Portuguese' => 'pt_BR',
        'Chinese' => 'zh_CN',
        'Japanese' => 'ja',
        'Russian' => 'ru',
        'Korean' => 'ko',
        'Nepali' => 'ne',
    ]);
});

it('names each language in that language', function (Locale $locale, string $label): void {
    expect($locale->label())->toBe($label);
})->with([
    [Locale::English, 'English'],
    [Locale::Hindi, 'हिन्दी'],
    [Locale::Spanish, 'Español'],
    [Locale::French, 'Français'],
    [Locale::German, 'Deutsch'],
    [Locale::Portuguese, 'Português (Brasil)'],
    [Locale::Chinese, '中文（简体）'],
    [Locale::Japanese, '日本語'],
    [Locale::Russian, 'Русский'],
    [Locale::Korean, '한국어'],
    [Locale::Nepali, 'नेपाली'],
]);
