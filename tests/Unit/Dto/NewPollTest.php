<?php

declare(strict_types=1);

use App\Dto\NewPoll;
use App\Enums\PollDuration;

it('holds between 2 and 4 options', function (array $options): void {
    expect((new NewPoll($options, PollDuration::OneDay))->options)->toBe($options);
})->with([
    'two' => [['Tabs', 'Spaces']],
    'four' => [['PHP', 'Go', 'Rust', 'Zig']],
]);

it('refuses too few or too many options', function (array $options): void {
    expect(fn (): NewPoll => new NewPoll($options, PollDuration::OneDay))->toThrow(InvalidArgumentException::class);
})->with([
    'one' => [['Tabs']],
    'five' => [['PHP', 'Go', 'Rust', 'Zig', 'C']],
]);
