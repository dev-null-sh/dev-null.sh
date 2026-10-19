<?php

declare(strict_types=1);

namespace App\Services\EmbedProviders;

use App\Contracts\Services\EmbedProvider;
use App\Dto\Embed;
use Uri\WhatWg\Url;

final readonly class JitsiMeet implements EmbedProvider
{
    /**
     * meet.jit.si ends framed calls after five minutes, so a room is joined in a new tab. Its query and fragment, which
     * may carry a token or config overrides, are left out.
     */
    public function embed(Url $url): ?Embed
    {
        if ($url->getAsciiHost() !== 'meet.jit.si'
            || preg_match('#^/([\w-]+)/?$#', $url->getPath(), $matches) !== 1) {
            return null;
        }

        return Embed::call('Jitsi Meet', "https://meet.jit.si/{$matches[1]}");
    }
}
