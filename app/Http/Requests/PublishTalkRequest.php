<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Talk;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;

final class PublishTalkRequest extends FormRequest
{
    public function authorize(): Response
    {
        $talk = $this->route('talk');
        assert($talk instanceof Talk);

        return $talk->user_id === $this->user()?->id
            ? Response::allow()
            : Response::denyAsNotFound();
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'unlisted' => ['sometimes', 'boolean'],
        ];
    }
}
