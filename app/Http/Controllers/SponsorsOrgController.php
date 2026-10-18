<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\CreateSponsorsOrg;
use App\Actions\DeleteSponsorsOrg;
use App\Actions\UpdateOrgSponsorsFrom;
use App\Http\Requests\CreateSponsorsOrgRequest;
use App\Http\Requests\DeleteSponsorsOrgRequest;
use App\Http\Requests\UpdateSponsorsOrgRequest;
use App\Models\Conversation;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

final readonly class SponsorsOrgController
{
    /**
     * Straight after, GitHub is asked who the sponsors are, in a full page visit since it leaves the app.
     */
    public function store(CreateSponsorsOrgRequest $request, #[CurrentUser] User $user, CreateSponsorsOrg $action): Response
    {
        $action->handle($user, $request->from());

        return Inertia::location(route('sponsorship-sync.create'));
    }

    public function update(UpdateSponsorsOrgRequest $request, UpdateOrgSponsorsFrom $action): RedirectResponse
    {
        $org = $request->org();
        assert($org instanceof Conversation);

        $action->handle($org, $request->from());

        return back();
    }

    public function destroy(DeleteSponsorsOrgRequest $request, #[CurrentUser] User $user, DeleteSponsorsOrg $action): RedirectResponse
    {
        $action->handle($user);

        return back();
    }
}
