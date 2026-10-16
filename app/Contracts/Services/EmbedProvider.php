<?php

declare(strict_types=1);

namespace App\Contracts\Services;

use App\Dto\Embed;
use Uri\WhatWg\Url;

interface EmbedProvider
{
    public function embed(Url $url): ?Embed;
}
