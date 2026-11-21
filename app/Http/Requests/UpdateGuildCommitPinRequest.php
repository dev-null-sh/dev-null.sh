<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\Feature;
use App\Models\Commit;
use App\Models\Guild;
use App\Models\User;
use App\Models\UserTimeout;
use App\Queries\GuildCommitsQuery;
use App\Services\Features;
use Closure;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;
use Inertia\Inertia;

final class UpdateGuildCommitPinRequest extends FormRequest
{
    public function authorize(Features $features, GuildCommitsQuery $query): Response
    {
        $guild = $this->route('guild');
        assert($guild instanceof Guild);
        $commit = $this->route('commit');
        assert($commit instanceof Commit);
        $user = $this->user();
        assert($user instanceof User);

        return $features->available(Feature::Guilds, $user) && $guild->user_id === $user->id
            && $commit->parent_id === null
            && (($guild->pinned_commit_id === $commit->id && in_array($this->input('pinned'), [false, 0, '0'], true))
                || $query->rootIds($guild, $user, $commit->id)->isNotEmpty())
            ? Response::allow() : Response::denyAsNotFound();
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'pinned' => ['required', 'boolean'],
        ];
    }

    /** @return list<Closure(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $user = $this->user();
            assert($user instanceof User);
            $timeout = $user->activeTimeout();

            if ($timeout instanceof UserTimeout) {
                Inertia::flash('timeout', $timeout->details());
                $validator->errors()->add('pin', $timeout->message());
            }
        }];
    }
}
