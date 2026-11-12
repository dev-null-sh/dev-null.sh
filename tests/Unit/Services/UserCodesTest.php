<?php

declare(strict_types=1);

use App\Services\UserCodes;

it('makes a code of eight consonants the user can read out, in two groups', function (): void {
    expect(new UserCodes()->generate())->toMatch('/^[BCDFGHJKLMNPQRSTVWXZ]{4}-[BCDFGHJKLMNPQRSTVWXZ]{4}$/');
});

it('makes a different code every time', function (): void {
    $codes = collect(range(1, 20))->map(fn (): string => new UserCodes()->generate());

    expect($codes->unique())->toHaveCount(20);
});

it('reads a code however it was typed', function (string $typed): void {
    expect(new UserCodes()->normalize($typed))->toBe('BCDF-GHJK');
})->with([
    'as shown' => 'BCDF-GHJK',
    'in lowercase' => 'bcdf-ghjk',
    'without the dash' => 'bcdfghjk',
    'with a space instead' => 'bcdf ghjk',
    'with spaces around' => ' BCDF-GHJK ',
    'with a tab' => "bcdf\tghjk",
    'with two dashes' => 'BCDF--GHJK',
]);

it('leaves what is not a code for validation to refuse', function (string $typed, string $normalized): void {
    expect(new UserCodes()->normalize($typed))->toBe($normalized);
})->with([
    'too short' => ['bcd', 'BCD'],
    'too long' => ['bcdf-ghjk-lm', 'BCDFGHJKLM'],
    'with letters outside the alphabet' => ['aeio-uyaa', 'AEIO-UYAA'],
    'with digits' => ['1234-5678', '1234-5678'],
]);
