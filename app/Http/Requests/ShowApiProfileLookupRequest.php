<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Profile;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

final class ShowApiProfileLookupRequest extends FormRequest
{
    /**
     * Only a handle GitHub could have can be looked up. Someone who deleted their account here left for good, so their
     * handle is missing, like one GitHub could not have. So is a handle a profile has, as a lookup that found it does.
     */
    public function authorize(): Response
    {
        $handle = Str::lower($this->handle());

        return preg_match('/^[a-z0-9](?:[a-z0-9]|-(?=[a-z0-9])){0,38}$/', $handle) === 1
            && Cache::get("github-profiles:not-found:{$handle}") !== 'deleted'
            && Profile::query()->where('handle', $this->handle())->doesntExist()
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

    public function handle(): string
    {
        $handle = $this->route('handle');
        assert(is_string($handle));

        return $handle;
    }
}
