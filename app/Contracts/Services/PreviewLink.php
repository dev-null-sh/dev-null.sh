<?php

declare(strict_types=1);

namespace App\Contracts\Services;

use App\Dto\LinkPreviewMetadata;
use Illuminate\Http\Client\Response;
use Uri\WhatWg\Url;

interface PreviewLink
{
    public function metadata(Response $response, Url $finalUrl): LinkPreviewMetadata;
}
