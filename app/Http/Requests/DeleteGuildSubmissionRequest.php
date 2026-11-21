<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\Feature;
use App\Models\GuildSubmission;
use App\Services\Features;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;

final class DeleteGuildSubmissionRequest extends FormRequest
{
    public function authorize(Features $features): Response
    {
        return $this->user()?->isAdmin() && $features->available(Feature::Guilds, $this->user())
            ? Response::allow() : Response::denyAsNotFound();
    }

    /** @return array{} */
    public function rules(): array
    {
        return [];
    }

    public function submission(): ?GuildSubmission
    {
        $id = $this->route('submission');
        assert(is_string($id));

        return GuildSubmission::query()->find($id);
    }
}
