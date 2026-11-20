<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\Feature;
use App\Models\Guild;
use App\Models\User;
use App\Services\Features;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Storage;

final class ShowApiGuildIconRequest extends FormRequest
{
    /**
     * The URL was signed for one viewer, or for a guest, so it stops working as soon as guilds are no longer available
     * to them, as the web's icon does, as well as when it expires.
     */
    public function authorize(Features $features): Response
    {
        $guild = $this->route('guild');
        assert($guild instanceof Guild);

        $viewer = $this->query('user');
        $user = is_string($viewer) && $viewer !== '' ? User::query()->find($viewer) : null;

        return $features->available(Feature::Guilds, $user) && $guild->icon_path !== null && Storage::disk()->exists($guild->icon_path)
            ? Response::allow() : Response::denyAsNotFound();
    }

    /** @return array{} */
    public function rules(): array
    {
        return [];
    }
}
