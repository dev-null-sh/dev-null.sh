<?php

declare(strict_types=1);

namespace App\Services;

/**
 * @phpstan-type ForecastMethod 'flat'|'linear'|'seasonal'
 * @phpstan-type ForecastPoint array{value: float, lower: float, upper: float}
 * @phpstan-type ForecastResult array{method: ForecastMethod, trend: float, points: list<ForecastPoint>}
 */
final readonly class Forecast
{
    /**
     * The two-sided 80% quantile of the normal distribution: four days out of five should land inside the band.
     */
    private const float Z = 1.2816;

    private const int TREND_POINTS = 7;

    private const int SEASONAL_POINTS = 21;

    /**
     * Only the last four weeks are fitted, so an old launch spike doesn't bend today's trend.
     */
    private const int WINDOW = 28;

    /**
     * Too few days to have a spread still leave room either side of the mean.
     */
    private const float MIN_FLAT_BAND = 1.0;

    /**
     * The days that follow a daily history, oldest first and ending yesterday, starting today. Under a week of history
     * is projected flat at its mean; otherwise a straight line is fitted to the last four weeks, and from three weeks on
     * the weekday each day falls on moves it up or down as it did in that time. The band is an 80% prediction interval.
     *
     * @param  list<float>  $history
     * @param  positive-int  $horizon
     * @return ForecastResult
     */
    public function predict(array $history, int $horizon): array
    {
        $values = array_slice($history, -self::WINDOW);
        $count = count($values);

        if ($count < self::TREND_POINTS) {
            $mean = $count === 0 ? 0.0 : array_sum($values) / $count;
            $deviation = $count < 2 ? 0.0 : sqrt(array_sum(array_map(fn (float $value): float => ($value - $mean) ** 2, $values)) / ($count - 1));
            $band = max(self::Z * $deviation * sqrt(1 + 1 / max(1, $count)), self::MIN_FLAT_BAND);

            return [
                'method' => 'flat',
                'trend' => 0.0,
                'points' => array_fill(0, $horizon, $this->point($mean, $band)),
            ];
        }

        $xMean = ($count - 1) / 2;
        $yMean = array_sum($values) / $count;
        $sxx = array_sum(array_map(fn (int $x): float => ($x - $xMean) ** 2, range(0, $count - 1)));
        $slope = array_sum(array_map(fn (int $x, float $y): float => ($x - $xMean) * ($y - $yMean), range(0, $count - 1), $values)) / $sxx;
        $intercept = $yMean - $slope * $xMean;
        $residuals = array_map(fn (int $x, float $y): float => $y - ($intercept + $slope * $x), range(0, $count - 1), $values);

        $isSeasonal = $count >= self::SEASONAL_POINTS;
        $seasons = array_fill(0, 7, 0.0);

        if ($isSeasonal) {
            $seasons = array_map(
                fn (int $weekday): float => collect($residuals)->filter(fn (float $residual, int $x): bool => $x % 7 === $weekday)->avg() ?? 0.0,
                range(0, 6),
            );
            $offset = array_sum($seasons) / 7;
            $seasons = array_map(fn (float $season): float => $season - $offset, $seasons);
        }

        $errors = array_map(fn (int $x, float $residual): float => $residual - $seasons[$x % 7], range(0, $count - 1), $residuals);
        $parameters = $isSeasonal ? 8 : 2;
        $deviation = sqrt(array_sum(array_map(fn (float $error): float => $error ** 2, $errors)) / ($count - $parameters));

        return [
            'method' => $isSeasonal ? 'seasonal' : 'linear',
            'trend' => round($slope, 2),
            'points' => array_map(fn (int $x): array => $this->point(
                $intercept + $slope * $x + $seasons[$x % 7],
                self::Z * $deviation * sqrt(1 + 1 / $count + ($x - $xMean) ** 2 / $sxx),
            ), range($count, $count + $horizon - 1)),
        ];
    }

    /**
     * @return ForecastPoint
     */
    private function point(float $value, float $band): array
    {
        return [
            'value' => round(max(0.0, $value), 1),
            'lower' => round(max(0.0, $value - $band), 1),
            'upper' => round(max(0.0, $value + $band), 1),
        ];
    }
}
