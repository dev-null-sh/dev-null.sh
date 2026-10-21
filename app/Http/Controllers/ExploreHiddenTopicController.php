<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\DeleteExploreHiddenTopic;
use App\Http\Requests\DeleteExploreHiddenTopicRequest;
use App\Models\ExploreTopic;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;

final readonly class ExploreHiddenTopicController
{
    public function destroy(DeleteExploreHiddenTopicRequest $request, #[CurrentUser] User $user, ExploreTopic $topic, DeleteExploreHiddenTopic $action): RedirectResponse
    {
        $action->handle($user, $topic);

        return back();
    }
}
