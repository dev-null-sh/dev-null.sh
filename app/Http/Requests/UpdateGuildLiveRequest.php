<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\Feature;
use App\Enums\LivePlatform;
use App\Models\Guild;
use App\Models\User;
use App\Rules\LiveStreamUrl;
use App\Services\Features;
use Illuminate\Auth\Access\Response;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

final class UpdateGuildLiveRequest extends FormRequest
{
    public function authorize(Features $features): Response
    {
        $guild = $this->route('guild');
        assert($guild instanceof Guild);

        return $features->available(Feature::Guilds, $this->user()) && $guild->user_id === $this->user()?->id
            ? Response::allow() : Response::denyAsNotFound();
    }

    /**
     * @return array<string, list<string|ValidationRule>>
     */
    public function rules(): array
    {
        return [
            'url' => ['bail', 'required', 'string', 'max:2048', new LiveStreamUrl()],
            'hours' => ['required', 'integer', 'between:1,'.User::MAX_LIVE_HOURS],
        ];
    }

    public function url(): string
    {
        return $this->string('url')->trim()->value();
    }

    /**
     * Validation already found the platform, so a link without one never gets here.
     */
    public function platform(): LivePlatform
    {
        return LivePlatform::for($this->url()) ?? abort(422);
    }
};
