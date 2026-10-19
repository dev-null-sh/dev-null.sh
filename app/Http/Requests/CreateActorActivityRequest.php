<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\User;
use App\Services\HttpSignatures;
use App\Services\RemoteActors;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;

final class CreateActorActivityRequest extends FormRequest
{
    /**
     * @var array<mixed>|null
     */
    private ?array $signer = null;

    /**
     * Only follows and unfollows are acted on. Anything else is let through to be dropped before the signature is
     * checked, as checking it means fetching the sender's key, and Mastodon sends account deletions to every server it
     * knows.
     */
    public function authorize(HttpSignatures $signatures, RemoteActors $actors): Response
    {
        $user = $this->route('user');
        assert($user instanceof User);

        if (! $user->federates) {
            return Response::denyAsNotFound();
        }

        if (! $this->isActedOn()) {
            return Response::allow();
        }

        $this->signer = $this->signedBy($signatures, $actors);

        return $this->signer === null ? Response::denyWithStatus(401) : Response::allow();
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [];
    }

    /**
     * @return array<mixed>
     */
    public function activity(): array
    {
        $activity = json_decode($this->getContent(), associative: true);

        return is_array($activity) ? $activity : [];
    }

    public function isActedOn(): bool
    {
        return in_array($this->activity()['type'] ?? null, ['Follow', 'Undo'], true);
    }

    /**
     * @return array<mixed>
     */
    public function signer(): array
    {
        assert($this->signer !== null);

        return $this->signer;
    }

    /**
     * The actor who signed the request, when the signature checks out and that actor is the one acting.
     *
     * @return array<mixed>|null
     */
    private function signedBy(HttpSignatures $signatures, RemoteActors $actors): ?array
    {
        $keyId = $signatures->keyId($this);
        $user = $this->route('user');
        assert($user instanceof User);

        $actor = $keyId === null ? null : $actors->find($keyId, $user);
        $publicKey = is_array($actor) ? ($actor['publicKey'] ?? null) : null;

        if (! is_array($actor) || ! is_array($publicKey)
            || ($publicKey['id'] ?? null) !== $keyId
            || ($publicKey['owner'] ?? null) !== $actor['id']
            || ($this->activity()['actor'] ?? null) !== $actor['id']
            || ! is_string($publicKey['publicKeyPem'] ?? null)) {
            return null;
        }

        return $signatures->verify($this, $publicKey['publicKeyPem']) ? $actor : null;
    }
}
