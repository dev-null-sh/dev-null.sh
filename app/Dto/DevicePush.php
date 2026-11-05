<?php

declare(strict_types=1);

namespace App\Dto;

/**
 * What a phone shows: who did something, never what they wrote, since the text passes through Google and Apple. The
 * data tells the app what to open, and an iPhone threads pushes that share a group.
 */
final readonly class DevicePush
{
    /**
     * The app's own scheme. NativePHP opens a tapped push only through its `url`, so the data rides along in its query.
     */
    public const string URL = 'devnull://push';

    /**
     * @var array<string, string>
     */
    public array $data;

    /**
     * @param  array<string, string>  $data
     */
    public function __construct(
        public string $title,
        public string $body,
        array $data,
        public ?string $group = null,
    ) {
        $this->data = [...$data, 'url' => self::URL.'?'.http_build_query($data, encoding_type: PHP_QUERY_RFC3986)];
    }
}
