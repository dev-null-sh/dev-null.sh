<?php

declare(strict_types=1);

use App\Services\Forecast;

it('projects nothing but a minimal band without any history', function (): void {
    expect(resolve(Forecast::class)->predict([], 2))->toBe([
        'method' => 'flat',
        'trend' => 0.0,
        'points' => [
            ['value' => 0.0, 'lower' => 0.0, 'upper' => 1.0],
            ['value' => 0.0, 'lower' => 0.0, 'upper' => 1.0],
        ],
    ]);
});

it('projects under a week of history flat at its mean, the band from its spread', function (): void {
    expect(resolve(Forecast::class)->predict([2.0, 4.0, 6.0], 2))->toBe([
        'method' => 'flat',
        'trend' => 0.0,
        'points' => [
            ['value' => 4.0, 'lower' => 1.0, 'upper' => 7.0],
            ['value' => 4.0, 'lower' => 1.0, 'upper' => 7.0],
        ],
    ]);
});

it('keeps a minimal band around a flat history that never varied', function (): void {
    expect(resolve(Forecast::class)->predict([3.0, 3.0], 1)['points'])->toBe([
        ['value' => 3.0, 'lower' => 2.0, 'upper' => 4.0],
    ]);
});

it('extends the linear trend of a week or more of history', function (): void {
    expect(resolve(Forecast::class)->predict([1.0, 3.0, 5.0, 7.0, 9.0, 11.0, 13.0, 15.0, 17.0, 19.0], 2))->toBe([
        'method' => 'linear',
        'trend' => 2.0,
        'points' => [
            ['value' => 21.0, 'lower' => 21.0, 'upper' => 21.0],
            ['value' => 23.0, 'lower' => 23.0, 'upper' => 23.0],
        ],
    ]);
});

it('widens the band of a noisy trend the further out it projects', function (): void {
    expect(resolve(Forecast::class)->predict([0.0, 2.0, 1.0, 3.0, 2.0, 4.0, 3.0], 2))->toBe([
        'method' => 'linear',
        'trend' => 0.5,
        'points' => [
            ['value' => 4.1, 'lower' => 2.7, 'upper' => 5.6],
            ['value' => 4.6, 'lower' => 3.0, 'upper' => 6.2],
        ],
    ]);
});

it('never projects below zero', function (): void {
    expect(resolve(Forecast::class)->predict([20.0, 17.0, 14.0, 11.0, 8.0, 5.0, 2.0], 1))->toBe([
        'method' => 'linear',
        'trend' => -3.0,
        'points' => [
            ['value' => 0.0, 'lower' => 0.0, 'upper' => 0.0],
        ],
    ]);
});

it('moves each day with its weekday once there are three weeks of history', function (): void {
    $weekdays = [2.0, -2.0, 0.0, 0.0, 0.0, -2.0, 2.0];
    $history = array_map(fn (int $day): float => 10 + 0.5 * $day + $weekdays[$day % 7], range(0, 27));

    expect(resolve(Forecast::class)->predict($history, 3))->toBe([
        'method' => 'seasonal',
        'trend' => 0.5,
        'points' => [
            ['value' => 26.0, 'lower' => 26.0, 'upper' => 26.0],
            ['value' => 22.5, 'lower' => 22.5, 'upper' => 22.5],
            ['value' => 25.0, 'lower' => 25.0, 'upper' => 25.0],
        ],
    ]);
});

it('fits only the last four weeks', function (): void {
    $history = [...array_fill(0, 10, 500.0), ...array_map(fn (int $day): float => 1.0 + 2 * $day, range(0, 27))];

    expect(resolve(Forecast::class)->predict($history, 1)['points'])->toBe([
        ['value' => 57.0, 'lower' => 57.0, 'upper' => 57.0],
    ]);
});
