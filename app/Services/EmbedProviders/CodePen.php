<?php

declare(strict_types=1);

namespace App\Services\EmbedProviders;

use App\Contracts\Services\EmbedProvider;
use App\Dto\Embed;
use App\Enums\EmbedKind;
use Uri\WhatWg\Url;

final readonly class CodePen implements EmbedProvider
{
    public function embed(Url $url): ?Embed
    {
        if ($url->getAsciiHost() !== 'codepen.io'
            || preg_match('#^/((?:team/)?[\w-]+)/(?:pen|full|details|embed)/(\w+)/?$#', $url->getPath(), $matches) !== 1) {
            return null;
        }

        return Embed::fixed('CodePen', EmbedKind::Code, "https://codepen.io/{$matches[1]}/embed/{$matches[2]}", 400, ['default-tab' => 'result']);
    }
}
