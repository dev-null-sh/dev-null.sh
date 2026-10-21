<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\CreateExploreFeedback;
use App\Http\Requests\CreateExploreFeedbackRequest;
use App\Models\ExploreTopic;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;

final readonly class ExploreFeedbackController
{
    public function store(CreateExploreFeedbackRequest $request, #[CurrentUser] User $user, ExploreTopic $topic, CreateExploreFeedback $action): RedirectResponse
    {
        $action->handle($user, $topic, $request->string('reason')->value());

        return to_route('explore.index');
    }
}
