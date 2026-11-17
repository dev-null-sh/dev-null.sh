<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\Feature;
use App\Services\Features;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;

final class DeleteGuildRequest extends FormRequest
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
}
