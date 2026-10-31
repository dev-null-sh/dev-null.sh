<?php

declare(strict_types=1);

use App\Models\Commit;
use App\Models\Headline;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Sleep;

it('shares headlines across commits and exposes only their names', function (): void {
    $first = Commit::factory()->create();
    $second = Commit::factory()->create();
    $laravel = Headline::factory()->create(['name' => 'Laravel adds a new feature']);
    $php = Headline::factory()->create(['name' => 'PHP gets faster']);

    $first->headlines()->attach([$laravel->id, $php->id]);
    $laravel->commits()->attach($second);

    expect($first->headlines->pluck('name')->sort()->values()->all())->toBe(['Laravel adds a new feature', 'PHP gets faster'])
        ->and($second->headlines->sole()->is($laravel))->toBeTrue()
        ->and($laravel->commits->modelKeys())->toBe([$first->id, $second->id])
        ->and($laravel->load('commits')->toArray())->toBe(['name' => 'Laravel adds a new feature']);
});

it('stores each headline name only once', function (): void {
    Headline::factory()->create(['name' => 'Laravel adds a new feature']);

    expect(fn () => Headline::factory()->create(['name' => 'Laravel adds a new feature']))->toThrow(UniqueConstraintViolationException::class);
});

it('links a headline to a commit only once', function (): void {
    $commit = Commit::factory()->create();
    $headline = Headline::factory()->create();
    $commit->headlines()->attach($headline);

    expect(fn () => $commit->headlines()->attach($headline))->toThrow(UniqueConstraintViolationException::class);
});

it('prunes headlines fourteen days after their last new link without deleting commits', function (): void {
    $this->travelTo(now()->setDate(2026, 10, 31)->startOfDay());
    $old = Headline::factory()->create(['created_at' => '2026-08-01']);
    $boundary = Headline::factory()->create(['created_at' => '2026-08-01']);
    $recent = Headline::factory()->create(['created_at' => '2026-08-01']);
    $unused = Headline::factory()->create(['created_at' => '2026-10-17']);
    $new = Headline::factory()->create(['created_at' => '2026-10-17 00:00:01']);
    $commit = Commit::factory()->create();
    $commit->headlines()->attach($old, ['created_at' => '2026-10-16']);
    $commit->headlines()->attach($boundary, ['created_at' => '2026-10-17']);
    $commit->headlines()->attach($recent, ['created_at' => '2026-10-16']);
    $recent->commits()->attach(Commit::factory()->create(), ['created_at' => '2026-10-17 00:00:01']);
    $old->update(['name' => 'An edit does not renew the TTL']);

    $this->artisan('model:prune', ['--model' => [Headline::class]])->assertSuccessful();

    $this->assertModelMissing($old);
    $this->assertModelMissing($boundary);
    $this->assertModelMissing($unused);
    $this->assertModelExists($recent);
    $this->assertModelExists($new);
    $this->assertModelExists($commit);
    expect($commit->headlines->modelKeys())->toBe([$recent->id]);
});

it('keeps headlines recently linked to a soft deleted commit', function (): void {
    $headline = Headline::factory()->create(['created_at' => now()->subMonths(2)]);
    $commit = Commit::factory()->create();
    $headline->commits()->attach($commit);
    $commit->delete();

    $this->artisan('model:prune', ['--model' => [Headline::class]])->assertSuccessful();

    $this->assertModelExists($headline);
});

it('skips pruning while processing owns the headline lock', function (): void {
    $this->freezeTime();
    Sleep::fake(syncWithCarbon: true);
    $headline = Headline::factory()->create(['created_at' => now()->subDays(15)]);
    $lock = Cache::lock(Headline::PROCESSING_LOCK, Headline::PROCESSING_LOCK_SECONDS);
    $lock->get();

    expect(new Headline()->pruneAll())->toBe(0);
    $this->assertModelExists($headline);
    expect($lock->isOwnedByCurrentProcess())->toBeTrue();

    $lock->release();
    expect(new Headline()->pruneAll())->toBe(1);
    $this->assertModelMissing($headline);
});

it('does not renew a headline when syncing an existing link', function (): void {
    $headline = Headline::factory()->create(['created_at' => now()->subMonths(2)]);
    $commit = Commit::factory()->create();
    $commit->headlines()->attach($headline, ['created_at' => now()->subMonths(2)]);

    $commit->headlines()->syncWithoutDetaching([$headline->id]);
    $this->artisan('model:prune', ['--model' => [Headline::class]])->assertSuccessful();

    $this->assertModelMissing($headline);
});

it('removes a permanently deleted commits links but keeps shared headlines', function (): void {
    $headline = Headline::factory()->create();
    $commit = Commit::factory()->create();
    $other = Commit::factory()->create();
    $headline->commits()->attach([$commit->id, $other->id]);

    $commit->forceDelete();

    $this->assertModelExists($headline);
    expect($headline->commits->modelKeys())->toBe([$other->id]);
});
