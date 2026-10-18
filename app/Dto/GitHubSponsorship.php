<?php

declare(strict_types=1);

namespace App\Dto;

use App\Models\Sponsorship;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Arr;

/**
 * One sponsorship as GitHub reports it to the person signing in, naming the account on the other side by its
 * immutable ID.
 */
final readonly class GitHubSponsorship
{
    public function __construct(
        public string $githubId,
        public bool $isOneTime,
        public bool $isActive,
        public CarbonImmutable $sponsoredAt,
    ) {
        //
    }

    /**
     * Maps a Sponsorship node of the GraphQL API, with the other side aliased as `account`. Null when that side is an
     * organization, which can't be on /dev/null.
     *
     * @param  array<array-key, mixed>  $node
     */
    public static function fromNode(array $node): ?self
    {
        $githubId = Arr::get($node, 'account.databaseId');
        $createdAt = Arr::get($node, 'createdAt');
        $tierSelectedAt = Arr::get($node, 'tierSelectedAt');

        if (! is_int($githubId) || ! is_string($createdAt)) {
            return null;
        }

        $sponsoredAt = CarbonImmutable::parse($createdAt);

        if (is_string($tierSelectedAt) && CarbonImmutable::parse($tierSelectedAt)->isAfter($sponsoredAt)) {
            $sponsoredAt = CarbonImmutable::parse($tierSelectedAt);
        }

        return new self(
            githubId: (string) $githubId,
            isOneTime: Arr::get($node, 'isOneTimePayment') === true,
            isActive: Arr::get($node, 'isActive') === true,
            sponsoredAt: $sponsoredAt,
        );
    }

    /**
     * Someone can have sponsored the same maintainer more than once. The one that counts is the active one, or else
     * the latest.
     *
     * @param  list<self>  $sponsorships
     * @return array<string, self>
     */
    public static function latestByAccount(array $sponsorships): array
    {
        $latest = [];

        foreach ($sponsorships as $sponsorship) {
            $current = $latest[$sponsorship->githubId] ?? null;

            if (! $current instanceof self || $sponsorship->outranks($current)) {
                $latest[$sponsorship->githubId] = $sponsorship;
            }
        }

        return $latest;
    }

    /**
     * When it stops counting as active: thirty days after a one-time payment, never while a monthly one runs, and for
     * a monthly one that ended, when it was first seen to have ended.
     */
    public function lapsesAt(?CarbonInterface $known): ?CarbonInterface
    {
        return match (true) {
            $this->isOneTime => $this->sponsoredAt->addDays(Sponsorship::ONE_TIME_DAYS),
            $this->isActive => null,
            default => $known?->isPast() === true ? $known : now(),
        };
    }

    private function outranks(self $other): bool
    {
        if ($this->isActiveNow() !== $other->isActiveNow()) {
            return $this->isActiveNow();
        }

        return $this->sponsoredAt->isAfter($other->sponsoredAt);
    }

    private function isActiveNow(): bool
    {
        return $this->lapsesAt(null)?->isFuture() ?? true;
    }
}
