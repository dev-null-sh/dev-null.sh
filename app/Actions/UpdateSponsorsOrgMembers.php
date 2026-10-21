<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\OrgRole;
use App\Enums\SponsorsFrom;
use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Models\Sponsorship;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

final readonly class UpdateSponsorsOrgMembers
{
    /**
     * Lets in the sponsors the org takes and lets out everyone else, without logging either, so nobody learns who came
     * or went. Newcomers join hidden, caught up, with the history. Nobody is let in across a block with the
     * maintainer, or again after leaving until they sponsor again.
     */
    public function handle(Conversation $org): void
    {
        DB::transaction(function () use ($org): void {
            $maintainer = $org->participants()->where('role', OrgRole::Maintainer)->firstOrFail()->user;

            $sponsors = Sponsorship::query()
                ->where('sponsorable_id', $maintainer->id)
                ->whereNull('left_at')
                ->when($org->sponsors_from === SponsorsFrom::Monthly, fn (Builder $query): Builder => $query->where('is_one_time', false))
                ->when($org->sponsors_from !== SponsorsFrom::Everyone, fn (Builder $query): Builder => $query
                    ->where(fn (Builder $query): Builder => $query->whereNull('lapses_at')->orWhere('lapses_at', '>', now())))
                ->whereNotIn('sponsor_id', $maintainer->idsAcrossBlocks())
                ->pluck('sponsor_id');

            $members = ConversationParticipant::query()->where('conversation_id', $org->id)->where('role', OrgRole::Contributor);

            (clone $members)->whereNotIn('user_id', $sponsors)->delete();

            $latest = $org->fresh()->last_message_id ?? 0;

            foreach ($sponsors->diff($members->pluck('user_id')) as $sponsorId) {
                ConversationParticipant::query()->create([
                    'conversation_id' => $org->id,
                    'user_id' => $sponsorId,
                    'role' => OrgRole::Contributor,
                    'is_hidden' => true,
                    'read_through_id' => $latest,
                    'last_message_at' => now(),
                ]);
            }
        });
    }
}
