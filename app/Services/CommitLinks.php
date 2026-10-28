<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Routing\Route;
use Illuminate\Routing\Router;
use Illuminate\Support\Str;
use Uri\WhatWg\Url;

final readonly class CommitLinks
{
    public function __construct(private Router $router)
    {
        //
    }

    /**
     * Reads this site's commit route without dispatching a request or loading its models.
     */
    public function previewUrl(string $url): ?string
    {
        $parsed = Url::parse($url);
        $site = Url::parse(config()->string('app.url'));
        assert($site instanceof Url);

        if (! $parsed instanceof Url
            || ! in_array($parsed->getScheme(), ['http', 'https'], true)
            || $parsed->getAsciiHost() !== $site->getAsciiHost()
            || $parsed->getPort() !== $site->getPort()) {
            return null;
        }

        $route = $this->router->getRoutes()->getByName('commit.show');
        assert($route instanceof Route);

        if (preg_match($route->toSymfonyRoute()->compile()->getRegex(), mb_rtrim($parsed->getPath(), '/'), $matches) !== 1
            || ! Str::isUuid($matches['commit'])) {
            return null;
        }

        return route('commit-preview.show', ['user' => $matches['user'], 'commit' => $matches['commit']], absolute: false);
    }
}
