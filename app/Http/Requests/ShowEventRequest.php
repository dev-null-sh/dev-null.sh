<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\RepliesPerPage;
use App\Models\Event;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;

final class ShowEventRequest extends FormRequest
{
    public function authorize(): Response
    {
        $event = $this->route('event');
        assert($event instanceof Event);

        return $event->isVisibleTo($this->user()) ? Response::allow() : Response::denyAsNotFound();
    }

    public function repliesPerPage(): int
    {
        return RepliesPerPage::of($this)->value;
    }

    /**
     * @return array{}
     */
    public function rules(): array
    {
        return [];
    }
}
