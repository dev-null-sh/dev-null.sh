<?php

declare(strict_types=1);

use App\Rules\NotBlank;
use Illuminate\Support\Facades\Validator;

it('lets invisible blank values through without the rule', function (string $value): void {
    $validator = Validator::make(['title' => $value], ['title' => ['required', 'string', 'min:3', 'max:72']]);

    expect($validator->passes())->toBeTrue();
})->with([
    'non breaking spaces' => "\u{00A0}\u{00A0}\u{00A0}",
    'zero width spaces' => "\u{200B}\u{200B}\u{200B}",
]);

it('rejects invisible blank values with the rule', function (string $value): void {
    $validator = Validator::make(['title' => $value], ['title' => ['required', 'string', 'min:3', 'max:72', new NotBlank]]);

    expect($validator->fails())->toBeTrue();
})->with([
    'non breaking spaces' => "\u{00A0}\u{00A0}\u{00A0}",
    'zero width spaces' => "\u{200B}\u{200B}\u{200B}",
]);
