<?php

declare(strict_types=1);

use App\Actions\CreateTomFriendship;
use App\Ai\Agents\Tom;
use App\Ai\Tools\SearchCommits;
use App\Ai\Tools\SearchKnowledgeBase;
use App\Enums\CommitStatus;
use App\Enums\Feature;
use App\Models\Commit;
use App\Models\KnowledgeBaseSection;
use App\Models\User;
use App\Services\Features;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Laravel\Ai\Embeddings;
use Laravel\Ai\Messages\Message;
use Laravel\Ai\Tools\Request as ToolRequest;

it('uses the reserved bot handle in its instructions after the real tom joins', function (): void {
    User::factory()->create(['username' => 'devnull-tom', 'github_id' => CreateTomFriendship::GITHUB_ID]);
    $owner = User::factory()->create(['username' => 'tom']);
    $question = Commit::factory()->for($owner)->create(['body' => '@devnull-tom hello']);

    $instructions = new Tom($question)->instructions();

    expect($instructions)->toContain('You are Tom (@devnull-tom)', 'The latest message is by @tom.');
});

it('executes semantic, history and statistics tools through OpenCode and sends only public evidence', function (): void {
    config(['ai.providers.opencode.key' => 'test-opencode', 'services.openai.key' => 'test']);
    resolve(Features::class)->update(Feature::Translations, false);
    Embeddings::fake([[embeddingVector(0)], [embeddingVector(0)]]);
    $public = Commit::factory()->for(User::factory()->state(['location' => 'Portugal']))->create(['body' => 'Public database topic', 'embedding' => embeddingVector(0)]);
    Commit::factory()->pending()->create(['body' => 'Pending secret', 'embedding' => embeddingVector(0)]);
    Commit::factory()->rejected()->create(['body' => 'Rejected secret', 'embedding' => embeddingVector(0)]);
    Commit::factory()->create(['body' => 'Deleted secret', 'deleted_at' => now(), 'embedding' => embeddingVector(0)]);
    KnowledgeBaseSection::factory()->create(['headings' => ['Stack'], 'content' => 'The platform uses PostgreSQL.', 'embedding' => embeddingVector(0)]);
    $question = Commit::factory()->create(['body' => '@tom explain the database and find posts about it']);
    User::factory()->create(['github_id' => '123', 'username' => 'alice-local']);
    Http::fake([
        'api.openai.com/v1/decisions' => Http::response(relevanceResponse([true])),
        'api.github.com/repos/nunomaduro/dev-null.sh/languages' => Http::response(['PHP' => 123]),
        'api.github.com/search/issues*' => Http::response(['incomplete_results' => false, 'items' => []]),
        'api.github.com/search/commits*' => Http::response(['incomplete_results' => false, 'items' => [[
            'html_url' => 'https://github.com/nunomaduro/dev-null.sh/commit/abc',
            'author' => ['login' => 'alice', 'id' => 123], 'committer' => ['login' => 'not-the-author'],
            'commit' => ['message' => "feat: database search\n\nsecret trailer", 'author' => ['email' => 'secret@example.com', 'date' => '2025-01-01T00:00:00Z']],
        ]]]),
        'opencode.ai/zen/v1/chat/completions' => Http::sequence()->push([
            'choices' => [['finish_reason' => 'tool_calls', 'message' => ['role' => 'assistant', 'content' => '', 'tool_calls' => [
                ['id' => 'kb', 'type' => 'function', 'function' => ['name' => 'SearchKnowledgeBase', 'arguments' => '{"query":"platform database"}']],
                ['id' => 'posts', 'type' => 'function', 'function' => ['name' => 'SearchCommits', 'arguments' => '{"query":"database"}']],
                ['id' => 'repo', 'type' => 'function', 'function' => ['name' => 'RepositoryInsights', 'arguments' => '{"metric":"languages"}']],
                ['id' => 'history', 'type' => 'function', 'function' => ['name' => 'SearchRepositoryHistory', 'arguments' => '{"query":"database search"}']],
                ['id' => 'community', 'type' => 'function', 'function' => ['name' => 'CommunityStats', 'arguments' => '{"metric":"location","term":"Portugal"}']],
            ]]]],
        ])->push([
            'choices' => [['finish_reason' => 'stop', 'message' => ['role' => 'assistant', 'content' => 'PostgreSQL, with a side of rust. :-D']]],
        ]),
    ]);

    $response = new Tom($question)->prompt($question->body);

    expect($response->text)->toBe('PostgreSQL, with a side of rust. :-D');
    Http::assertSent(function (Request $request) use ($public): bool {
        if ($request->url() !== 'https://opencode.ai/zen/v1/chat/completions') {
            return false;
        }

        $tools = collect($request['messages'])->where('role', 'tool')->pluck('content')->implode(' ');

        return $request['model'] === 'deepseek-v4.1-flash'
            && $request->hasHeader('Authorization', 'Bearer test-opencode')
            && $request['thinking'] === ['type' => 'disabled']
            && str_contains($tools, 'The platform uses PostgreSQL.')
            && str_contains($tools, 'Public database topic')
            && str_contains($tools, '"languages":{"PHP":123}')
            && str_contains($tools, '"commit_author":"alice"')
            && str_contains($tools, '"mention":"@alice-local"')
            && str_contains(stripslashes($tools), 'https://github.com/nunomaduro/dev-null.sh/commit/abc')
            && ! str_contains($tools, 'not-the-author')
            && str_contains($tools, '"count":1')
            && str_contains($tools, 'Not nationality or verified residence')
            && str_contains(stripslashes($tools), route('commit.show', [$public->user, $public]))
            && ! str_contains($tools, 'secret')
            && ! str_contains($tools, 'embedding')
            && ! str_contains($tools, 'github_id');
    });
});

it('remembers only the public ancestor branch with the correct speakers', function (): void {
    $tom = User::factory()->create(['username' => 'tom', 'github_id' => CreateTomFriendship::GITHUB_ID]);
    $user = User::factory()->create(['username' => 'alice']);
    $root = Commit::factory()->for($user)->create(['body' => 'What is this place?']);
    $answer = Commit::factory()->for($root, 'parent')->for($tom)->create(['body' => 'A developer community.']);
    Commit::factory()->for($root, 'parent')->create(['body' => 'A different branch.']);
    Commit::factory()->create(['body' => 'An unrelated thread.']);
    $newcomer = User::factory()->create(['username' => 'bob']);
    $question = Commit::factory()->for($answer, 'parent')->for($newcomer)->create(['body' => '@tom and who started it?']);

    expect(new Tom($question)->messages()->map(fn (Message $message): array => [$message->role->value, $message->content])->all())->toBe([
        ['user', '@alice: What is this place?'],
        ['assistant', '@tom: A developer community.'],
    ]);
    expect(new Tom($question)->instructions())->toContain('The latest message is by @bob.')
        ->not->toContain('@alice', '2012 RAM');

    $root->update(['status' => CommitStatus::Rejected]);
    expect(new Tom($question)->messages()->pluck('content')->all())->toBe(['@tom: A developer community.']);
    $answer->delete();
    expect(new Tom($question)->messages())->toBeEmpty();
});

it('bounds history by both posts and characters and signals a nearly full window', function (int $length, int $expected): void {
    $user = User::factory()->create(['username' => 'alice']);
    $parent = null;
    for ($index = 0; $index < 15; $index++) {
        $parent = Commit::factory()->for($user)->create(['body' => str_repeat('x', $length), 'parent_id' => $parent?->id]);
    }

    $question = Commit::factory()->for($parent, 'parent')->for($user)->create(['body' => '@tom '.str_repeat('y', 995)]);
    $agent = new Tom($question);

    expect($agent->messages())->toHaveCount($expected);
    expect($agent->messages()->sum(fn (Message $message): int => mb_strlen($message->content)) + mb_strlen($question->body))->toBeLessThanOrEqual(12000);
    expect($agent->instructions())->toContain('2012 RAM');
})->with(['message limit' => [10, 12], 'character limit' => [3500, 3]]);

it('caps tool results and search calls for each reply', function (): void {
    Embeddings::fake([[embeddingVector(0)]]);
    KnowledgeBaseSection::factory()->count(4)->create(['content' => str_repeat('k', 4000), 'embedding' => embeddingVector(0)]);
    $tool = new SearchKnowledgeBase;
    $request = new ToolRequest(['query' => 'origins']);

    $results = json_decode($tool->handle($request), true);
    expect($results)->toHaveCount(3);
    expect(array_keys($results[0]))->toBe(['headings', 'content']);
    expect(mb_strlen($results[0]['content']))->toBe(3000);

    $tool->handle($request);
    expect($tool->handle($request))->toContain('budget exhausted');

    $commits = new SearchCommits;
    expect($commits->handle(new ToolRequest(['query' => 'origins'])))->toBe('[]');
    $commits->handle($request);
    expect($commits->handle($request))->toContain('budget exhausted');
});
