<?php

declare(strict_types=1);

use App\Actions\CreateTomFriendship;
use App\Ai\Agents\Tom;
use App\Ai\Agents\TomToolDecision;
use App\Models\Commit;
use App\Models\User;
use App\Queries\CommitAncestorsQuery;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\JsonSchemaTypeFactory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Events\StepCompleted;
use Laravel\Ai\Tools\Request;

require dirname(__DIR__, 2).'/vendor/autoload.php';

$snapshot = $argv[1];
require $snapshot.'/app/Ai/Agents/Tom.php';
foreach (glob($snapshot.'/app/Ai/Tools/*.php') as $file) {
    require $file;
}

$hasDecision = file_exists($snapshot.'/app/Ai/Agents/TomToolDecision.php');
if ($hasDecision) {
    require $snapshot.'/app/Ai/Agents/TomToolDecision.php';
}

$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
config([
    'database.default' => 'pgsql',
    'database.connections.pgsql.host' => '127.0.0.1',
    'database.connections.pgsql.database' => 'testing',
    'database.connections.pgsql.url' => null,
    'cache.default' => 'array',
    'logging.default' => 'null',
    'ai.providers.openai.store' => false,
]);
DB::statement('CREATE TEMP TABLE users (github_id text, username text)');
DB::statement('SET search_path TO pg_temp');
view()->getFinder()->prependLocation($snapshot.'/resources/views');
Http::preventStrayRequests();
Http::allowStrayRequests([
    mb_rtrim(config('ai.providers.openai.url'), '/').'/*',
    mb_rtrim(config('ai.providers.opencode.url'), '/').'/*',
]);

$case = json_decode(stream_get_contents(STDIN), true, flags: JSON_THROW_ON_ERROR);
$commit = Commit::factory()->make(['body' => $case['prompt'], 'parent_id' => null, 'user_id' => 'benchmark-user']);
$commit->setRelation('user', User::factory()->make(['username' => 'benchmark-user']));
$ancestors = collect($case['history'] ?? [])->map(function (array $message): Commit {
    $commit = Commit::factory()->make(['body' => $message['content'], 'deleted_at' => null, 'user_id' => 'benchmark-user']);
    $commit->setRelation('user', User::factory()->make([
        'username' => $message['role'] === 'assistant' ? 'tom' : 'benchmark-user',
        'github_id' => $message['role'] === 'assistant' ? CreateTomFriendship::GITHUB_ID : 'benchmark-user',
    ]));

    return $commit;
});
$query = Mockery::mock(new CommitAncestorsQuery);
$query->shouldReceive('get')->andReturn($ancestors);
$app->instance(CommitAncestorsQuery::class, $query);

$steps = [];
Event::listen(StepCompleted::class, function (StepCompleted $event) use (&$steps): void {
    $steps[] = [
        'agent' => class_basename($event->agent),
        'model' => $event->model,
        'milliseconds' => $event->time,
        'calls' => collect($event->response->toolCalls)->map(fn ($call): array => [
            'tool' => $call->name,
            'arguments' => $call->arguments,
        ])->all(),
    ];
});

$agent = $hasDecision ? new TomToolDecision(new Tom($commit)->messages()->all()) : new Tom($commit);
$tools = collect($agent->tools())->map(fn (Tool $tool): Tool => new class($tool, $case['results'][class_basename($tool)] ?? ['[]']) implements Tool
{
    private int $calls = 0;

    /** @param list<string> $results */
    public function __construct(private readonly Tool $tool, private array $results) {}

    public function name(): string
    {
        return class_basename($this->tool);
    }

    public function description(): string
    {
        return (string) $this->tool->description();
    }

    /** @return array<string, mixed> */
    public function schema(JsonSchema $schema): array
    {
        return $this->tool->schema($schema);
    }

    public function handle(Request $request): string
    {
        $index = $this->calls++;

        return $index >= 2
            ? 'Tool budget exhausted. Answer using the evidence already retrieved.'
            : $this->results[min($index, count($this->results) - 1)];
    }
})->all();
$agent->withTools($tools);
$schemas = collect($tools)->mapWithKeys(fn (Tool $tool): array => [
    $tool->name() => collect($tool->schema(new JsonSchemaTypeFactory))->map(fn ($type): array => $type->toArray())->all(),
])->all();
$started = hrtime(true);
$selectionMs = null;
$executed = [];
$error = null;
$answer = null;
try {
    $response = $agent->prompt($case['prompt']);
    $selectionMs = (hrtime(true) - $started) / 1e6;
    $executed = $response->toolResults->map(fn ($result): array => [
        'tool' => $result->name,
        'arguments' => $result->arguments,
    ])->all();
    $answer = $hasDecision
        ? new Tom($commit, array_values($response->toolResults->all()))->prompt($case['prompt'])->text
        : $response->text;
} catch (Throwable $throwable) {
    $error = $throwable::class;
}

echo json_encode([
    'decision_layer' => $hasDecision,
    'steps' => $steps,
    'executed' => $executed,
    'schemas' => $schemas,
    'selection_ms' => $selectionMs,
    'total_ms' => (hrtime(true) - $started) / 1e6,
    'error' => $error,
    'answer' => $answer,
], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES).PHP_EOL;
