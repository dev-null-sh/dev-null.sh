<?php

declare(strict_types=1);

use App\Enums\Feature;
use App\Models\Guild;
use App\Models\User;
use App\Queries\GuildMentionsQuery;
use App\Services\Features;
use Illuminate\Support\Facades\DB;

it('looks up only real prose hashtags across all texts in one badge-only query', function (): void {
    $viewer = User::factory()->create();
    resolve(Features::class)->update(Feature::Guilds, true, false);
    Guild::factory()->create(['handle' => 'rust', 'name' => 'Rust', 'emoji' => '🦀']);
    Guild::factory()->create(['handle' => 'café', 'name' => 'Café']);
    Guild::factory()->create(['handle' => 'code']);
    Guild::factory()->create(['handle' => 'unrelated']);
    DB::enableQueryLog();
    DB::flushQueryLog();

    $mentions = resolve(GuildMentionsQuery::class)->get((function (): Generator {
        yield '#RUST #missing `#code` https://example.com/#code';
        yield "#rust #CAFÉ\n```\n#code\n```";
    })(), $viewer);

    expect($mentions)->toHaveKeys(['rust', 'café'])->toHaveCount(2)
        ->and($mentions['rust'])->toBeInstanceOf(Guild::class)
        ->and($mentions['rust']->handle)->toBe('rust');
    $queries = collect(DB::getQueryLog())->filter(fn (array $query): bool => str_contains($query['query'], 'from "guilds"'));
    expect($queries)->toHaveCount(1)
        ->and($queries->sole()['query'])->not->toContain('select *', 'description', 'members_count')
        ->and($queries->sole()['bindings'])->toBe(['rust', 'missing', 'café']);
});

it('returns no badges or guild queries for guests and unavailable guilds', function (?string $audience): void {
    $viewer = $audience === null ? null : User::factory()->create();
    resolve(Features::class)->update(Feature::Guilds, $audience !== 'disabled', $audience === 'admin');
    Guild::factory()->create(['handle' => 'rust']);
    DB::enableQueryLog();
    DB::flushQueryLog();

    $mentions = resolve(GuildMentionsQuery::class)->get(['#rust'], $viewer);

    expect($mentions)->toBe([])
        ->and(collect(DB::getQueryLog())->filter(fn (array $query): bool => str_contains($query['query'], 'from "guilds"')))->toHaveCount(0);
})->with(['guest' => null, 'disabled' => 'disabled', 'admin preview' => 'admin']);

it('does not query guilds when there are no prose hashtags', function (): void {
    $viewer = User::factory()->create();
    resolve(Features::class)->update(Feature::Guilds, true, false);
    DB::enableQueryLog();
    DB::flushQueryLog();

    $mentions = resolve(GuildMentionsQuery::class)->get(['plain text', '`#rust`'], $viewer);

    expect($mentions)->toBe([])
        ->and(collect(DB::getQueryLog())->filter(fn (array $query): bool => str_contains($query['query'], 'from "guilds"')))->toHaveCount(0);
});

it('keeps every badge when a retained log exceeds the postgres binding limit', function (): void {
    $viewer = User::factory()->create();
    resolve(Features::class)->update(Feature::Guilds, true, false);
    Guild::factory()->create(['handle' => 'guild0']);
    Guild::factory()->create(['handle' => 'guild65535']);
    $texts = (function (): Generator {
        for ($index = 0; $index < 65536; $index++) {
            yield '#guild'.$index;
        }
    })();

    $mentions = resolve(GuildMentionsQuery::class)->get($texts, $viewer);

    expect($mentions)->toHaveKeys(['guild0', 'guild65535'])->toHaveCount(2);
});
