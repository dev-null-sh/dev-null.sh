<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Repo;
use App\Models\User;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;

final class ShowRepoRequest extends FormRequest
{
    /**
     * A repo is private: to anyone outside it, it does not exist.
     */
    public function authorize(): Response
    {
        $user = $this->user();
        assert($user instanceof User);

        $repo = $this->route('repo');
        assert($repo instanceof Repo);

        return $repo->hasContributor($user) ? Response::allow() : Response::denyAsNotFound();
    }

    /**
     * @return array{}
     */
    public function rules(): array
    {
        return [];
    }
}
