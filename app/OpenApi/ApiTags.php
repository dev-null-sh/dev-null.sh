<?php

declare(strict_types=1);

namespace App\OpenApi;

use App\Enums\ApiDocsSection;
use Dedoc\Scramble\Support\Generator\Tag;
use Dedoc\Scramble\Support\RouteInfo;
use Illuminate\Support\Str;
use LogicException;

/**
 * The groups the API docs list endpoints in, the reference sections of the docs, named after the first segment of
 * their path, so the docs follow the API's own shape. A log and the starred commits belong to the user, so they are
 * listed with the rest of /me.
 */
final readonly class ApiTags
{
    private const array Aliases = [
        'log' => 'me',
        'starred' => 'me',
    ];

    /**
     * @return list<string>
     */
    public function __invoke(RouteInfo $routeInfo): array
    {
        $segment = Str::of($routeInfo->route->uri())->after('api/v1/')->before('/')->value();
        $section = ApiDocsSection::tryFrom(self::Aliases[$segment] ?? $segment);

        throw_unless($section, fn (): LogicException => new LogicException("The API docs have no group for /{$segment}: add it to ".ApiDocsSection::class.', or alias it in '.self::class.'.'));

        return [$section->title()];
    }

    /**
     * @return list<Tag>
     */
    public static function tags(): array
    {
        return array_map(fn (ApiDocsSection $section): Tag => new Tag($section->title()), ApiDocsSection::reference());
    }
}
