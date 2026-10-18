<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Talk;
use App\Models\User;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;

final class ShowTalkRequest extends FormRequest
{
    public function authorize(): Response
    {
        $talk = $this->route('talk');
        assert($talk instanceof Talk);

        $user = $this->user();

        return $talk->isVisibleTo($user instanceof User ? $user : null)
            ? Response::allow()
            : Response::denyAsNotFound();
    }

    /**
     * @return array{}
     */
    public function rules(): array
    {
        return [];
    }
}
