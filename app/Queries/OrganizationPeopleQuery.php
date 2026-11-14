<?php

declare(strict_types=1);

namespace App\Queries;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\Pivot;

final readonly class OrganizationPeopleQuery
{
    /**
     * The organization's members, on the People tab of its page, first joined first, as the viewer, or a guest, may see
     * them: without anyone across a block or muted, and with whether the viewer follows each one and each one follows
     * them.
     *
     * @return BelongsToMany<User, Organization>
     */
    public function builder(Organization $organization, ?User $viewer): BelongsToMany
    {
        $people = $organization->members()
            ->oldest('organization_members.created_at')
            ->oldest('users.id');

        if (! $viewer instanceof User) {
            return $people;
        }

        return $people
            ->whereNotIn('users.id', $viewer->idsAcrossBlocks())
            ->whereNotIn('users.id', $viewer->mutedIds())
            ->withExists([
                'followers as is_followed' => fn (Builder $query): Builder => $query->whereKey($viewer->id),
                'following as follows_you' => fn (Builder $query): Builder => $query->whereKey($viewer->id),
            ]);
    }

    /**
     * A page of them, without the roles and join dates of the membership: the page lists its admins already.
     *
     * @return CursorPaginator<int, User&object{pivot: Pivot}>
     */
    public function page(Organization $organization, ?User $viewer): CursorPaginator
    {
        $people = $this->builder($organization, $viewer)
            ->cursorPaginate(30)
            ->appends(['tab' => 'people']);

        foreach ($people->items() as $person) {
            $person->makeHidden('pivot');
        }

        return $people;
    }
}
