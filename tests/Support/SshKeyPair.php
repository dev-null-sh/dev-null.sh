<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Dto\SshPublicKey;
use phpseclib4\Common\Functions\Strings;

/**
 * An Ed25519 key pair that signs like `ssh-keygen -Y sign`.
 */
final readonly class SshKeyPair
{
    private function __construct(private string $secretKey, public SshPublicKey $publicKey)
    {
        //
    }

    public static function generate(): self
    {
        $keyPair = sodium_crypto_sign_keypair();

        return new self(
            sodium_crypto_sign_secretkey($keyPair),
            SshPublicKey::fromBlob(
                Strings::packSSH2('ss', 'ssh-ed25519', sodium_crypto_sign_publickey($keyPair)),
            ),
        );
    }

    public function sign(string $message, string $namespace = 'devnull-commit'): string
    {
        $signedData = 'SSHSIG'.Strings::packSSH2('ssss', $namespace, '', 'sha512', hash('sha512', $message, true));

        $signature = Strings::packSSH2('ss', 'ssh-ed25519', sodium_crypto_sign_detached($signedData, $this->secretKey));

        return base64_encode('SSHSIG'.Strings::packSSH2('Nsssss', 1, $this->publicKey->blob, $namespace, '', 'sha512', $signature));
    }
}
