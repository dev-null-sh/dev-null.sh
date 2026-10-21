<?php

declare(strict_types=1);

namespace App\Dto;

/**
 * What the person signing in has to do with GitHub Sponsors. Either list is null when it wasn't asked for, or GitHub
 * couldn't hand over all of it.
 */
final readonly class GitHubSponsors
{
    /**
     * @param  list<GitHubSponsorship>|null  $sponsoring  the maintainers they sponsor
     * @param  list<GitHubSponsorship>|null  $sponsors  their own sponsors, private ones included
     */
    public function __construct(
        public bool $hasSponsorsListing,
        public ?array $sponsoring,
        public ?array $sponsors,
    ) {
        //
    }
}
