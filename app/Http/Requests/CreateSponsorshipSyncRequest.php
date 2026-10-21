<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Conversation;
use App\Models\User;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Laravel\Socialite\Socialite;
use Laravel\Socialite\Two\GithubProvider;
use Laravel\Socialite\Two\InvalidStateException;
use Laravel\Socialite\Two\User as GitHubUser;

final class CreateSponsorshipSyncRequest extends FormRequest
{
    public function authorize(): Response
    {
        return Conversation::sponsorsOrgOf($this->signedInUser()) instanceof Conversation ? Response::allow() : Response::denyAsNotFound();
    }

    /**
     * @return array{}
     */
    public function rules(): array
    {
        return [];
    }

    /**
     * The GitHub account GitHub just sent back, or null when the user said no, the state didn't match, or it is
     * someone else's account.
     */
    public function githubUser(): ?GitHubUser
    {
        if ($this->has('error')) {
            return null;
        }

        /** @var GithubProvider $github */
        $github = Socialite::driver('github');

        try {
            $githubUser = $github->redirectUrl(route('sponsorship-sync.store'))->user();
        } catch (InvalidStateException) {
            return null;
        }

        assert($githubUser instanceof GitHubUser);

        return (string) $githubUser->getId() === $this->signedInUser()->github_id ? $githubUser : null;
    }

    private function signedInUser(): User
    {
        $user = $this->user();
        assert($user instanceof User);

        return $user;
    }
}
