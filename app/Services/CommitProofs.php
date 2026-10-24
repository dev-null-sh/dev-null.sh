<?php

declare(strict_types=1);

namespace App\Services;

use App\Dto\SshSignature;
use App\Models\Commit;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final readonly class CommitProofs
{
    /**
     * Check a retained SSH proof before exposing it to a public reader.
     *
     * @return array<string, bool|int|string|null>
     */
    public function get(Commit $commit): array
    {
        /** @var object{signature: string, payload: string, public_key: string, fingerprint: string, namespace: string} $record */
        $record = DB::table('commit_signatures')
            ->where('commit_id', $commit->id)
            ->first() ?? throw new NotFoundHttpException;

        try {
            $signature = SshSignature::parse($record->signature);
        } catch (InvalidArgumentException) {
            throw new NotFoundHttpException;
        }

        throw_unless(
            in_array($record->namespace, [SignedCommits::Namespace, SignedCommits::ImagesNamespace], true)
            && $signature->verify($record->payload, $record->namespace)
            && hash_equals($record->public_key, $signature->publicKey->toOpenSsh())
            && hash_equals($record->fingerprint, $signature->publicKey->fingerprint())
            && hash_equals($record->fingerprint, (string) $commit->signer_fingerprint),
            NotFoundHttpException::class,
        );

        return [
            'version' => 1,
            'commit_id' => $commit->id,
            'html_url' => route('commit.show', [$commit->user, $commit]),
            'signer' => $commit->user?->username,
            'namespace' => $record->namespace,
            'public_key' => $signature->publicKey->toOpenSsh(),
            'fingerprint' => $signature->publicKey->fingerprint(),
            'payload_base64' => base64_encode($record->payload),
            'ssh_signature' => $signature->toArmored(),
            'displayed_body' => $commit->body,
            'note' => 'The signed payload is the original CLI submission. Display text may be normalized or expand emoji shortcodes. GitHub key ownership was checked at submission time, not reverified now.',
        ];
    }
}
