<?php

declare(strict_types=1);

use App\Actions\CreateTomFriendship;
use App\Ai\Tools\CommunityStats;
use App\Contracts\Services\GitHubProvider;
use App\Enums\OrganizationRole;
use App\Models\Commit;
use App\Models\Message;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Http\Client\ConnectionException;
use Laravel\Ai\Tools\Request;

it('counts current onboarded members and signups within the requested rolling window', function (): void {
    $this->travelTo(now()->setDate(2026, 10, 19)->startOfDay());
    User::factory()->create(['created_at' => now()->subDays(7)]);
    User::factory()->create(['created_at' => now()->subDays(7)->subSecond()]);
    User::factory()->create(['created_at' => now()->subDays(30)->subSecond()]);
    User::factory()->notOnboarded()->create();
    User::factory()->create(['github_id' => CreateTomFriendship::GITHUB_ID]);
    User::factory()->create()->delete();

    $members = json_decode(new CommunityStats()->handle(new Request(['metric' => 'members'])), true);
    $week = json_decode(new CommunityStats()->handle(new Request(['metric' => 'new_members', 'days' => 7])), true);
    $month = json_decode(new CommunityStats()->handle(new Request(['metric' => 'new_members', 'days' => 30])), true);

    expect($members['count'])->toBe(3);
    expect($week)->toMatchArray(['count' => 1, 'population' => 3, 'since' => '2026-10-12T00:00:00+00:00', 'as_of' => '2026-10-19T00:00:00+00:00']);
    expect($month['count'])->toBe(2);
});

it('counts distinct public authors including replies but not private or moderated activity', function (): void {
    $author = User::factory()->create();
    $root = Commit::factory()->for($author)->create(['created_at' => now()->subDays(7)]);
    Commit::factory()->for($author)->create();
    Commit::factory()->for($root, 'parent')->create();
    Commit::factory()->create(['created_at' => now()->subDays(7)->subSecond()]);
    Commit::factory()->pending()->create();
    Commit::factory()->rejected()->create();
    Commit::factory()->create(['deleted_at' => now()]);
    Commit::factory()->for(User::factory()->notOnboarded())->create();
    Commit::factory()->for(User::factory()->create(['github_id' => CreateTomFriendship::GITHUB_ID]))->create();
    Message::factory()->create(['body' => 'Private activity is not public activity.']);

    $result = json_decode(new CommunityStats()->handle(new Request(['metric' => 'public_authors', 'days' => 7])), true);

    expect($result['count'])->toBe(2);
    expect($result['definition'])->toContain('Not online users');
});

it('counts inclusive signup dates and caches different start and end dates separately', function (): void {
    $this->travelTo(now('UTC')->setDate(2026, 10, 22)->startOfDay());
    User::factory()->count(7)->sequence(
        ['created_at' => '2026-10-18 23:59:59'],
        ['created_at' => '2026-10-19 00:00:00'],
        ['created_at' => '2026-10-20 00:00:00'],
        ['created_at' => '2026-10-20 23:59:59'],
        ['created_at' => '2026-10-21 00:00:00'],
        ['created_at' => '2026-10-21 12:00:00'],
        ['created_at' => '2026-10-21 23:59:59'],
    )->create();
    User::factory()->notOnboarded()->create(['created_at' => '2026-10-20 12:00:00']);
    User::factory()->create(['github_id' => CreateTomFriendship::GITHUB_ID, 'created_at' => '2026-10-20 12:00:00']);
    User::factory()->create(['created_at' => '2026-10-20 12:00:00'])->delete();

    foreach ([['2026-10-20', '2026-10-20', 2], ['2026-10-19', '2026-10-20', 3], ['2026-10-19', '2026-10-21', 6]] as [$start, $end, $count]) {
        $result = json_decode(new CommunityStats()->handle(new Request(['metric' => 'new_members', 'start_date' => $start, 'end_date' => $end])), true);

        expect($result)->toMatchArray(['count' => $count, 'population' => 7, 'start_date' => $start, 'end_date' => $end, 'timezone' => 'UTC']);
        expect($result['definition'])->toContain('Current onboarded members', 'inclusive UTC calendar dates');
    }

    $rolling = json_decode(new CommunityStats()->handle(new Request(['metric' => 'new_members', 'days' => 7])), true);
    expect($rolling)->toMatchArray(['count' => 7, 'start_date' => null, 'end_date' => null]);
});

it('uses posting dates rather than signup dates for distinct authors in a calendar range', function (): void {
    $this->travelTo(now('UTC')->setDate(2026, 10, 22)->startOfDay());
    $author = User::factory()->create();
    $root = Commit::factory()->for($author)->create(['created_at' => '2026-10-19 00:00:00']);
    Commit::factory()->for($author)->create(['created_at' => '2026-10-20 23:59:59']);
    Commit::factory()->for($root, 'parent')->create(['created_at' => '2026-10-20 23:59:59']);
    Commit::factory()->create(['created_at' => '2026-10-18 23:59:59']);
    Commit::factory()->create(['created_at' => '2026-10-21 00:00:00']);
    Commit::factory()->pending()->create(['created_at' => '2026-10-20 12:00:00']);

    $result = json_decode(new CommunityStats()->handle(new Request(['metric' => 'public_authors', 'start_date' => '2026-10-19', 'end_date' => '2026-10-20'])), true);

    expect($result)->toMatchArray(['count' => 2, 'since' => '2026-10-19T00:00:00+00:00', 'start_date' => '2026-10-19', 'end_date' => '2026-10-20', 'timezone' => 'UTC']);
    expect($result['definition'])->toContain('Not online users');
});

it('counts today only through the observation time', function (string $metric): void {
    $this->travelTo(now('UTC')->setDate(2026, 10, 21)->setTime(12, 0));
    $factory = $metric === 'new_members' ? User::factory() : Commit::factory();
    $factory->count(4)->sequence(
        ['created_at' => '2026-10-20 23:59:59'],
        ['created_at' => '2026-10-21 00:00:00'],
        ['created_at' => '2026-10-21 12:00:00'],
        ['created_at' => '2026-10-21 12:00:01'],
    )->create();

    $result = json_decode(new CommunityStats()->handle(new Request(['metric' => $metric, 'start_date' => '2026-10-21', 'end_date' => '2026-10-21'])), true);

    expect($result)->toMatchArray(['count' => 2, 'since' => '2026-10-21T00:00:00+00:00', 'as_of' => '2026-10-21T12:00:00+00:00']);
})->with(['new_members', 'public_authors']);

it('counts profile matches without guessing demographics or returning people', function (string $metric, string $term, array $matching, array $other): void {
    $column = $metric === 'location' ? 'location' : 'bio';
    foreach ([...$matching, ...$other] as $text) {
        User::factory()->create([$column => $text]);
    }

    User::factory()->notOnboarded()->create([$column => $matching[0]]);
    User::factory()->create(['github_id' => CreateTomFriendship::GITHUB_ID, $column => $matching[0]]);
    User::factory()->withBlankProfile()->create();

    $result = json_decode(new CommunityStats()->handle(new Request(['metric' => $metric, 'term' => $term])), true);

    expect($result['count'])->toBe(count($matching));
    expect($result['coverage'])->toContain('not a community-wide demographic count');
    expect(array_keys($result))->toBe(['metric', 'term', 'count', 'population', 'definition', 'coverage', 'matched_terms', 'since', 'start_date', 'end_date', 'timezone', 'as_of', 'source', 'cache_seconds']);
})->with([
    'country name, not nationality or a city guess' => ['location', ' Portugal ', ['Lisbon, PORTUGAL', 'Portugal'], ['Lisbon', 'Portuguese abroad', 'Portugalville']],
    'UK aliases' => ['location', 'United Kingdom', ['London, UK', 'U.K.', 'United Kingdom'], ['Ukraine', 'London']],
    'US aliases' => ['location', 'USA', ['New York, US', 'United States', 'U.S.A.'], ['Australia', 'New York']],
    'unicode place' => ['location', 'São Paulo', ['São Paulo, Brazil'], ['Sao Paulo', 'Brazil']],
    'PHP is a term not a substring' => ['bio_language', 'PHP', ['PHP developer', 'PHP, php, PHP'], ['phpunit fan', 'Developer']],
    'Java is not JavaScript' => ['bio_language', 'java', ['Java engineer'], ['JavaScript engineer']],
    'language aliases' => ['bio_language', 'javascript', ['JS / TypeScript', 'JavaScript'], ['Java', 'json']],
    'Go is not an ordinary verb' => ['bio_language', 'go', ['Golang developer', 'Go engineer'], ['Go outside', 'Django developer']],
    'C is not C++ or C sharp' => ['bio_language', 'c', ['C programmer'], ['C++ developer', 'C# developer']],
    'C++ punctuation' => ['bio_language', 'c++', ['C++ developer', 'cpp'], ['C developer', 'C#']],
    'C sharp punctuation' => ['bio_language', 'c#', ['C# developer', 'CSharp'], ['C developer', 'C++']],
]);

it('treats location patterns as literal text rather than SQL or regular expressions', function (string $term): void {
    User::factory()->create(['location' => 'Portugal']);

    $result = json_decode(new CommunityStats()->handle(new Request(['metric' => 'location', 'term' => $term])), true);

    expect($result['count'])->toBe(0);
})->with(['.*', '%%', "Portugal' OR 1=1 --"]);

it('groups public location strings without returning account identities or inferring countries', function (): void {
    User::factory()->count(3)->sequence(['location' => ' Portugal '], ['location' => 'PORTUGAL'], ['location' => 'Lisbon, Portugal'])->create();
    User::factory()->withBlankProfile()->create();
    User::factory()->create(['location' => '   ']);
    User::factory()->notOnboarded()->create(['location' => 'Portugal']);
    User::factory()->create(['location' => 'Portugal', 'github_id' => CreateTomFriendship::GITHUB_ID]);
    User::factory()->create(['location' => 'Portugal'])->delete();
    foreach (range(1, 10) as $number) {
        User::factory()->create(['location' => 'Z place '.sprintf('%02d', $number)]);
    }

    $result = json_decode(new CommunityStats()->handle(new Request(['metric' => 'locations'])), true);

    expect($result['population'])->toBe(15);
    expect($result['rows'])->toHaveCount(10);
    expect($result['rows'][0])->toBe(['location' => 'portugal', 'members' => 2]);
    expect($result['rows'][1])->toBe(['location' => 'lisbon, portugal', 'members' => 1]);
    expect($result['rows'][9])->toBe(['location' => 'z place 08', 'members' => 1]);
});

it('counts public GitHub organization members by stable identity not private local membership', function (): void {
    $public = User::factory()->create(['github_id' => '101']);
    $private = User::factory()->create(['github_id' => '102']);
    User::factory()->notOnboarded()->create(['github_id' => '103']);
    User::factory()->create(['github_id' => '104'])->delete();
    User::factory()->create(['github_id' => CreateTomFriendship::GITHUB_ID]);
    Organization::factory()->create(['handle' => 'example'])->members()->attach($private, ['role' => OrganizationRole::Member]);
    $this->mock(GitHubProvider::class)->shouldReceive('publicOrganizationMemberIds')->with('example')
        ->andReturn(['101', '103', '104', '999', CreateTomFriendship::GITHUB_ID]);

    $result = new CommunityStats()->handle(new Request(['metric' => 'github_organization', 'term' => 'EXAMPLE']));

    expect(json_decode($result, true))->toMatchArray(['count' => 1, 'population' => 2, 'term' => 'example']);
    expect($result)->not->toContain($public->id, $public->handle, $private->handle, '"101"');
});

it('does not turn unavailable organization statistics into zero or cache the failure', function (bool $throws): void {
    $provider = $this->mock(GitHubProvider::class);
    $expectation = $provider->shouldReceive('publicOrganizationMemberIds')->with('example')->once();
    if ($throws) {
        $expectation->andThrow(new ConnectionException);
    } else {
        $expectation->andReturnNull();
    }

    expect(new CommunityStats()->handle(new Request(['metric' => 'github_organization', 'term' => 'example'])))->toContain('unavailable');

    $this->mock(GitHubProvider::class)->shouldReceive('publicOrganizationMemberIds')->with('example')->once()->andReturn([]);
    $result = json_decode(new CommunityStats()->handle(new Request(['metric' => 'github_organization', 'term' => 'example'])), true);
    expect($result['count'])->toBe(0);
})->with([false, true]);

it('counts public posts and replies rather than authors and respects calendar boundaries', function (): void {
    $this->travelTo(now('UTC')->setDate(2026, 10, 22)->setTime(12, 0));
    $author = User::factory()->create();
    $root = Commit::factory()->for($author)->create(['created_at' => '2026-10-19 23:59:59']);
    Commit::factory()->for($author)->for($root, 'parent')->create(['created_at' => '2026-10-20 00:00:00']);
    Commit::factory()->for($author)->create(['created_at' => '2026-10-20 23:59:59']);
    Commit::factory()->for($author)->create(['author_id' => Organization::factory()->create()->id, 'created_at' => '2026-10-21 12:00:00']);
    Commit::factory()->for($author)->create(['created_at' => now()]);
    Commit::factory()->for($author)->create(['created_at' => now()->addSecond()]);
    Commit::factory()->pending()->create();
    Commit::factory()->rejected()->create();
    Commit::factory()->create(['deleted_at' => now()]);
    Commit::factory()->for(User::factory()->notOnboarded())->create();
    Commit::factory()->for(User::factory()->create(['github_id' => CreateTomFriendship::GITHUB_ID]))->create();

    foreach ([['2026-10-20', '2026-10-20', 2], ['2026-10-20', '2026-10-21', 3], ['2026-10-22', '2026-10-22', 1], [null, null, 5]] as [$start, $end, $count]) {
        $arguments = $start === null ? [] : ['start_date' => $start, 'end_date' => $end];
        $result = json_decode(new CommunityStats()->handle(new Request(['metric' => 'commits', ...$arguments])), true);

        expect($result)->toMatchArray(['count' => $count, 'start_date' => $start, 'end_date' => $end, 'timezone' => 'UTC']);
        expect($result['definition'])->toContain('posts and replies', 'including organization posts');
    }
});

it('expires aggregate caches and preserves the original observation time while cached', function (): void {
    $user = User::factory()->create();
    $request = new Request(['metric' => 'members']);
    $first = new CommunityStats()->handle($request);
    $user->delete();
    $this->travel(4)->minutes();

    expect(new CommunityStats()->handle($request))->toBe($first);

    $this->travel(2)->minutes();
    $fresh = json_decode(new CommunityStats()->handle($request), true);
    expect($fresh['count'])->toBe(0);
    expect($fresh['as_of'])->toBe(now()->toIso8601String());
});

it('refuses unsupported metrics, periods and combined filters', function (array $arguments, string $error): void {
    expect(new CommunityStats()->handle(new Request($arguments)))->toContain($error);
})->with([
    [['metric' => 'email'], 'Unsupported metric'],
    [['metric' => 'new_members', 'days' => 365], 'Unsupported metric or period'],
    [['metric' => 'location', 'term' => ''], 'Provide one location'],
    [['metric' => 'location', 'term' => str_repeat('x', 81)], 'Provide one location'],
    [['metric' => 'bio_language', 'term' => 'english'], 'Unsupported language'],
    [['metric' => 'members', 'term' => 'Portugal'], 'Combined filters'],
    [['metric' => 'follower_ranking'], 'Unsupported metric'],
    [['metric' => 'github_organization', 'term' => ''], 'Provide one GitHub organization'],
    [['metric' => 'github_organization', 'term' => '../users/example'], 'Provide one GitHub organization'],
    [['metric' => 'github_organization', 'term' => str_repeat('x', 40)], 'Provide one GitHub organization'],
    [['metric' => 'commits', 'days' => 7], 'omit days'],
]);

it('bounds community queries per reply', function (): void {
    $tool = new CommunityStats;
    $request = new Request(['metric' => 'members']);
    $tool->handle($request);
    $tool->handle($request);

    expect($tool->handle($request))->toContain('budget exhausted');
});

it('refuses invalid or conflicting calendar ranges instead of returning rolling counts', function (array $arguments, string $error): void {
    $this->travelTo(now('UTC')->setDate(2026, 10, 21)->startOfDay());

    expect(new CommunityStats()->handle(new Request(['metric' => 'new_members', ...$arguments])))->toContain($error);
})->with([
    'missing end' => [['start_date' => '2026-10-20'], 'Provide both'],
    'missing start' => [['end_date' => '2026-10-20'], 'Provide both'],
    'empty dates' => [['start_date' => '', 'end_date' => ''], 'Provide both'],
    'null dates' => [['start_date' => null, 'end_date' => null], 'Provide both'],
    'invalid calendar day' => [['start_date' => '2026-02-30', 'end_date' => '2026-03-01'], 'Provide both'],
    'unsupported year zero' => [['start_date' => '0000-01-01', 'end_date' => '2026-10-20'], 'Provide both'],
    'timestamp not a date' => [['start_date' => '2026-10-20T12:00:00Z', 'end_date' => '2026-10-20'], 'Provide both'],
    'reversed dates' => [['start_date' => '2026-10-20', 'end_date' => '2026-10-19'], 'Provide both'],
    'future date' => [['start_date' => '2026-10-20', 'end_date' => '2026-10-22'], 'Provide both'],
    'rolling and calendar' => [['days' => 7, 'start_date' => '2026-10-20', 'end_date' => '2026-10-20'], 'Omit days'],
    'historical population' => [['metric' => 'members', 'start_date' => '2026-10-20', 'end_date' => '2026-10-20'], 'Date ranges are only'],
    'demographic range' => [['metric' => 'location', 'term' => 'Portugal', 'start_date' => '2026-10-20', 'end_date' => '2026-10-20'], 'Date ranges are only'],
    'combined signup filter' => [['term' => 'Portugal', 'start_date' => '2026-10-20', 'end_date' => '2026-10-20'], 'Combined filters'],
]);
