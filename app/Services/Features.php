<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\Feature;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * A feature keeps its default until an administrator switches it, so only the switched ones are stored.
 */
final readonly class Features
{
    private const string CACHE_KEY = 'features:switched';

    public function enabled(Feature $feature): bool
    {
        return $this->switched()[$feature->value] ?? $feature->isEnabledByDefault();
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

    /** @return array<string, bool> */
    private function switched(): array
    {
        /** @var array<string, bool> $switched */
        $switched = Cache::memo()->rememberForever(self::CACHE_KEY, fn (): array => DB::table('features')
            ->pluck('enabled', 'name')
            ->map(fn (mixed $enabled): bool => (bool) $enabled)
            ->all());

        return $switched;
    }
}
