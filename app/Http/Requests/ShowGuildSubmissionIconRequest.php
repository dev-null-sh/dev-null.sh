<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\Feature;
use App\Models\GuildSubmission;
use App\Services\Features;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Storage;

final class ShowGuildSubmissionIconRequest extends FormRequest
{
    /** @var resource|null */
    private mixed $icon = null;

    public function authorize(Features $features): Response
    {
        $submission = $this->route('submission');
        assert($submission instanceof GuildSubmission);

        return $this->user()?->isAdmin() && $features->available(Feature::Guilds, $this->user())
            && $submission->icon_path !== null && is_resource($this->icon = Storage::disk()->readStream($submission->icon_path))
            ? Response::allow() : Response::denyAsNotFound();
    }

    /** @return array{} */
    public function rules(): array
    {
        return [];
    }

    /** @return resource */
    public function icon(): mixed
    {
        assert(is_resource($this->icon));

        return $this->icon;
    }
}
