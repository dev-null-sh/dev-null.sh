<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Commit;
use App\Models\Event;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Storage;

final class ShowEventCoverRequest extends FormRequest
{
    /**
     * Covers follow the visibility of commit images: approved ones for everyone, guests included, the rest for authors and moderators.
     */
    public function authorize(): Response
    {
        $event = $this->route('event');
        assert($event instanceof Event);

        $commit = Commit::query()->find($event->commit_id);

        return $event->cover_path !== null
            && $commit instanceof Commit
            && $commit->imagesAreVisibleTo($this->user())
            && Storage::disk()->exists($event->cover_path)
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
