<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Dto\GitHubAccount;
use App\Models\User;
use App\Queries\GitHubAccountQuery;
use App\Queries\PendingMentionCommitsQuery;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;

final class ShowUserRequest extends FormRequest
{
    private ?User $profile = null;

    private ?GitHubAccount $account = null;

    /**
     * A username nobody on /dev/null has still has a profile, waiting to be claimed, once a commit anyone may see
     * mentions it and GitHub knows the account. Any other username is not found.
     */
    public function authorize(PendingMentionCommitsQuery $mentions, GitHubAccountQuery $accounts): Response
    {
        $username = $this->route('user');
        assert(is_string($username));

        $this->profile = User::query()->where('username', $username)->first();

        if ($this->profile instanceof User) {
            return Response::allow();
        }

        $this->account = $mentions->exists($username) ? $accounts->get($username) : null;

        return $this->account instanceof GitHubAccount ? Response::allow() : Response::denyAsNotFound();
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'tab' => ['nullable', 'string', 'in:commits,replies'],
        ];
    }

    public function tab(): string
    {
        return $this->input('tab') === 'replies' ? 'replies' : 'commits';
    }

    /**
     * The user whose profile it is, or null when the profile is waiting to be claimed.
     */
    public function profile(): ?User
    {
        return $this->profile;
    }

    /**
     * The GitHub account of a profile waiting to be claimed.
     */
    public function account(): GitHubAccount
    {
        assert($this->account instanceof GitHubAccount);

        return $this->account;
    }
}
