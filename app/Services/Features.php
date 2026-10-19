<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\Feature;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Every feature is on until an administrator switches it off, so only the switched off ones are stored.
 */
final readonly class Features
{
    private const string CACHE_KEY = 'features:disabled';

    public function enabled(Feature $feature): bool
    {
        return ! in_array($feature->value, $this->disabled(), true);
    }

    /** @return list<array{name: string, enabled: bool}> */
    public function all(): array
    {
        return array_map(fn (Feature $feature): array => [
            'name' => $feature->value,
            'enabled' => $this->enabled($feature),
        ], Feature::cases());
    }

    public function update(Feature $feature, bool $enabled): void
    {
        DB::table('features')->upsert(
            [['name' => $feature->value, 'enabled' => $enabled, 'created_at' => now(), 'updated_at' => now()]],
            ['name'],
            ['enabled', 'updated_at'],
        );

        Cache::memo()->forget(self::CACHE_KEY);
    }

    /** @return list<string> */
    private function disabled(): array
    {
        /** @var list<string> $disabled */
        $disabled = Cache::memo()->rememberForever(self::CACHE_KEY, fn (): array => DB::table('features')
            ->where('enabled', false)
            ->pluck('name')
            ->values()
            ->all());

        return $disabled;
    }
}
