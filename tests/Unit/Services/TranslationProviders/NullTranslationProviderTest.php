<?php

declare(strict_types=1);

use App\Contracts\Services\TranslationProvider;
use App\Services\TranslationProviders\NullTranslationProvider;

it('is the translation provider in testing', function (): void {
    expect(resolve(TranslationProvider::class))->toBeInstanceOf(NullTranslationProvider::class);
});

it('can tell no language, so translates nothing', function (): void {
    expect(new NullTranslationProvider()->translate('Guten morgen', 'en'))->toBe(['source_language' => 'und', 'body' => 'Guten morgen']);
});
