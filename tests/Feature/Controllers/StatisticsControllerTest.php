<?php

declare(strict_types=1);

use App\Http\Middleware\HandleInertiaRequests;
use App\Models\Commit;
use App\Models\User;
use Illuminate\Support\Facades\DB;

it('renders public statistics for guests', function (): void {
    Commit::factory()->create();

    $this->get(route('statistics.show'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('statistics/show')
            ->where('statistics.users', 1)
            ->where('statistics.commits', 1)
            ->has('statistics.activity', 30)
            ->has('statistics.updatedAt')
            ->missing('statistics.pendingModerationCount')
            ->missing('statistics.queues'));
});

it('shows the same public counts to signed in authors regardless of their private visibility', function (): void {
    $viewer = User::factory()->create();
    $blocked = User::factory()->create();
    $viewer->blocking()->attach($blocked);
    Commit::factory()->for($blocked)->create();
    Commit::factory()->for($viewer)->pending()->create();
    Commit::factory()->for($viewer)->rejected()->create();

    $this->actingAs($viewer)->get(route('statistics.show'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('statistics/show')
            ->where('statistics.users', 2)
            ->where('statistics.commits', 1));

    auth()->logout();

    $this->get(route('statistics.show'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('statistics.users', 2)
            ->where('statistics.commits', 1));
});

it('does not recompute statistics during unrelated partial visits', function (): void {
    $viewer = User::factory()->create();

    DB::flushQueryLog();
    DB::enableQueryLog();

    $this->actingAs($viewer)->getJson(route('statistics.show'), [
        'X-Inertia' => 'true',
        'X-Inertia-Version' => (string) new HandleInertiaRequests()->version(request()),
        'X-Inertia-Partial-Component' => 'statistics/show',
        'X-Inertia-Partial-Data' => 'auth',
    ])->assertOk()
        ->assertJsonPath('component', 'statistics/show')
        ->assertJsonMissingPath('props.statistics');

    $queries = array_column(DB::getQueryLog(), 'query');
    DB::disableQueryLog();

    expect(array_filter($queries, fn (string $sql): bool => str_starts_with($sql, 'select count(*) as aggregate from "commits"')
        || str_starts_with($sql, 'select count(*) as aggregate from "users"')
        || str_contains($sql, 'group by "bucket"')))->toBe([]);
});

it('describes the statistics page to search engines and link previews', function (): void {
    $this->get(route('statistics.show'))
        ->assertOk()
        ->assertSee('<title>Statistics - /dev/null</title>', false)
        ->assertSee('<link rel="canonical" href="'.route('statistics.show').'">', false)
        ->assertSee('"@type":"WebPage"', false);
});
