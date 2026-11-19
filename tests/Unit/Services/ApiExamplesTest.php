<?php

declare(strict_types=1);

use App\Services\ApiExamples;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function exampleEndpoint(array $overrides = []): array
{
    return [
        'id' => 'api-user.show',
        'method' => 'GET',
        'path' => '/users/{handle}',
        'summary' => '',
        'description' => null,
        'signature' => 'optional',
        'parameters' => [
            ['in' => 'path', 'name' => 'handle', 'type' => 'string', 'required' => true, 'description' => null, 'enum' => null, 'children' => []],
        ],
        'body' => null,
        'responses' => [],
        ...$overrides,
    ];
}

/**
 * @param  array<string, mixed>  $endpoint
 * @return array<string, string>
 */
function exampleCodes(array $endpoint): array
{
    return array_column(resolve(ApiExamples::class)->for($endpoint), 'code', 'label');
}

/**
 * Runs an endpoint's Laravel example against a faked client, with a devnullHeaders() that only remembers what it was
 * asked to sign, and answers what it signed and what the client then sent.
 *
 * @param  array<string, mixed>  $endpoint
 * @return array{signed: list<string>, sent: list<string>}
 */
function laravelExampleExchange(array $endpoint): array
{
    if (! function_exists('devnullHeaders')) {
        /**
         * @return array<string, string>
         */
        function devnullHeaders(string $method, string $path, string $body = ''): array
        {
            $GLOBALS['devnullSigned'] = [$method, $path, $body];

            return ['X-SSH-Signature' => 'signed'];
        }
    }

    Http::fake();
    eval(exampleCodes($endpoint)['Laravel']);

    /** @var Request $request */
    $request = Http::recorded()->first()[0];

    return [
        'signed' => $GLOBALS['devnullSigned'],
        'sent' => [$request->method(), (string) str($request->url())->after('dev-null.sh'), $request->body()],
    ];
}

it('sends the very request its Laravel example signs', function (array $endpoint): void {
    $exchange = laravelExampleExchange($endpoint);

    expect($exchange['sent'])->toBe($exchange['signed']);
})->with([
    'a write without a body' => [exampleEndpoint(['method' => 'POST', 'path' => '/users/{handle}/follow', 'signature' => 'required'])],
    'a replacement without a body' => [exampleEndpoint(['method' => 'PUT', 'path' => '/commits/{commit}/pin', 'signature' => 'required', 'parameters' => []])],
    'a deletion' => [exampleEndpoint(['method' => 'DELETE', 'path' => '/commits/{commit}', 'signature' => 'required', 'parameters' => []])],
    'a read with a query' => [exampleEndpoint(['path' => '/search/commits', 'signature' => 'required', 'parameters' => [
        ['in' => 'query', 'name' => 'q', 'type' => 'string', 'required' => true, 'description' => null, 'enum' => null, 'children' => []],
    ]])],
    'a write with a body' => [exampleEndpoint(['method' => 'POST', 'path' => '/commits', 'signature' => 'required', 'parameters' => [], 'body' => ['mediaType' => 'application/json', 'fields' => [
        ['name' => 'body', 'type' => 'string', 'required' => true, 'description' => null, 'enum' => null, 'children' => []],
        ['name' => 'settings', 'type' => 'object', 'required' => true, 'description' => null, 'enum' => null, 'children' => [
            ['name' => 'quiet', 'type' => 'object', 'required' => true, 'description' => null, 'enum' => null, 'children' => []],
        ]],
    ]]])],
]);

it('encodes a body alike in every client, whatever its values hold', function (): void {
    $codes = exampleCodes(exampleEndpoint(['method' => 'POST', 'path' => '/commits', 'signature' => 'required', 'parameters' => [], 'body' => ['mediaType' => 'application/json', 'fields' => [
        ['name' => 'body', 'type' => 'string', 'required' => true, 'description' => null, 'enum' => ['it\'s a "quote", a \\ and a'."\n".'line'], 'children' => []],
        ['name' => 'link-preview', 'type' => 'object', 'required' => true, 'description' => null, 'enum' => null, 'children' => [
            ['name' => 'meta', 'type' => 'object', 'required' => true, 'description' => null, 'enum' => null, 'children' => []],
        ]],
        ['name' => 'members', 'type' => 'array of string', 'required' => true, 'description' => null, 'enum' => null, 'children' => []],
    ]]]));
    $expected = '{"body":"it\'s a \"quote\", a \\\\ and a\nline","link-preview":{"meta":{}},"members":["…"]}';

    $curl = Process::run(['bash', '-c', Str::before($codes['curl'], "\ndevnull_sign")."\nprintf %s \"\$body\""])->throw()->output();
    eval(Str::between($codes['Laravel'], "\n\n", "\n\n").' $laravel = $body;');
    $node = Process::run(['node', '--input-type=module', '-e', 'const body'.Str::between($codes['fetch'], 'const body', "\n\n")."\nprocess.stdout.write(body);"])->throw()->output();

    expect(json_decode($curl))->toEqual(json_decode($expected))
        ->and(json_decode($laravel))->toEqual(json_decode($expected))
        ->and(json_decode($node))->toEqual(json_decode($expected));
});

it('schedules a commit for tomorrow, computed when the example runs, in every client', function (bool $signed): void {
    $codes = exampleCodes(exampleEndpoint(['method' => 'POST', 'path' => '/scheduled-commits', 'signature' => $signed ? 'required' : 'optional', 'parameters' => [], 'body' => ['mediaType' => 'application/json', 'fields' => [
        ['name' => 'body', 'type' => 'string', 'required' => true, 'description' => null, 'enum' => null, 'children' => []],
        ['name' => 'scheduled_at', 'type' => 'date-time', 'required' => true, 'description' => null, 'enum' => null, 'children' => []],
    ]]]));
    $shell = $signed
        ? Str::before($codes['curl'], "\ndevnull_sign")."\nprintf %s \"\$body\""
        : Str::before($codes['curl'], "\ncurl")."\nprintf %s ".Str::of($codes['curl'])->afterLast('-d ')->value();

    $curl = Process::run(['bash', '-c', $shell])->throw()->output();
    eval(Str::of($codes['Laravel'])->after("use Illuminate\\Support\\Facades\\Http;\n\n")->before("\n\n\$response")->value().' $laravel = $body;');
    $node = Process::run(['node', '--input-type=module', '-e', 'const body'.Str::between($codes['fetch'], 'const body', "\n\n")."\nprocess.stdout.write(body);"])->throw()->output();

    foreach ([$curl, $laravel, $node] as $sent) {
        $scheduledAt = json_decode((string) $sent, true)['scheduled_at'];

        expect($scheduledAt)->toMatch('/^\d{4}-\d\d-\d\dT\d\d:\d\d:\d\d\+00:00$/')
            ->and(CarbonImmutable::createFromFormat('Y-m-d\TH:i:sP', $scheduledAt)->isBetween(now()->addHours(23), now()->addHours(25)))->toBeTrue();
    }
})->with(['signed' => true, 'unsigned' => false]);

it('fills in values the API accepts for fields with their own format', function (): void {
    $codes = exampleCodes(exampleEndpoint(['method' => 'PUT', 'path' => '/me/live', 'signature' => 'required', 'parameters' => [], 'body' => ['mediaType' => 'application/json', 'fields' => [
        ['name' => 'url', 'type' => 'string', 'required' => true, 'description' => null, 'enum' => null, 'children' => []],
        ['name' => 'timezone', 'type' => 'string', 'required' => true, 'description' => null, 'enum' => null, 'children' => []],
        ['name' => 'sdp', 'type' => 'string', 'required' => true, 'description' => null, 'enum' => null, 'children' => []],
    ]]]));

    expect($codes['curl'])->toContain('"url":"https://www.twitch.tv/nunomaduro"', '"timezone":"Europe/Paris"', '"sdp":"v=0…"');
});

it('reads a public endpoint without signing', function (): void {
    expect(exampleCodes(exampleEndpoint()))->toBe([
        'curl' => <<<'SH_WRAP'
        curl https://dev-null.sh/api/v1/users/nunomaduro \
          -H 'Accept: application/json'
        SH_WRAP,
        'Laravel' => <<<'PHP'
            use Illuminate\Support\Facades\Http;

            $response = Http::baseUrl('https://dev-null.sh/api/v1')
                ->acceptJson()
                ->get('users/nunomaduro')
                ->throw()
                ->json();
            PHP,
        'fetch' => <<<'JS_WRAP'
        const response = await fetch('https://dev-null.sh/api/v1/users/nunomaduro', {
          headers: {
            Accept: 'application/json',
          },
        });
        if (!response.ok) throw new Error(await response.text());
        const json = await response.json();
        JS_WRAP,
    ]);
});

it('signs the exact body it sends', function (): void {
    $endpoint = exampleEndpoint([
        'method' => 'POST',
        'path' => '/commits',
        'signature' => 'required',
        'parameters' => [],
        'body' => ['mediaType' => 'multipart/form-data', 'fields' => [
            ['name' => 'body', 'type' => 'string', 'required' => true, 'description' => null, 'enum' => null, 'children' => []],
            ['name' => 'parent_id', 'type' => 'uuid | null', 'required' => false, 'description' => null, 'enum' => null, 'children' => []],
        ]],
    ]);

    expect(exampleCodes($endpoint))->toBe([
        'curl' => <<<'SH_WRAP'
        body='{"body":"Hello from the API!"}'
        devnull_sign POST '/api/v1/commits' "$body" # see Authentication

        curl -X POST https://dev-null.sh/api/v1/commits \
          -H 'Accept: application/json' \
          -H "X-Devnull-Timestamp: $ts" \
          -H "X-Devnull-Nonce: $nonce" \
          -H "X-SSH-Signature: $signature" \
          -H 'Content-Type: application/json' \
          -d "$body"
        SH_WRAP,
        'Laravel' => <<<'PHP'
            use Illuminate\Support\Facades\Http;

            $body = json_encode(['body' => 'Hello from the API!']);

            $response = Http::baseUrl('https://dev-null.sh/api/v1')
                ->acceptJson()
                ->withHeaders(devnullHeaders('POST', '/api/v1/commits', $body)) // see Authentication
                ->withBody($body)
                ->post('commits')
                ->throw()
                ->json();
            PHP,
        'fetch' => <<<'JS_WRAP'
        import { devnullHeaders } from './devnull.mjs'; // see Authentication

        const body = JSON.stringify({ body: 'Hello from the API!' });

        const response = await fetch('https://dev-null.sh/api/v1/commits', {
          method: 'POST',
          headers: {
            ...devnullHeaders('POST', '/api/v1/commits', body),
            Accept: 'application/json',
            'Content-Type': 'application/json',
          },
          body,
        });
        if (!response.ok) throw new Error(await response.text());
        const json = await response.json();
        JS_WRAP,
    ]);
});

it('signs a request without a body over nothing', function (): void {
    $codes = exampleCodes(exampleEndpoint(['method' => 'DELETE', 'path' => '/commits/{commit}', 'signature' => 'required', 'parameters' => []]));

    expect($codes['curl'])->toBe(<<<'SH_WRAP'
    devnull_sign DELETE '/api/v1/commits/01a1c63c-ae57-70cc-adf3-2298cc075627' # see Authentication

    curl -X DELETE https://dev-null.sh/api/v1/commits/01a1c63c-ae57-70cc-adf3-2298cc075627 \
      -H 'Accept: application/json' \
      -H "X-Devnull-Timestamp: $ts" \
      -H "X-Devnull-Nonce: $nonce" \
      -H "X-SSH-Signature: $signature"
    SH_WRAP)
        ->and($codes['Laravel'])->toContain("->withHeaders(devnullHeaders('DELETE', '/api/v1/commits/01a1c63c-ae57-70cc-adf3-2298cc075627')) // see Authentication\n    ->delete('commits/01a1c63c-ae57-70cc-adf3-2298cc075627')")
        ->and($codes['Laravel'])->not->toContain('$body')
        ->and($codes['fetch'])->toContain("  method: 'DELETE',\n  headers: {\n    ...devnullHeaders('DELETE', '/api/v1/commits/01a1c63c-ae57-70cc-adf3-2298cc075627'),\n    Accept: 'application/json',\n  },\n});");
});

it('signs the path and the query it requests', function (): void {
    $codes = exampleCodes(exampleEndpoint([
        'path' => '/search/commits',
        'signature' => 'required',
        'parameters' => [
            ['in' => 'query', 'name' => 'q', 'type' => 'string', 'required' => true, 'description' => null, 'enum' => null, 'children' => []],
        ],
    ]));

    expect($codes['curl'])->toContain("devnull_sign GET '/api/v1/search/commits?q=laravel' # see Authentication")
        ->and($codes['curl'])->toContain("curl 'https://dev-null.sh/api/v1/search/commits?q=laravel' \\")
        ->and($codes['Laravel'])->toContain("devnullHeaders('GET', '/api/v1/search/commits?q=laravel')")
        ->and($codes['Laravel'])->toContain("->get('search/commits?q=laravel')");
});

it('shows once how each client signs a request', function (): void {
    $signing = resolve(ApiExamples::class)->signing();

    expect(array_column($signing, 'label'))->toBe(['curl', 'Laravel', 'fetch'])
        ->and(array_column($signing, 'language'))->toBe(['bash', 'php', 'javascript'])
        ->and($signing[0]['code'])->toStartWith("# devnull_sign METHOD PATH [BODY] sets \$ts, \$nonce and \$signature\ndevnull_sign() {")
        ->and($signing[1]['code'])->toContain('function devnullHeaders(string $method, string $path, string $body = \'\'): array')
        ->and($signing[2]['code'])->toContain("export function devnullHeaders(method, path, body = '') {")
        ->and($signing[1]['html'])->toContain('<span class="hl-keyword">function</span>');
});

it('fills in the parameters a request needs', function (): void {
    $codes = exampleCodes(exampleEndpoint([
        'path' => '/search/commits',
        'parameters' => [
            ['in' => 'query', 'name' => 'q', 'type' => 'string', 'required' => true, 'description' => null, 'enum' => null, 'children' => []],
            ['in' => 'query', 'name' => 'cursor', 'type' => 'string | null', 'required' => false, 'description' => null, 'enum' => null, 'children' => []],
        ],
    ]));

    expect($codes['curl'])->toStartWith("curl 'https://dev-null.sh/api/v1/search/commits?q=laravel' \\\n");
});

it('fills in a reaction with an emoji', function (): void {
    $codes = exampleCodes(exampleEndpoint([
        'method' => 'DELETE',
        'path' => '/remotes/messages/{message}/reactions',
        'parameters' => [
            ['in' => 'query', 'name' => 'emoji', 'type' => 'string', 'required' => true, 'description' => null, 'enum' => null, 'children' => []],
        ],
    ]));

    expect($codes['curl'])->toStartWith("curl -X DELETE 'https://dev-null.sh/api/v1/remotes/messages/1/reactions?emoji=%F0%9F%91%8D' \\\n");
});

it('asks for JSON in every client, with a body or without', function (array $endpoint): void {
    $codes = exampleCodes($endpoint);

    expect($codes['curl'])->toContain("-H 'Accept: application/json'")
        ->and($codes['Laravel'])->toContain('->acceptJson()')
        ->and($codes['fetch'])->toContain("Accept: 'application/json',");
})->with([
    'a public read' => [exampleEndpoint()],
    'a signed deletion' => [exampleEndpoint(['method' => 'DELETE', 'path' => '/commits/{commit}', 'signature' => 'required', 'parameters' => []])],
    'a signed write with a body' => [exampleEndpoint(['method' => 'POST', 'path' => '/commits', 'signature' => 'required', 'parameters' => [], 'body' => ['mediaType' => 'application/json', 'fields' => [
        ['name' => 'body', 'type' => 'string', 'required' => true, 'description' => null, 'enum' => null, 'children' => []],
    ]]])],
]);

it('asks for an issue by its number', function (): void {
    $codes = exampleCodes(exampleEndpoint([
        'path' => '/search/issues',
        'parameters' => [
            ['in' => 'query', 'name' => 'q', 'type' => 'string', 'required' => true, 'description' => null, 'enum' => null, 'children' => []],
        ],
    ]));

    expect($codes['curl'])->toStartWith("curl 'https://dev-null.sh/api/v1/search/issues?q=857' \\\n");
});

it('sends a list in the query as one flat list', function (): void {
    $codes = exampleCodes(exampleEndpoint([
        'path' => '/log/{log}',
        'parameters' => [
            ['in' => 'query', 'name' => 'pending[]', 'type' => 'array of uuid', 'required' => true, 'description' => null, 'enum' => null, 'children' => []],
        ],
    ]));

    parse_str((string) parse_url(Str::betweenFirst($codes['curl'], "'", "'"), PHP_URL_QUERY), $query);

    expect($query)->toBe(['pending' => ['0195d8c4-7b2e-7c4f-9e1a-5b6c7d8e9f01']]);
});

it('fills in a headline with its id', function (): void {
    expect(exampleCodes(exampleEndpoint(['path' => '/headlines/{headline}', 'parameters' => []]))['curl'])
        ->toStartWith("curl https://dev-null.sh/api/v1/headlines/0195d8c4-7b2e-7c4f-9e1a-5b6c7d8e9f01 \\\n");
});

it('fills in path parameters, keeping one it has no example for as it is', function (): void {
    expect(exampleCodes(exampleEndpoint(['path' => '/remotes/orgs/{org}/members/{handle}/{thing}']))['curl'])
        ->toStartWith("curl https://dev-null.sh/api/v1/remotes/orgs/0195d8c4-7b2e-7c4f-9e1a-5b6c7d8e9f01/members/nunomaduro/{thing} \\\n");
});

it('reads no body from an answer that has none', function (): void {
    $codes = exampleCodes(exampleEndpoint(['responses' => [['status' => '204', 'description' => 'No content', 'fields' => []]]]));

    expect($codes['fetch'])->toStartWith("const response = await fetch('https://dev-null.sh/api/v1/users/nunomaduro', {")
        ->and($codes['fetch'])->toEndWith("});\nif (!response.ok) throw new Error(await response.text());")
        ->and($codes['fetch'])->not->toContain('response.json()');
});

it('fills a body with what each required field takes', function (): void {
    $codes = exampleCodes(exampleEndpoint([
        'method' => 'PUT',
        'path' => '/me/lane',
        'signature' => 'required',
        'parameters' => [],
        'body' => ['mediaType' => 'application/json', 'fields' => [
            ['name' => 'lane', 'type' => 'integer | string', 'required' => true, 'description' => null, 'enum' => [3, 'auto'], 'children' => []],
            ['name' => 'enabled', 'type' => 'boolean', 'required' => true, 'description' => null, 'enum' => null, 'children' => []],
            ['name' => 'options', 'type' => 'array of string', 'required' => true, 'description' => null, 'enum' => null, 'children' => []],
            ['name' => 'client_id', 'type' => 'uuid', 'required' => true, 'description' => null, 'enum' => null, 'children' => []],
            ['name' => 'count', 'type' => 'integer', 'required' => true, 'description' => null, 'enum' => null, 'children' => []],
        ]],
    ]));

    expect($codes['curl'])->toContain(<<<'SH'
        body='{"lane":3,"enabled":true,"options":["…"],"client_id":"0195d8c4-7b2e-7c4f-9e1a-5b6c7d8e9f01","count":1}'
        SH)
        ->and($codes['Laravel'])->toContain("json_encode(['lane' => 3, 'enabled' => true, 'options' => ['…'], 'client_id' => '0195d8c4-7b2e-7c4f-9e1a-5b6c7d8e9f01', 'count' => 1])");
});

it('fills an object with its own required fields, and leaves out one that has none', function (): void {
    $codes = exampleCodes(exampleEndpoint([
        'method' => 'POST',
        'path' => '/commits',
        'signature' => 'required',
        'parameters' => [],
        'body' => ['mediaType' => 'application/json', 'fields' => [
            ['name' => 'body', 'type' => 'string', 'required' => true, 'description' => null, 'enum' => null, 'children' => []],
            ['name' => 'poll', 'type' => 'object', 'required' => true, 'description' => null, 'enum' => null, 'children' => [
                ['name' => 'options', 'type' => 'array of string', 'required' => false, 'description' => null, 'enum' => null, 'children' => []],
            ]],
            ['name' => 'link', 'type' => 'object', 'required' => true, 'description' => null, 'enum' => null, 'children' => [
                ['name' => 'title', 'type' => 'string', 'required' => true, 'description' => null, 'enum' => null, 'children' => []],
            ]],
        ]],
    ]));

    expect($codes['curl'])->toContain(<<<'SH'
        body='{"body":"Hello from the API!","link":{"title":"…"}}'
        SH);
});

it('fills the fields of a body that requires none, since sending nothing would mean something else', function (): void {
    $codes = exampleCodes(exampleEndpoint([
        'method' => 'PUT',
        'path' => '/remotes/orgs/{org}',
        'signature' => 'required',
        'parameters' => [],
        'body' => ['mediaType' => 'application/json', 'fields' => [
            ['name' => 'name', 'type' => 'string | null', 'required' => false, 'description' => null, 'enum' => null, 'children' => []],
        ]],
    ]));

    expect($codes['curl'])->toContain(<<<'SH'
        body='{"name":"The Artisans"}'
        SH);
});

it('sends a list of objects as a list', function (): void {
    $codes = exampleCodes(exampleEndpoint([
        'method' => 'PUT',
        'path' => '/remotes/orgs/{org}/call',
        'signature' => 'required',
        'parameters' => [],
        'body' => ['mediaType' => 'application/json', 'fields' => [
            ['name' => 'signals', 'type' => 'array of object', 'required' => true, 'description' => null, 'enum' => null, 'children' => [
                ['name' => 'type', 'type' => 'string', 'required' => true, 'description' => null, 'enum' => ['offer', 'answer'], 'children' => []],
            ]],
        ]],
    ]));

    expect($codes['curl'])->toContain(<<<'SH'
        body='{"signals":[{"type":"offer"}]}'
        SH);
});

it('highlights each example in its language', function (): void {
    $examples = resolve(ApiExamples::class)->for(exampleEndpoint());

    expect(array_column($examples, 'language'))->toBe(['bash', 'php', 'javascript'])
        ->and($examples[1]['html'])->toContain('<span class="hl-type">Http</span>')
        ->and($examples[2]['html'])->toContain('<span class="hl-keyword">const</span>');
});
