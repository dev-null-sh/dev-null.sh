<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\CreateBroadcastPublisher;
use App\Actions\DeleteBroadcastPublisher;
use App\Http\Requests\CreateBroadcastPublisherRequest;
use App\Http\Requests\DeleteBroadcastPublisherRequest;
use App\Models\Conversation;
use App\Models\User;
use Illuminate\Http\Response;

final readonly class BroadcastPublisherController
{
    public function store(CreateBroadcastPublisherRequest $request, Conversation $broadcast, User $publisher, CreateBroadcastPublisher $action): Response
    {
        $action->handle($broadcast, $publisher);

        return response()->noContent();
    }

    public function destroy(DeleteBroadcastPublisherRequest $request, Conversation $broadcast, User $publisher, DeleteBroadcastPublisher $action): Response
    {
        $action->handle($broadcast, $publisher);

        return response()->noContent();
    }
}
