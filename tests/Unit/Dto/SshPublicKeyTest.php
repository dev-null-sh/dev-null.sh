<?php

declare(strict_types=1);

use App\Dto\SshPublicKey;
use phpseclib4\Common\Functions\Strings;

it('parses public keys like ssh-keygen', function (string $key, string $type, string $fingerprint): void {
    $line = sshFixture("{$key}.pub");

    $publicKey = SshPublicKey::fromOpenSsh($line);

    expect($publicKey->type)->toBe($type)
        ->and($publicKey->fingerprint())->toBe($fingerprint)
        ->and($publicKey->toOpenSsh().' fixture')->toBe(mb_trim($line));
})->with([
    ['ed25519', 'ssh-ed25519', 'SHA256:wQlmkP2ZglsD6TJUUv9mYNQ3P1Pd19k+IndGPwabPHU'],
    ['rsa', 'ssh-rsa', 'SHA256:+2C+8Xqs9EB5Eb1eW0Nuu+i4n7xrbTwLqE8NWY8MTlY'],
]);

it('parses public keys without a comment', function (): void {
    $line = sshFixture('ed25519.pub');

    $publicKey = SshPublicKey::fromOpenSsh(str_replace(' fixture', '', $line));

    expect($publicKey->fingerprint())->toBe('SHA256:wQlmkP2ZglsD6TJUUv9mYNQ3P1Pd19k+IndGPwabPHU');
});

it('rejects malformed public keys', function (string $line): void {
    expect(fn (): SshPublicKey => SshPublicKey::fromOpenSsh($line))
        ->toThrow(InvalidArgumentException::class, 'Malformed SSH public key.');
})->with([
    'empty' => [''],
    'missing key' => ['ssh-ed25519'],
    'not base64' => ['ssh-ed25519 %%%'],
    'type mismatch' => ['ssh-rsa '.base64_encode(Strings::packSSH2('s', 'ssh-ed25519').Strings::packSSH2('s', str_repeat('a', 32)))],
    'short ed25519 key' => ['ssh-ed25519 '.base64_encode(Strings::packSSH2('s', 'ssh-ed25519').Strings::packSSH2('s', 'short'))],
]);

it('rejects truncated and non-canonical key blobs', function (string $blob): void {
    expect(fn (): SshPublicKey => SshPublicKey::fromBlob($blob))
        ->toThrow(InvalidArgumentException::class, 'Malformed SSH public key.');
})->with([
    'truncated' => "\x00\x00",
    'trailing data' => fn (): string => SshPublicKey::fromOpenSsh(sshFixture('ed25519.pub'))->blob.'x',
    'not a curve point' => fn (): string => Strings::packSSH2('ss', 'ssh-ed25519', str_repeat("\xff", 32)),
]);

it('rejects unsupported key types', function (): void {
    $blob = Strings::packSSH2('ss', 'ecdsa-sha2-nistp256', 'nistp256');

    expect(fn (): SshPublicKey => SshPublicKey::fromBlob($blob))
        ->toThrow(InvalidArgumentException::class, 'Unsupported SSH key type [ecdsa-sha2-nistp256]. Use an Ed25519 or RSA key.');
});

it('rejects small rsa keys', function (string $modulus): void {
    $blob = Strings::packSSH2('sss', 'ssh-rsa', "\x01\x00\x01", $modulus);

    expect(fn (): SshPublicKey => SshPublicKey::fromBlob($blob))
        ->toThrow(InvalidArgumentException::class, 'RSA keys must be at least 2048 bits.');
})->with([
    '1024 bits' => "\x00".str_repeat("\xff", 128),
    'empty' => '',
]);

it('rejects signatures made with another algorithm', function (): void {
    $publicKey = SshPublicKey::fromOpenSsh(sshFixture('rsa.pub'));

    $signature = Strings::packSSH2('s', 'ssh-rsa').Strings::packSSH2('s', 'sha1 signature');

    expect($publicKey->verify('data', $signature))->toBeFalse();
});

it('rejects ed25519 signatures of the wrong length', function (): void {
    $publicKey = SshPublicKey::fromOpenSsh(sshFixture('ed25519.pub'));

    $signature = Strings::packSSH2('s', 'ssh-ed25519').Strings::packSSH2('s', 'short');

    expect($publicKey->verify('data', $signature))->toBeFalse();
});

it('may try to parse a public key', function (): void {
    expect(SshPublicKey::tryFromOpenSsh(sshFixture('ed25519.pub'))?->fingerprint())->toBe('SHA256:wQlmkP2ZglsD6TJUUv9mYNQ3P1Pd19k+IndGPwabPHU')
        ->and(SshPublicKey::tryFromOpenSsh('not a key'))->toBeNull()
        ->and(SshPublicKey::tryFromOpenSsh('ecdsa-sha2-nistp256 '.base64_encode(Strings::packSSH2('s', 'ecdsa-sha2-nistp256'))))->toBeNull();
});

it('rejects malformed signature blobs', function (string $signature): void {
    $publicKey = SshPublicKey::fromOpenSsh(sshFixture('ed25519.pub'));

    expect($publicKey->verify('data', $signature))->toBeFalse();
})->with([
    'truncated' => "\x00\x00",
    'trailing data' => fn (): string => Strings::packSSH2('ss', 'ssh-ed25519', str_repeat('a', 64)).'x',
]);
