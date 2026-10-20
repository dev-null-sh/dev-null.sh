export type GifMutation = {
    name: string;
    source: string;
    test: string;
    before: string;
    after: string;
};

export const phpGifMutations: GifMutation[] = [
    {
        name: 'redirect limit cannot increase',
        source: 'app/Actions/UpdateLinkPreview.php',
        test: 'tests/Unit/Actions/UpdateLinkPreviewTest.php',
        before: 'MAX_REDIRECTS = 3',
        after: 'MAX_REDIRECTS = 4',
    },
    {
        name: 'redirect limit cannot decrease',
        source: 'app/Actions/UpdateLinkPreview.php',
        test: 'tests/Unit/Actions/UpdateLinkPreviewTest.php',
        before: 'MAX_REDIRECTS = 3',
        after: 'MAX_REDIRECTS = 2',
    },
    {
        name: 'redirect cycles stop before refetching',
        source: 'app/Actions/UpdateLinkPreview.php',
        test: 'tests/Unit/Actions/UpdateLinkPreviewTest.php',
        before: 'if (isset($visited[$source]))',
        after: 'if (false)',
    },
    {
        name: 'non-default ports are rejected',
        source: 'app/Services/LinkPreviewAddress.php',
        test: 'tests/Unit/Actions/UpdateLinkPreviewTest.php',
        before: 'if ($url->getPort() !== null)',
        after: 'if (false)',
    },
    {
        name: 'non-global addresses are rejected',
        source: 'app/Services/LinkPreviewAddress.php',
        test: 'tests/Unit/Actions/UpdateLinkPreviewTest.php',
        before: 'return filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_GLOBAL_RANGE) !== false',
        after: 'return true',
    },
    {
        name: 'Symfony special-use subnets stay blocked',
        source: 'app/Services/LinkPreviewAddress.php',
        test: 'tests/Unit/Actions/UpdateLinkPreviewTest.php',
        before: '...IpUtils::PRIVATE_SUBNETS,',
        after: '',
    },
    {
        name: 'additional relay ranges stay blocked',
        source: 'app/Services/LinkPreviewAddress.php',
        test: 'tests/Unit/Actions/UpdateLinkPreviewTest.php',
        before: "'192.88.99.0/24',",
        after: '',
    },
    {
        name: 'all DNS answers must be public',
        source: 'app/Services/LinkPreviewAddress.php',
        test: 'tests/Unit/Actions/UpdateLinkPreviewTest.php',
        before: 'array_all($addresses, $this->isPublic(...))',
        after: 'true',
    },
    {
        name: 'response body is capped',
        source: 'app/Services/LimitedResponseBody.php',
        test: 'tests/Unit/Services/LimitedResponseBodyTest.php',
        before: '$remaining = max(0, $this->maxBytes - $this->tell());',
        after: '$remaining = PHP_INT_MAX;',
    },
    {
        name: 'seeking cannot bypass the byte cap',
        source: 'app/Services/LimitedResponseBody.php',
        test: 'tests/Unit/Services/LimitedResponseBodyTest.php',
        before: 'max(0, $this->maxBytes - $this->tell())',
        after: 'max(-1, $this->maxBytes - $this->tell())',
    },
    {
        name: 'overall deadline stays at five seconds',
        source: 'app/Services/LinkPreviewBudget.php',
        test: 'tests/Unit/Services/LinkPreviewBudgetTest.php',
        before: 'TIMEOUT_SECONDS = 5',
        after: 'TIMEOUT_SECONDS = 6',
    },
    {
        name: 'remaining timeout never rounds upward',
        source: 'app/Services/LinkPreviewBudget.php',
        test: 'tests/Unit/Services/LinkPreviewBudgetTest.php',
        before: 'floor(($deadline - $this->seconds())',
        after: 'round(($deadline - $this->seconds())',
    },
    {
        name: 'automatic HTTP redirects stay disabled',
        source: 'app/Services/LinkPreviewHttpClient.php',
        test: 'tests/Unit/Actions/UpdateLinkPreviewTest.php',
        before: '->withoutRedirecting()',
        after: '->withOptions([RequestOptions::ALLOW_REDIRECTS => true])',
    },
    {
        name: 'automatic decompression stays disabled',
        source: 'app/Services/LinkPreviewHttpClient.php',
        test: 'tests/Unit/Actions/UpdateLinkPreviewTest.php',
        before: 'RequestOptions::DECODE_CONTENT => false',
        after: 'RequestOptions::DECODE_CONTENT => true',
    },
    {
        name: 'requests pin the checked address',
        source: 'app/Services/LinkPreviewHttpClient.php',
        test: 'tests/Unit/Actions/UpdateLinkPreviewTest.php',
        before: 'CURLOPT_CONNECT_TO => [$connection]',
        after: 'CURLOPT_CONNECT_TO => []',
    },
    {
        name: 'content type is checked',
        source: 'app/Services/GifLinks.php',
        test: 'tests/Unit/Actions/UpdateLinkPreviewTest.php',
        before: 'if ($mediaType !== self::CONTENT_TYPE)',
        after: 'if (false)',
    },
    {
        name: 'both supported GIF signatures remain valid',
        source: 'app/Services/GifLinks.php',
        test: 'tests/Unit/Actions/UpdateLinkPreviewTest.php',
        before: "SIGNATURES = ['GIF87a', 'GIF89a']",
        after: "SIGNATURES = ['GIF89a']",
    },
    {
        name: 'truncated GIF descriptors are rejected',
        source: 'app/Services/GifLinks.php',
        test: 'tests/Unit/Actions/UpdateLinkPreviewTest.php',
        before: 'MIN_HEADER_BYTES = 13',
        after: 'MIN_HEADER_BYTES = 12',
    },
    {
        name: 'GIF dimensions must be nonzero',
        source: 'app/Services/GifLinks.php',
        test: 'tests/Unit/Actions/UpdateLinkPreviewTest.php',
        before: 'return $width !== self::ZERO_DIMENSION && $height !== self::ZERO_DIMENSION;',
        after: 'return true;',
    },
    {
        name: 'oversized URLs stay rejected',
        source: 'app/Services/GifLinks.php',
        test: 'tests/Unit/Actions/UpdateLinkPreviewTest.php',
        before: 'MAX_URL_LENGTH = 2048',
        after: 'MAX_URL_LENGTH = 2049',
    },
    {
        name: 'maximum-length URLs remain supported',
        source: 'app/Services/GifLinks.php',
        test: 'tests/Unit/Actions/UpdateLinkPreviewTest.php',
        before: 'MAX_URL_LENGTH = 2048',
        after: 'MAX_URL_LENGTH = 2047',
    },
    {
        name: 'Giphy hosts cannot be spoofed',
        source: 'app/Services/EmbedProviders/Giphy.php',
        test: 'tests/Unit/Services/EmbedProviders/GiphyTest.php',
        before: 'if (! in_array($url->getAsciiHost(), self::SUPPORTED_HOSTS, true))',
        after: 'if (false)',
    },
];
