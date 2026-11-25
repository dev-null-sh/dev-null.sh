<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\ApiErrorCode;
use App\Enums\Feature;
use App\Exceptions\ApiException;
use App\Models\Commit;
use App\Models\Guild;
use App\Models\User;
use App\Models\UserTimeout;
use App\Queries\GuildCommitsQuery;
use App\Services\Features;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;

final class DeleteApiGuildCommitPinRequest extends FormRequest
{
    /**
     * Only the guild's moderator unpins, also a pin the board no longer shows, as on the web. Anyone else gets a 404
     * before a moderator in timeout is told why they can't, as the web checks the timeout after authorizing.
     */
    public function authorize(Features $features, GuildCommitsQuery $query): Response
    {
        $guild = $this->route('guild');
        assert($guild instanceof Guild);
        $commit = $this->route('commit');
        assert($commit instanceof Commit);
        $user = $this->user();
        assert($user instanceof User);

        $allowed = $features->available(Feature::Guilds, $user) && $guild->user_id === $user->id && $commit->parent_id === null
            && ($guild->pinned_commit_id === $commit->id || $query->rootIds($guild, $user, $commit->id)->isNotEmpty());

        if (! $allowed) {
            return Response::denyAsNotFound();
        }

        $timeout = $user->activeTimeout();

        if ($timeout instanceof UserTimeout) {
            throw new ApiException(ApiErrorCode::TimedOut, $timeout->message());
        }

        return Response::allow();
    }

    /** @return array{} */
    public function rules(): array
    {
        return [];
    }
}
