<?php

declare(strict_types=1);

namespace App\Queries;

use App\Contracts\Services\GitHubProvider;
use App\Models\User;
use Illuminate\Http\Client\HttpClientException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * @phpstan-type SponsorTier array{amount: int, sponsors: list<array{login: string, github_id: string}>}
 * @phpstan-type Tier array{amount: int, partners: list<array{login: string, handle: string|null}>}
 */
final readonly class PartnersQuery
{
    /**
     * The amount each tier shows, in dollars, with the smallest total that reaches it, in cents, the biggest first. A
     * total one dollar short still reaches it, so 499 dollars is in the 500 tier. The last one holds every other public
     * sponsor.
     */
    private const array Minimums = [
        50000 => 4_999_900,
        20000 => 1_999_900,
        5000 => 499_900,
        500 => 49_900,
        0 => 0,
    ];

    private const int Ttl = 3600;

    private const int FailedTtl = 60;

    public function __construct(private GitHubProvider $github)
    {
        //
    }

    /**
     * Every tier, the biggest first, with the public sponsors whose total over time reaches it but not the next one,
     * so the page shows who gave the most without the amounts. Each one has their /dev/null handle, or null when they
     * aren't on it, found by their GitHub account. Null when GitHub didn't answer.
     *
     * @return list<Tier>|null
     */
    public function get(): ?array
    {
        $tiers = $this->sponsorTiers();

        if ($tiers === null) {
            return null;
        }

        $handles = User::query()
            ->whereIn('github_id', array_merge(...array_map(fn (array $tier): array => array_column($tier['sponsors'], 'github_id'), $tiers)))
            ->get()
            ->mapWithKeys(fn (User $user): array => [$user->github_id => $user->handle]);

        return array_map(fn (array $tier): array => [
            'amount' => $tier['amount'],
            'partners' => array_map(fn (array $sponsor): array => [
                'login' => $sponsor['login'],
                'handle' => $handles->get($sponsor['github_id']),
            ], $tier['sponsors']),
        ], $tiers);
    }

    /**
     * Kept for an hour, and a failed read for a minute, so an outage doesn't send every visitor to GitHub.
     *
     * @return list<SponsorTier>|null
     */
    private function sponsorTiers(): ?array
    {
        /** @var list<SponsorTier>|false|null $tiers */
        $tiers = Cache::get('partner-sponsor-tiers');

        if ($tiers === null) {
            $organization = Str::before(config()->string('services.github.repository'), '/');

            try {
                $sponsors = $this->github->publicSponsors($organization);

                $tiers = array_map(fn (int $amount): array => [
                    'amount' => $amount,
                    'sponsors' => array_values(array_map(
                        fn (array $sponsor): array => ['login' => $sponsor['login'], 'github_id' => $sponsor['github_id']],
                        array_filter($sponsors, fn (array $sponsor): bool => Arr::first(
                            array_keys(self::Minimums),
                            fn (int $tierAmount): bool => $sponsor['amount_in_cents'] >= self::Minimums[$tierAmount],
                        ) === $amount),
                    )),
                ], array_keys(self::Minimums));
            } catch (HttpClientException) {
                $tiers = false;
            }

            Cache::put('partner-sponsor-tiers', $tiers, $tiers === false ? self::FailedTtl : self::Ttl);
        }

        return $tiers === false ? null : $tiers;
    }
}
