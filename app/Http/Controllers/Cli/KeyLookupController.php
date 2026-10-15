<?php

declare(strict_types=1);

namespace App\Http\Controllers\Cli;

use App\Dto\SshPublicKey;
use App\Models\SshKey;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;

final readonly class KeyLookupController
{
    private const int MaxKeys = 20;

    /**
     * Picks, among the public keys sent by the `devnull commit` script, one that belongs to a devnull user,
     * so the script only asks the SSH agent (or a hardware key) to sign with that one.
     *
     * Anyone can call this endpoint, so it only reads the database: GitHub is checked when the signed commit is sent.
     */
    public function store(Request $request): Response
    {
        $publicKeys = Str::of($request->getContent())
            ->explode("\n")
            ->take(self::MaxKeys)
            ->map(fn (string $line): ?SshPublicKey => SshPublicKey::tryFromOpenSsh($line))
            ->filter()
            ->keyBy(fn (SshPublicKey $publicKey): string => $publicKey->fingerprint());

        $knownFingerprints = SshKey::query()->whereIn('fingerprint', $publicKeys->keys())->pluck('fingerprint');

        $publicKey = $publicKeys->first(fn (SshPublicKey $publicKey, string $fingerprint): bool => $knownFingerprints->contains($fingerprint));

        abort_if(
            $publicKey === null,
            404,
            __('None of your SSH keys are on the GitHub account of a devnull user. Add your key on GitHub (Settings → SSH and GPG keys), then sign in on :url.', ['url' => url('/')]),
        );

        return response($publicKey->toOpenSsh()."\n")->header('Content-Type', 'text/plain; charset=utf-8');
    }
}
