<?php

declare(strict_types=1);

use App\Services\EmbedProviders\AppleMusic;
use App\Services\EmbedProviders\Asciinema;
use App\Services\EmbedProviders\CodePen;
use App\Services\EmbedProviders\Loom;
use App\Services\EmbedProviders\SoundCloud;
use App\Services\EmbedProviders\Spotify;
use App\Services\EmbedProviders\Suno;
use App\Services\EmbedProviders\Twitch;
use App\Services\EmbedProviders\Vimeo;
use App\Services\EmbedProviders\YouTube;

return [

    'enabled' => (bool) env('EMBEDS_ENABLED', true),

    'only' => array_values(array_filter(array_map(trim(...), explode(',', (string) env('EMBEDS_PROVIDERS', ''))))),

    'providers' => [
        'youtube' => YouTube::class,
        'vimeo' => Vimeo::class,
        'loom' => Loom::class,
        'twitch' => Twitch::class,
        'suno' => Suno::class,
        'spotify' => Spotify::class,
        'soundcloud' => SoundCloud::class,
        'apple_music' => AppleMusic::class,
        'codepen' => CodePen::class,
        'asciinema' => Asciinema::class,
    ],

    'youtube' => [
        'privacy_enhanced' => (bool) env('EMBEDS_YOUTUBE_PRIVACY_ENHANCED', true),
    ],

];
