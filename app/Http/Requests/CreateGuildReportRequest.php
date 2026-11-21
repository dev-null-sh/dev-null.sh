<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\Feature;
use App\Models\Guild;
use App\Services\Features;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;

final class CreateGuildReportRequest extends FormRequest
{
    public function authorize(Features $features): Response
    {
        $guild = $this->route('guild');
        assert($guild instanceof Guild);

        return $features->available(Feature::Guilds, $this->user()) && $guild->user_id !== $this->user()?->id
            ? Response::allow() : Response::denyAsNotFound();
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return ['details' => ['required', 'string', 'max:500']];
    }
}
