<?php

declare(strict_types=1);

namespace App\Services;

use App\Actions\CreateCommitImages;
use App\Models\User;
use Closure;
use InvalidArgumentException;

/**
 * Verifies a commit signed with `ssh-keygen -Y sign` by the `devnull commit` script.
 *
 * The signed payload is "<unix timestamp>\n<nonce>\n<commit to reply to, or empty>\n<body>". A commit
 * with images is signed under its own namespace, so the signature states which format it covers, and
 * carries one more line before the body: the comma-separated SHA-256 of each image, in the order
 * they are attached, so the signature covers the images as well as the text.
 *
 * The line of the commit to reply to holds "quote:<commit>" for a quote instead: a reference never
 * starts with that prefix, so what older scripts sign keeps its meaning.
 */
final readonly class SignedCommits
{
    public const string Namespace = 'devnull-commit';

    public const string ImagesNamespace = 'devnull-commit-images';

    public const string QuotePrefix = 'quote:';

    public function __construct(private SshSignatures $sshSignatures)
    {
        //
    }

    /**
     * @param  (Closure(): list<string>)|null  $imageHashes
     * @return array{user: User, reply_to: string|null, quote: string|null, body: string}
     */
    public function verify(string $payload, string $signature, ?Closure $imageHashes = null): array
    {
        $withImages = $imageHashes instanceof Closure;

        $publicKey = $this->sshSignatures->verify($payload, $signature, $withImages ? self::ImagesNamespace : self::Namespace);

        $lines = $withImages ? 5 : 4;
        $parts = explode("\n", $payload, $lines);

        throw_if(count($parts) !== $lines || ! mb_check_encoding($payload, 'UTF-8'), InvalidArgumentException::class, 'Malformed payload.');

        if ($withImages) {
            [$timestamp, $nonce, $replyTo, $signedHashes, $body] = $parts;
        } else {
            [$timestamp, $nonce, $replyTo, $body] = $parts;
            $signedHashes = '';
        }

        $quote = str_starts_with($replyTo, self::QuotePrefix) ? mb_substr($replyTo, mb_strlen(self::QuotePrefix)) : null;

        throw_if($quote === '', InvalidArgumentException::class, 'Malformed payload.');

        throw_if(
            $withImages && preg_match('/^[0-9a-f]{64}(,[0-9a-f]{64}){0,'.(CreateCommitImages::MAX_IMAGES - 1).'}$/', $signedHashes) !== 1,
            InvalidArgumentException::class,
            'Malformed payload.',
        );

        $user = $this->sshSignatures->authenticate($publicKey, $timestamp, $nonce);

        throw_if(
            ! $user instanceof User,
            InvalidArgumentException::class,
            "This SSH key ({$publicKey->fingerprint()}) is not on the GitHub account of a /dev/null user.",
        );

        if ($withImages) {
            throw_unless(
                hash_equals($signedHashes, implode(',', $imageHashes())),
                InvalidArgumentException::class,
                'The attached images do not match the signed payload.',
            );
        }

        return [
            'user' => $user,
            'reply_to' => $replyTo === '' || $quote !== null ? null : $replyTo,
            'quote' => $quote,
            'body' => mb_trim($body),
        ];
    }
}
