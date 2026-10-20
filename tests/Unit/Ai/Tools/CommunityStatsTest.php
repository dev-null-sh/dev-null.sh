<?php

declare(strict_types=1);

use App\Actions\CreateTomFriendship;
use App\Ai\Tools\CommunityStats;
use App\Models\Commit;
use App\Models\Message;
use App\Models\User;
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

it('counts profile matches without guessing demographics or returning people', function (string $metric, string $term, array $matching, array $other): void {
    $column = $metric === 'location' ? 'location' : 'bio';
    foreach ([...$matching, ...$other] as $text) {
        User::factory()->create([$column => $text]);
    }

    User::factory()->notOnboarded()->create([$column => $matching[0]]);
    User::factory()->create(['github_id' => CreateTomFriendship::GITHUB_ID, $column => $matching[0]]);
    User::factory()->withoutProfile()->create();

    $result = json_decode(new CommunityStats()->handle(new Request(['metric' => $metric, 'term' => $term])), true);

    expect($result['count'])->toBe(count($matching));
    expect($result['coverage'])->toContain('not a community-wide demographic count');
    expect(array_keys($result))->toBe(['metric', 'term', 'count', 'population', 'definition', 'coverage', 'matched_terms', 'since', 'as_of', 'source', 'cache_seconds']);
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
]);

it('bounds community queries per reply', function (): void {
    $tool = new CommunityStats;
    $request = new Request(['metric' => 'members']);
    $tool->handle($request);
    $tool->handle($request);

    expect($tool->handle($request))->toContain('budget exhausted');
});
