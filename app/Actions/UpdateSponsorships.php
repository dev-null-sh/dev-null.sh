<?php

declare(strict_types=1);

namespace App\Actions;

use App\Dto\GitHubSponsors;
use App\Dto\GitHubSponsorship;
use App\Enums\OrgRole;
use App\Models\Conversation;
use App\Models\Sponsorship;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final readonly class UpdateSponsorships
{
    public function __construct(private UpdateSponsorsOrgMembers $updateSponsorsOrgMembers)
    {
        //
    }

    /**
     * Mirrors what GitHub just told the user, on both sides: the maintainers they sponsor and, when it was read, their
     * own sponsors. Only pairs where the maintainer runs a sponsors org and both are on /dev/null are kept; the rest
     * is dropped on the spot. The orgs on either side are then brought up to date.
     */
    public function handle(User $user, GitHubSponsors $sponsors): void
    {
        $maintainerIds = DB::transaction(function () use ($user, $sponsors): Collection {
            $maintainerIds = new Collection();

            if ($sponsors->sponsoring !== null) {
                $sponsoring = GitHubSponsorship::latestByAccount($sponsors->sponsoring);
                $maintainers = User::query()
                    ->whereIn('github_id', array_keys($sponsoring))
                    ->whereIn('id', $this->sponsorsOrgs()->join('conversation_participants', 'conversation_participants.conversation_id', '=', 'conversations.id')
                        ->where('conversation_participants.role', OrgRole::Maintainer)
                        ->select('conversation_participants.user_id'))
                    ->get();

                foreach ($maintainers as $maintainer) {
                    $this->mirror($maintainer, $user, $sponsoring[$maintainer->github_id]);
                }

                $ended = $user->sponsorshipsAsSponsor()->whereNotIn('sponsorable_id', $maintainers->pluck('id'))->pluck('sponsorable_id');
                $user->sponsorshipsAsSponsor()->whereIn('sponsorable_id', $ended)->delete();

                $maintainerIds->push(...$maintainers->pluck('id'), ...$ended);
            }

            if ($sponsors->sponsors !== null && Conversation::sponsorsOrgOf($user) instanceof Conversation) {
                $sponsored = GitHubSponsorship::latestByAccount($sponsors->sponsors);
                $people = User::query()->whereIn('github_id', array_keys($sponsored))->whereKeyNot($user->id)->get();

                foreach ($people as $sponsor) {
                    $this->mirror($user, $sponsor, $sponsored[$sponsor->github_id]);
                }

                $user->sponsorshipsAsMaintainer()->whereNotIn('sponsor_id', $people->pluck('id'))->delete();

                $maintainerIds->push($user->id);
            }

            return $maintainerIds;
        });

        $this->sponsorsOrgs()
            ->whereHas('participants', fn (Builder $query): Builder => $query->where('role', OrgRole::Maintainer)->whereIn('user_id', $maintainerIds->unique()))
            ->get()
            ->each(fn (Conversation $org) => $this->updateSponsorsOrgMembers->handle($org));
    }

    /**
     * Someone who left the org, or was removed, stays out until they sponsor again.
     */
    private function mirror(User $maintainer, User $sponsor, GitHubSponsorship $sponsorship): void
    {
        $known = Sponsorship::query()->where('sponsorable_id', $maintainer->id)->where('sponsor_id', $sponsor->id)->first();
        $leftAt = $known?->left_at;

        Sponsorship::query()->updateOrCreate([
            'sponsorable_id' => $maintainer->id,
            'sponsor_id' => $sponsor->id,
        ], [
            'is_one_time' => $sponsorship->isOneTime,
            'lapses_at' => $sponsorship->lapsesAt($known?->lapses_at),
            'left_at' => $leftAt !== null && $sponsorship->sponsoredAt->isAfter($leftAt) ? null : $leftAt,
        ]);
    }

    /**
     * @return Builder<Conversation>
     */
    private function sponsorsOrgs(): Builder
    {
        return Conversation::query()->whereNotNull('sponsors_from');
    }
}
