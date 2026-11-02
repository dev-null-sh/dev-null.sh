<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

final class ShowProfileLookupRequest extends FormRequest
{
    /**
     * Only a handle GitHub could have can be looked up. Someone who deleted their account here left for good: their
     * handle answers a missing page, like one GitHub could not have.
     */
    public function authorize(): Response
    {
        $handle = Str::lower($this->handle());

        return preg_match('/^[a-z0-9](?:[a-z0-9]|-(?=[a-z0-9])){0,38}$/', $handle) === 1 && Cache::get("github-profiles:not-found:{$handle}") !== 'deleted'
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

    /**
     * The "@handle" no user or organization has, as typed.
     */
    public function handle(): string
    {
        $handle = $this->route('user');
        assert(is_string($handle));

        return $handle;
    }
}
