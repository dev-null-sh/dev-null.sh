<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Actions\CreateContributorRequest;
use App\Actions\UpdateContributorRequest;
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
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final readonly class ContributorRequestController
{
    public function index(ListContributorRequestsRequest $request, #[CurrentUser] ?User $user, ContributorRequestsQuery $requests): AnonymousResourceCollection
    {
        return ContributorRequestResource::collection($requests->builder(
            $user, $request->enum('kind', ContributorHelp::class), $request->string('status', 'open')->value(),
        )->paginate(12)->withQueryString());
    }

    public function store(CreateContributorRequestRequest $request, #[CurrentUser] User $user, CreateContributorRequest $action, ContributorRequestsQuery $requests): JsonResponse
    {
        $item = $action->handle($user, $request->string('body')->value(), $request->string('project_url')->value(), ContributorHelp::from($request->string('kind')->value()));

        return ContributorRequestResource::make($requests->builder($user, status: 'all')->findOrFail($item->id))->response()->setStatusCode(201);
    }

    public function update(UpdateContributorRequestRequest $request, #[CurrentUser] User $user, ContributorRequest $contributorRequest, UpdateContributorRequest $action, ContributorRequestsQuery $requests): ContributorRequestResource
    {
        $action->handle($contributorRequest, ContributorRequestStatus::from($request->string('status')->value()));

        return ContributorRequestResource::make($requests->builder($user, status: 'all')->findOrFail($contributorRequest->id));
    }
}
