<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\Feature;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Only explicit overrides are stored; guilds default to an admin preview, other features to everyone.
 */
final readonly class Features
{
    private const string CACHE_KEY = 'features:audiences';

    public function enabled(Feature $feature): bool
    {
        return $this->switched()[$feature->value]['enabled'] ?? true;
    }

    public function adminOnly(Feature $feature): bool
    {
        return $this->switched()[$feature->value]['admin_only'] ?? $feature === Feature::Guilds;
    }

    public function available(Feature $feature, ?User $user): bool
    {
        return $this->enabled($feature) && (! $this->adminOnly($feature) || ($user?->isAdmin() ?? false));
    }

    /** @return list<array{name: string, enabled: bool, admin_only: bool}> */
    public function all(): array
    {
        return array_map(fn (Feature $feature): array => [
            'name' => $feature->value,
            'enabled' => $this->enabled($feature),
            'admin_only' => $this->adminOnly($feature),
        ], Feature::cases());
    }

    public function update(Feature $feature, bool $enabled, ?bool $adminOnly = null): void
    {
        DB::table('features')->upsert(
            [['name' => $feature->value, 'enabled' => $enabled, 'admin_only' => $adminOnly ?? $this->adminOnly($feature), 'created_at' => now(), 'updated_at' => now()]],
            ['name'],
            ['enabled', 'admin_only', 'updated_at'],
        );

        Cache::memo()->forget(self::CACHE_KEY);
    }

    /** @return array<string, array{enabled: bool, admin_only: bool}> */
    private function switched(): array
    {
        /** @var array<string, array{enabled: bool, admin_only: bool}> $switched */
        $switched = Cache::memo()->rememberForever(self::CACHE_KEY, function (): array {
            /** @var Collection<int, object{name: string, enabled: bool, admin_only: bool}> $rows */
            $rows = DB::table('features')->get(['name', 'enabled', 'admin_only']);

            return $rows->mapWithKeys(fn (object $row): array => [$row->name => ['enabled' => (bool) $row->enabled, 'admin_only' => (bool) $row->admin_only]])->all();
        });

        return $switched;
    }
}
