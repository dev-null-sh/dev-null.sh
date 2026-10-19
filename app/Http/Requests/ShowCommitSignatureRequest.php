<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Commit;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;

final class ShowCommitSignatureRequest extends FormRequest
{
    public function authorize(): Response
    {
        $commit = $this->route('commit');
        assert($commit instanceof Commit);

        return $commit->signed_at !== null && $commit->isVisibleTo($this->user()) ? Response::allow() : Response::denyAsNotFound();
    }

    /**
     * @return array{}
     */
    public function rules(): array
    {
        return [];
    }
}
