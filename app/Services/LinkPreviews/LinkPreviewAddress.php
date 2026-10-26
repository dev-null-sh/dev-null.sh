<?php

declare(strict_types=1);

namespace App\Services\LinkPreviews;

use App\Contracts\Services\DnsResolver;
use Symfony\Component\HttpFoundation\IpUtils;
use Uri\WhatWg\Url;

final readonly class LinkPreviewAddress
{
    /**
     * @var list<string>
     */
    private const array BLOCKED_RANGES = [
        ...IpUtils::PRIVATE_SUBNETS,
        '64:ff9b::/32', // NAT64; FILTER_FLAG_GLOBAL_RANGE permits RFC 6052.
        '192.88.99.0/24',
        '224.0.0.0/4',
        'fec0::/10',
        'ff00::/8',
        '::ffff:0:0:0/96',
    ];

    public function __construct(private DnsResolver $dns)
    {
        //
    }

    public function get(Url $url): ?string
    {
        if (! in_array($url->getScheme(), ['http', 'https'], true)) {
            return null;
        }

        if (($url->getUsername() ?? '') !== '') {
            return null;
        }

        if (($url->getPassword() ?? '') !== '') {
            return null;
        }

        if ($url->getPort() !== null) {
            return null;
        }

        $addresses = $this->dns->resolve(mb_trim((string) $url->getAsciiHost(), '[]'));

        return $addresses !== [] && array_all($addresses, $this->isPublic(...)) ? $addresses[0] : null;
    }

    private function isPublic(string $address): bool
    {
        return filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_GLOBAL_RANGE) !== false
            && ! IpUtils::checkIp($address, self::BLOCKED_RANGES);
    }
}
