<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\UpdateOrgPinRequest;
use App\Models\Conversation;
use Illuminate\Http\Response;

final readonly class OrgPinController
{
    public function update(UpdateOrgPinRequest $request, Conversation $org): Response
    {
        $org->update(['pinned_message_id' => $request->validated('message_id')]);

        return response()->noContent();
    }
}
