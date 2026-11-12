<?php

declare(strict_types=1);

use App\Enums\ApiErrorCode;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Number;

/**
 * The spec as Scramble exports it from the code right now, generated once per process since it reads every route.
 *
 * @return array<string, mixed>
 */
function freshOpenApiSpec(): array
{
    static $spec = null;

    if ($spec === null) {
        $path = tempnam(sys_get_temp_dir(), 'openapi');
        Artisan::call('scramble:export', ['--path' => $path]);
        $spec = json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
        File::delete($path);
    }

    return $spec;
}

/**
 * @return array<string, mixed>
 */
function openApiOperation(string $method, string $path): array
{
    return freshOpenApiSpec()['paths'][$path][$method];
}

it('commits the spec the code describes', function (): void {
    $committed = json_decode((string) file_get_contents(resource_path('api/openapi.json')), true, flags: JSON_THROW_ON_ERROR);

    expect($committed)->toBe(freshOpenApiSpec(), 'The API changed: migrate your database, run `composer generate:openapi` and commit resources/api/openapi.json.');
});

it('exports the same spec whatever the app is called and whichever language it speaks', function (): void {
    config(['app.name' => 'dev-null (local)']);
    Number::useLocale('fr');
    $path = tempnam(sys_get_temp_dir(), 'openapi');

    Artisan::call('scramble:export', ['--path' => $path]);
    $spec = json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
    File::delete($path);
    Number::useLocale('en');

    expect($spec['info']['title'])->toBe('/dev/null')
        ->and($spec['components']['schemas']['CreateApiCommitRequest']['properties']['body']['description'])->toStartWith('At most 4,000 characters');
});

it('describes the production API', function (): void {
    expect(freshOpenApiSpec()['servers'])->toBe([['url' => 'https://dev-null.sh/api/v1', 'description' => 'Production']]);
});

it('groups the endpoints as the docs list them', function (): void {
    $spec = freshOpenApiSpec();
    $tags = collect($spec['paths'])->flatMap(fn (array $item): array => collect($item)->pluck('tags')->flatten()->all())->unique()->values();

    expect(array_column($spec['tags'], 'name'))->toBe(['Commits', 'Headlines', 'Issues', 'Me', 'Notifications', 'Organizations', 'Remotes', 'Scheduled commits', 'Search', 'Users'])
        ->and($tags->sort()->values()->all())->toBe(collect(array_column($spec['tags'], 'name'))->sort()->values()->all())
        ->and(openApiOperation('get', '/log/{log}')['tags'])->toBe(['Me'])
        ->and(openApiOperation('get', '/starred')['tags'])->toBe(['Me'])
        ->and(openApiOperation('get', '/scheduled-commits')['tags'])->toBe(['Scheduled commits'])
        ->and(openApiOperation('get', '/headlines')['tags'])->toBe(['Headlines'])
        ->and(openApiOperation('post', '/issues')['tags'])->toBe(['Issues']);
});

it('leaves out what only the apps use to work', function (): void {
    expect(array_keys(freshOpenApiSpec()['paths']))
        ->not->toContain('/apps/mac')
        ->not->toContain('/devices')
        ->not->toContain('/broadcasting/auth')
        ->not->toContain('/me/web-session')
        ->not->toContain('/remotes/images/{messageImage}')
        ->not->toContain('/remotes/voices/{messageVoice}')
        ->not->toContain('/openapi.json');
});

it('names path parameters after what the route reads', function (): void {
    $paths = array_keys(freshOpenApiSpec()['paths']);

    expect($paths)->toContain('/users/{handle}', '/users/{handle}/follow', '/remotes/@{handle}/messages', '/remotes/orgs/{org}/members/{handle}', '/commits/{commit}')
        ->and(collect(openApiOperation('get', '/users/{handle}')['parameters'])->where('in', 'path')->pluck('name')->all())->toBe(['handle'])
        ->and(collect(openApiOperation('put', '/remotes/orgs/{org}/members/{handle}')['parameters'])->where('in', 'path')->pluck('name')->all())->toBe(['org', 'handle']);
});

it('describes a parameter a route reads by handle as the handle it is', function (): void {
    $handles = collect(freshOpenApiSpec()['paths'])
        ->flatMap(fn (array $item): array => collect($item)->flatMap(fn (array $operation): array => $operation['parameters'] ?? [])->all())
        ->where('in', 'path')
        ->where('name', 'handle');

    expect($handles)->not->toBeEmpty()
        ->and($handles->pluck('schema')->unique()->values()->all())->toBe([['type' => 'string']])
        ->and(collect(openApiOperation('post', '/users/{handle}/follow')['parameters'])->firstWhere('name', 'handle')['description'])->toBe("The user's handle")
        ->and(collect(openApiOperation('put', '/remotes/orgs/{org}/members/{handle}')['parameters'])->firstWhere('name', 'handle')['description'])->toBe("The member's handle");
});

it('says which endpoints need a signed request', function (): void {
    $spec = freshOpenApiSpec();

    expect($spec['components']['securitySchemes']['devnullSignature'])->toMatchArray(['type' => 'apiKey', 'in' => 'header', 'name' => 'X-SSH-Signature'])
        ->and(openApiOperation('get', '/me')['security'])->toBe([['devnullSignature' => []]])
        ->and(openApiOperation('post', '/commits')['security'])->toBe([['devnullSignature' => []]])
        ->and(openApiOperation('get', '/users/{handle}')['security'])->toBe([[], ['devnullSignature' => []]])
        ->and(openApiOperation('get', '/commits/{commit}')['security'])->toBe([[], ['devnullSignature' => []]]);
});

it('answers a bad signature with its error code', function (): void {
    expect(openApiOperation('get', '/me')['responses']['401']['content']['application/json']['schema'])->toBe(['$ref' => '#/components/schemas/ApiError'])
        ->and(openApiOperation('get', '/me')['responses']['401']['description'])->toBe('The signature is missing, stale or wrong, or the key that made it is not an approved device.')
        ->and(openApiOperation('get', '/users/{handle}')['responses']['401']['description'])->toBe('The signature is stale or wrong, or the key that made it is not an approved device. Without one, the request reads as a guest.');
});

it('answers too many requests with a message, and a code only a signed request gets', function (): void {
    $error = freshOpenApiSpec()['components']['schemas']['RateLimitError'];

    expect(openApiOperation('get', '/users/{handle}')['responses']['429']['content']['application/json']['schema'])->toBe(['$ref' => '#/components/schemas/RateLimitError'])
        ->and(openApiOperation('post', '/commits')['responses'])->toHaveKey('429')
        ->and($error['required'])->toBe(['message'])
        ->and($error['properties']['code']['enum'])->toBe(['rate_limited'])
        ->and($error['properties']['code']['description'])->toBe('Only a signed request gets it, and never from the pings and commit search limits.');
});

it('describes an author as the user or the organization it is', function (): void {
    $variants = freshOpenApiSpec()['components']['schemas']['ProfileResource']['anyOf'];

    expect($variants)->toHaveCount(2)
        ->and(array_keys($variants[0]['properties']))->toContain('type', 'id', 'handle', 'name', 'avatar_url', 'html_url')
        ->and(array_keys($variants[1]['properties']))->toContain('type', 'id', 'handle', 'name', 'avatar_url', 'html_url');
});

it('describes what resources build with the services they resolve', function (): void {
    $signed = freshOpenApiSpec()['components']['schemas']['CommitResource']['anyOf'][0]['properties'];

    expect($signed['mentions']['items']['properties'])->toHaveKeys(['handle', 'offset', 'length', 'kind'])
        ->and($signed)->toHaveKeys(['body_segments', 'preview_segments'])
        ->and($signed['id'])->toBe(['type' => 'string']);
});

it('says how long a commit may be, as links count', function (): void {
    $schemas = freshOpenApiSpec()['components']['schemas'];

    expect($schemas['CreateApiCommitRequest']['properties']['body']['description'])->toBe('At most 4,000 characters, where a link outside code counts as 23 at most. Each link must be 2,048 characters at most.')
        ->and($schemas['CreateApiScheduledCommitRequest']['properties']['body']['description'])->toBe('At most 4,000 characters, where a link outside code counts as 23 at most. Each link must be 2,048 characters at most.');
});

it('takes a commit as JSON, or as a signed upload of that JSON with its images', function (string $path, string $schema): void {
    $body = openApiOperation('post', $path)['requestBody']['content'];
    $upload = $body['multipart/form-data']['schema'];

    expect(array_keys($body))->toBe(['application/json', 'multipart/form-data'])
        ->and($body['application/json']['schema'])->toBe(['$ref' => "#/components/schemas/{$schema}"])
        ->and(freshOpenApiSpec()['components']['schemas'][$schema]['properties'])->not->toHaveKey('images')
        ->and(array_keys($upload['properties']))->toBe(['payload', 'images'])
        ->and($upload['required'])->toBe(['payload', 'images'])
        ->and($upload['properties']['payload'])->toMatchArray(['type' => 'string', 'format' => 'binary', 'contentMediaType' => 'application/json'])
        ->and($upload['properties']['images']['items'])->toMatchArray(['type' => 'string', 'format' => 'binary']);
})->with([
    'a commit' => ['/commits', 'CreateApiCommitRequest'],
    'a scheduled commit' => ['/scheduled-commits', 'CreateApiScheduledCommitRequest'],
    'a report' => ['/commits/{commit}/reports', 'CreateApiCommitReportRequest'],
]);

it('takes a poll only when a commit has one, then with its options and duration', function (string $schema): void {
    $request = freshOpenApiSpec()['components']['schemas'][$schema];

    expect($request['required'])->toContain('body')->not->toContain('poll')
        ->and($request['properties']['poll']['type'])->toBe(['object', 'null'])
        ->and($request['properties']['poll']['required'])->toBe(['options', 'duration']);
})->with(['CreateApiCommitRequest', 'CreateApiScheduledCommitRequest']);

it('lists every error code the API answers with', function (): void {
    $error = freshOpenApiSpec()['components']['schemas']['ApiError'];

    expect($error['required'])->toBe(['message', 'code'])
        ->and($error['properties']['code']['enum'])->toBe(array_column(ApiErrorCode::cases(), 'value'));
});
