<?php

declare(strict_types=1);

namespace App\Services;

final readonly class LinkPages
{
    /**
     * The page of every commit about a link, at /link/ followed by the link without its scheme. Only https links have
     * one. Its "%", "?" and "#" are escaped so they read back as written, and so is a slash ending it, which the router
     * would drop.
     */
    public function url(string $link): ?string
    {
        if (! str_starts_with($link, 'https://')) {
            return null;
        }

        $path = strtr(mb_substr($link, 8), ['%' => '%25', '?' => '%3F', '#' => '%23']);

        return url('link/'.(str_ends_with($path, '/') ? mb_substr($path, 0, -1).'%2F' : $path));
    }
}
