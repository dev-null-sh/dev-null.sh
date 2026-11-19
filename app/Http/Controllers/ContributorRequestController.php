<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\CreateContributorRequest;
use App\Actions\UpdateContributorRequest;
use App\Enums\CommitStatus;
use App\Enums\ContributorHelp;
use App\Enums\ContributorRequestStatus;
use App\Http\Requests\CreateContributorRequestRequest;
use App\Http\Requests\ListContributorRequestsRequest;
use App\Http\Requests\UpdateContributorRequestRequest;
use App\Http\Resources\ContributorRequestResource;
use App\Models\ContributorRequest;
use App\Models\User;
use App\Queries\ContributorRequestsQuery;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

final readonly class ContributorRequestController
{
    public function index(ListContributorRequestsRequest $request, #[CurrentUser] ?User $user, ContributorRequestsQuery $requests): Response
    {
        $kind = $request->enum('kind', ContributorHelp::class);
        $status = $request->string('status', 'open')->value();

        return Inertia::render('contributor-request/index', [
            'requests' => $requests->builder($user, $kind, $status)->paginate(12)->withQueryString()
                ->through(fn (ContributorRequest $item): array => ContributorRequestResource::make($item)->resolve($request)),
            'filters' => ['kind' => $kind?->value, 'status' => $status],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('contributor-request/create');
    }

    public function store(CreateContributorRequestRequest $request, #[CurrentUser] User $user, CreateContributorRequest $action): RedirectResponse
    {
        $item = $action->handle($user, $request->string('body')->value(), $request->string('project_url')->value(), ContributorHelp::from($request->string('kind')->value()));
        $pending = $item->commit?->status === CommitStatus::Pending;
        Inertia::flash('toast', ['type' => $pending ? 'info' : 'success', 'message' => $pending
            ? 'Request posted. Only you can see it until moderation approves it.' : 'Contributor request posted.']);

        return to_route('contributor-request.index');
    }

    public function update(UpdateContributorRequestRequest $request, ContributorRequest $contributorRequest, UpdateContributorRequest $action): RedirectResponse
    {
        $action->handle($contributorRequest, ContributorRequestStatus::from($request->string('status')->value()));
        Inertia::flash('toast', ['type' => 'success', 'message' => $contributorRequest->status === ContributorRequestStatus::Filled ? 'Request marked as filled.' : 'Request reopened.']);

        return back();
    }
}
