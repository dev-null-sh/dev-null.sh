<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Dto\SshPublicKey;
use App\Models\SshKey;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

final class CreateKeyLookupRequest extends FormRequest
{
    private const int MaxKeys = 20;

    public function authorize(): Response
    {
        return $this->publicKey() instanceof SshPublicKey
            ? Response::allow()
            : Response::denyAsNotFound('None of your SSH keys are on the GitHub account of a /dev/null user. Add your key on GitHub (Settings → SSH and GPG keys), then sign in on '.url('/').'.');
    }

    /**
     * @return array{}
     */
    public function rules(): array
    {
        return [];
    }

    /**
     * Picks, among the public keys sent by the `devnull commit` script, one that belongs to a devnull user.
     *
     * Anyone can call this endpoint, so it only reads the database: GitHub is checked when the signed commit is sent.
     */
    public function publicKey(): ?SshPublicKey
    {
        return once(function (): ?SshPublicKey {
            $publicKeys = Str::of($this->getContent())
                ->explode("\n")
                ->take(self::MaxKeys)
                ->map(fn (string $line): ?SshPublicKey => SshPublicKey::tryFromOpenSsh($line))
                ->filter()
                ->keyBy(fn (SshPublicKey $publicKey): string => $publicKey->fingerprint());

            $knownFingerprints = SshKey::query()->whereIn('fingerprint', $publicKeys->keys())->pluck('fingerprint');

            return $publicKeys->first(fn (SshPublicKey $publicKey, string $fingerprint): bool => $knownFingerprints->contains($fingerprint));
        });
    }
}
