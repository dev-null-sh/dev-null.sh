<?php

declare(strict_types=1);

use App\Enums\ApiDocsSection;
use App\Queries\ApiDocsQuery;
use App\Services\ApiExamples;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Tempest\Highlight\Highlighter;

function apiDocsFixtureQuery(): ApiDocsQuery
{
    return new ApiDocsQuery(resolve(ApiExamples::class), resolve(Highlighter::class), base_path('tests/Fixtures/openapi/spec.json'));
}

/**
 * @param  array<string, mixed>  $spec
 */
function apiDocsQueryOver(array $spec): ApiDocsQuery
{
    $path = (string) tempnam(sys_get_temp_dir(), 'openapi');
    File::put($path, (string) json_encode($spec));

    return new ApiDocsQuery(resolve(ApiExamples::class), resolve(Highlighter::class), $path);
}

it('shows how to call each endpoint', function (): void {
    $examples = apiDocsFixtureQuery()->get(ApiDocsSection::Commits)[0]['examples'];

    expect(array_column($examples, 'label'))->toBe(['curl', 'Laravel', 'fetch'])
        ->and($examples[0]['code'])->toStartWith('body=\'{"body":"Hello from the API!"}\'')
        ->and($examples[0]['code'])->toContain("devnull_sign POST '/api/v1/commits' \"\$body\"");
});

it('lists the endpoints of a group, as the spec orders them', function (): void {
    $endpoints = apiDocsFixtureQuery()->get(ApiDocsSection::Commits);

    expect(array_column($endpoints, 'id'))->toBe(['api-commit.store', 'api-commit.show'])
        ->and($endpoints[0])->toMatchArray([
            'method' => 'POST',
            'path' => '/commits',
            'summary' => 'Commit, reply or quote',
            'description' => 'Pending review unless the user is an admin.',
            'signature' => 'required',
        ])
        ->and(apiDocsFixtureQuery()->get(ApiDocsSection::Users)[0])->toMatchArray([
            'path' => '/users/{handle}',
            'description' => null,
            'signature' => 'optional',
        ]);
});

it('describes path and query parameters', function (): void {
    expect(apiDocsFixtureQuery()->get(ApiDocsSection::Commits)[1]['parameters'])->toBe([
        ['in' => 'path', 'name' => 'commit', 'type' => 'uuid', 'required' => true, 'description' => null, 'enum' => null, 'children' => []],
        ['in' => 'query', 'name' => 'replies', 'type' => 'integer', 'required' => false, 'description' => 'Replies instead.', 'enum' => [0, 1], 'children' => []],
    ]);
});

it('never says a path parameter may be null', function (): void {
    expect(apiDocsFixtureQuery()->get(ApiDocsSection::Users)[0]['parameters'][0])->toMatchArray(['name' => 'handle', 'type' => 'string']);
});

it('describes the body a request takes, with what each field must be', function (): void {
    $body = apiDocsFixtureQuery()->get(ApiDocsSection::Commits)[0]['body'];

    expect($body['mediaType'])->toBe('multipart/form-data')
        ->and($body['fields'][0])->toMatchArray(['name' => 'body', 'type' => 'string', 'required' => true, 'description' => 'At most 4,000 characters.'])
        ->and($body['fields'][1])->toMatchArray(['name' => 'images', 'type' => 'array of binary | null', 'required' => false])
        ->and($body['fields'][2])->toMatchArray(['name' => 'image_descriptions', 'type' => 'array of (string | null) | null'])
        ->and($body['fields'][3])->toMatchArray(['name' => 'poll', 'type' => 'object', 'required' => false])
        ->and(array_column($body['fields'][3]['children'], 'name'))->toBe(['options', 'duration'])
        ->and($body['fields'][3]['children'][1]['required'])->toBeTrue()
        ->and(apiDocsFixtureQuery()->get(ApiDocsSection::Users)[0]['body'])->toBeNull();
});

it('describes what a successful answer holds, through the resources it refers to', function (): void {
    $response = apiDocsFixtureQuery()->get(ApiDocsSection::Commits)[0]['responses'][0];
    $commit = $response['fields'][0];

    expect($response)->toMatchArray(['status' => '201', 'description' => ''])
        ->and($commit)->toMatchArray(['name' => 'data', 'type' => 'Commit', 'required' => true])
        ->and(array_column($commit['children'], 'name'))->toBe(['id', 'status', 'author', 'parent', 'deleted_at'])
        ->and(array_column($commit['children'], 'required'))->toBe([true, false, false, false, false])
        ->and($commit['children'][1])->toMatchArray(['type' => 'CommitStatus', 'enum' => ['pending', 'approved', 'rejected']])
        ->and($commit['children'][2]['type'])->toBe('User | Organization')
        ->and(array_column($commit['children'][2]['children'], 'name'))->toBe(['handle', 'bio', 'admins'])
        ->and($commit['children'][4]['type'])->toBe('string | null');
});

it('stops at a resource already being described', function (): void {
    $parent = apiDocsFixtureQuery()->get(ApiDocsSection::Commits)[0]['responses'][0]['fields'][0]['children'][3];

    expect($parent)->toMatchArray(['name' => 'parent', 'type' => 'Commit', 'children' => []]);
});

it('lists lists of resources as such', function (): void {
    expect(apiDocsFixtureQuery()->get(ApiDocsSection::Commits)[1]['responses'][0]['fields'][0])
        ->toMatchArray(['name' => 'data', 'type' => 'array of Commit'])
        ->and(apiDocsFixtureQuery()->get(ApiDocsSection::Commits)[1]['responses'][0]['fields'][0]['children'])->not->toBeEmpty();
});

it('leaves the errors every endpoint shares to the Errors guide', function (): void {
    $responses = apiDocsFixtureQuery()->get(ApiDocsSection::Users)[0]['responses'];

    expect($responses[1])->toMatchArray(['status' => '401', 'description' => 'The signature is wrong.', 'fields' => []])
        ->and($responses[2])->toMatchArray(['status' => '404', 'description' => 'Not found', 'fields' => []]);
});

it('details an error only this endpoint answers with', function (): void {
    expect(apiDocsFixtureQuery()->get(ApiDocsSection::Users)[0]['responses'][3])->toMatchArray([
        'status' => '409',
        'description' => '',
        'fields' => [
            ['name' => 'message', 'type' => 'string', 'required' => true, 'description' => null, 'enum' => ['Unable to claim this organization.'], 'children' => []],
        ],
    ]);
});

it('reads the spec once, until it changes, keeping one copy of each group', function (): void {
    $path = (string) tempnam(sys_get_temp_dir(), 'openapi');
    File::copy(base_path('tests/Fixtures/openapi/spec.json'), $path);

    $first = new ApiDocsQuery(resolve(ApiExamples::class), resolve(Highlighter::class), $path)->get(ApiDocsSection::Users);
    File::partialMock()->shouldReceive('get')->never();
    $again = new ApiDocsQuery(resolve(ApiExamples::class), resolve(Highlighter::class), $path)->get(ApiDocsSection::Users);
    File::swap(new Filesystem);

    file_put_contents($path, str_replace('A profile as it shows on the web', 'Changed', (string) file_get_contents($path)));
    $changed = new ApiDocsQuery(resolve(ApiExamples::class), resolve(Highlighter::class), $path)->get(ApiDocsSection::Users);

    expect($first[0]['summary'])->toBe('A profile as it shows on the web')
        ->and($again)->toBe($first)
        ->and($changed[0]['summary'])->toBe('Changed')
        ->and(Cache::get('api-docs:users')['data'])->toBe($changed);

    File::delete($path);
});

it('reads the index from the cache, as the groups are', function (): void {
    $index = apiDocsFixtureQuery()->index();
    File::partialMock()->shouldReceive('get')->never();

    expect(apiDocsFixtureQuery()->index())->toBe($index);
});

it('lists an endpoint whose items are its own kind without describing it forever', function (): void {
    $fields = apiDocsQueryOver([
        'paths' => ['/trees' => ['get' => ['operationId' => 'api-tree.index', 'tags' => ['Users'], 'responses' => [
            '200' => ['description' => '', 'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/Forest']]]],
        ]]]],
        'components' => ['schemas' => ['Forest' => ['type' => 'array', 'items' => ['$ref' => '#/components/schemas/Forest']]]],
    ])->get(ApiDocsSection::Users)[0]['responses'][0]['fields'];

    expect($fields)->toBe([]);
});

it('names an endpoint without an operation ID so its URL can carry it, and finds its schemas by that name', function (): void {
    $query = apiDocsQueryOver([
        'paths' => ['/users/{handle}/follow' => ['post' => ['tags' => ['Users'], 'responses' => [
            '200' => ['description' => '', 'content' => ['application/json' => ['schema' => ['type' => 'object', 'properties' => ['following' => ['type' => 'boolean']]]]]],
        ]]]],
    ]);

    expect($query->get(ApiDocsSection::Users)[0]['id'])->toBe('post-users-handle-follow')
        ->and(array_keys($query->schemas(ApiDocsSection::Users, 'post-users-handle-follow')['responses']))->toBe([200]);
});

it('refuses two endpoints the docs would name alike', function (): void {
    apiDocsQueryOver([
        'paths' => [
            '/users/{handle}/follow' => ['post' => ['tags' => ['Users'], 'responses' => []]],
            '/users/{handle}/follows' => ['post' => ['operationId' => 'post-users-handle-follow', 'tags' => ['Users'], 'responses' => []]],
        ],
    ])->get(ApiDocsSection::Users);
})->throws(LogicException::class, 'Two endpoints of the API docs would both be named post-users-handle-follow.');

it('refuses two endpoints whose anchors on the page would be alike', function (): void {
    apiDocsQueryOver([
        'paths' => [
            '/commits/{commit}' => ['get' => ['operationId' => 'api.commit.show', 'tags' => ['Users'], 'responses' => []]],
            '/commits/{commit}/again' => ['get' => ['operationId' => 'api-commit-show', 'tags' => ['Users'], 'responses' => []]],
        ],
    ])->get(ApiDocsSection::Users);
})->throws(LogicException::class, 'Two endpoints of the API docs would both be named api-commit-show.');

it('keeps an empty schema an object, and an empty list a list, in the JSON schema it gives', function (): void {
    $code = apiDocsQueryOver([
        'paths' => ['/calls' => ['post' => ['operationId' => 'api-call.store', 'tags' => ['Users'], 'responses' => [
            '200' => ['description' => '', 'content' => ['application/json' => ['schema' => [
                'type' => 'object',
                'additionalProperties' => new stdClass,
                'properties' => ['meta' => new stdClass, 'tags' => ['type' => 'array', 'items' => new stdClass]],
                'anyOf' => [new stdClass],
                'required' => [],
            ]]]],
        ]]]],
    ])->schemas(ApiDocsSection::Users, 'api-call.store')['responses']['200']['code'];

    expect(json_decode($code))->toEqual((object) [
        'type' => 'object',
        'additionalProperties' => new stdClass,
        'properties' => (object) ['meta' => new stdClass, 'tags' => (object) ['type' => 'array', 'items' => new stdClass]],
        'anyOf' => [new stdClass],
        'required' => [],
    ]);
});

it('says an endpoint the spec asks no signature of is public', function (): void {
    expect(apiDocsQueryOver([
        'paths' => ['/status' => ['get' => ['operationId' => 'api-status.show', 'tags' => ['Users'], 'responses' => []]]],
    ])->get(ApiDocsSection::Users)[0]['signature'])->toBe('optional');
});

it('indexes every endpoint of the reference, for searching it, its groups in the sidebar order', function (): void {
    expect(apiDocsFixtureQuery()->index())->toBe([
        ['id' => 'api-commit.store', 'method' => 'POST', 'path' => '/commits', 'summary' => 'Commit, reply or quote', 'section' => 'commits'],
        ['id' => 'api-commit.show', 'method' => 'GET', 'path' => '/commits/{commit}', 'summary' => 'One commit', 'section' => 'commits'],
        ['id' => 'api-user.show', 'method' => 'GET', 'path' => '/users/{handle}', 'summary' => 'A profile as it shows on the web', 'section' => 'users'],
    ]);
});

it('gives the JSON schema of the body a request takes, with what it refers to', function (): void {
    $schema = apiDocsFixtureQuery()->schemas(ApiDocsSection::Commits, 'api-commit.store')['body'];
    $json = json_decode($schema['code'], true);

    expect($json['properties']['body'])->toBe(['type' => 'string', 'maxLength' => 40000, 'description' => 'At most 4,000 characters.'])
        ->and($json['required'])->toBe(['body'])
        ->and($schema['code'])->toStartWith("{\n    \"type\": \"object\",")
        ->and($schema['html'])->toContain('<span class="hl-');
});

it('gives the JSON schema of a successful answer, writing each schema it refers to once', function (): void {
    $json = json_decode(apiDocsFixtureQuery()->schemas(ApiDocsSection::Commits, 'api-commit.store')['responses']['201']['code'], true);
    $commit = $json['$defs']['CommitResource'];

    expect($json['properties']['data'])->toBe(['$ref' => '#/$defs/CommitResource'])
        ->and($commit['anyOf'][0]['properties']['status'])->toBe(['$ref' => '#/$defs/CommitStatus'])
        ->and($json['$defs']['CommitStatus'])->toBe(['type' => 'string', 'enum' => ['pending', 'approved', 'rejected']])
        ->and($commit['anyOf'][0]['properties']['parent'])->toBe(['$ref' => '#/$defs/CommitResource'])
        ->and(array_keys($json['$defs']))->toEqualCanonicalizing(['CommitResource', 'CommitStatus', 'UserResource', 'OrganizationResource']);
});

it('gives the JSON schema of an error only this endpoint answers with, and none for those every endpoint shares', function (): void {
    $schemas = apiDocsFixtureQuery()->schemas(ApiDocsSection::Users, 'api-user.show');

    expect($schemas['body'])->toBeNull()
        ->and($schemas['responses'])->toHaveCount(2)->toHaveKeys(['200', '409'])
        ->and(json_decode($schemas['responses']['409']['code'], true)['properties']['message']['const'])->toBe('Unable to claim this organization.');
});

it('has no schemas for an endpoint the group does not have, and keeps nothing for it', function (): void {
    expect(apiDocsFixtureQuery()->schemas(ApiDocsSection::Users, 'api-commit.store'))->toBeNull()
        ->and(Cache::has('api-docs-schemas:users:api-commit.store'))->toBeFalse();
});

it('reads the committed spec by default', function (): void {
    expect(array_column(resolve(ApiDocsQuery::class)->get(ApiDocsSection::Users), 'path'))->toContain('/users/{handle}');
});
