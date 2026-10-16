<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Commit;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;

final class ShowCommitRequest extends FormRequest
{
    /**
     * Guests only see approved commits, while authors may also see their own pending or rejected commits.
     */
    public function authorize(): Response
    {
        $user = $this->user();

        $commit = $this->route('commit');
        assert($commit instanceof Commit);

        return $commit->isVisibleTo($user) ? Response::allow() : Response::denyAsNotFound();
    }

    /**
     * @return array{}
     */
    public function rules(): array
    {
        return [];
    }
}
