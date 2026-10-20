<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\Services\EmbedProvider;
use App\Dto\Embed;
use Illuminate\Contracts\Container\Container;
use Uri\WhatWg\Url;

final readonly class Embeds
{
    public function __construct(private Container $container)
    {
        //
    }

    /**
     * The GIF URL is metadata verified by the link unfurler, never a client-supplied attachment.
     */
    public function for(string $url, ?string $gifUrl = null): ?Embed
    {
        $parsed = Url::parse($url);

        if (! $parsed instanceof Url || ! in_array($parsed->getScheme(), ['http', 'https'], true) || ! config()->boolean('embeds.enabled')) {
            return null;
        }

        foreach ($this->providers() as $provider) {
            $embed = $provider->embed($parsed);

            if ($embed instanceof Embed) {
                return $embed;
            }
        }

        $only = config()->array('embeds.only');

        if ($gifUrl === null || ($only !== [] && ! in_array('gif', $only, true))) {
            return null;
        }

        return Embed::gif('GIF', $gifUrl);
    }

    /**
     * @return list<EmbedProvider>
     */
    private function providers(): array
    {
        $only = config()->array('embeds.only');
        $providers = [];

        foreach (config()->array('embeds.providers') as $key => $class) {
            $provider = is_string($class) && ($only === [] || in_array($key, $only, true)) ? $this->container->make($class) : null;

            if ($provider instanceof EmbedProvider) {
                $providers[] = $provider;
            }
        }

        return $providers;
    }
}
