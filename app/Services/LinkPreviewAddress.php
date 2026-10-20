<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\Services\DnsResolver;
use Symfony\Component\HttpFoundation\IpUtils;
use Uri\WhatWg\Url;

final readonly class LinkPreviewAddress
{
    /**
     * Extend Symfony's private subnets with relay, multicast, site-local and translated ranges
     * that PHP's global-range filter also permits.
     *
     * @var list<string>
     */
    private const array BLOCKED_RANGES = [
        ...IpUtils::PRIVATE_SUBNETS,
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

    /**
     * Returns the address to connect to, or null when the URL must not be fetched: anything but plain http(s) on a
     * default port without credentials, or a host that resolves to nothing or to any non-public address.
     */
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
