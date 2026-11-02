<?php

declare(strict_types=1);

use App\Services\EncryptedFiles;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Encryption\Encrypter;

it('encrypts files as raw bytes, 29 bytes larger, and decrypts them', function (): void {
    $files = resolve(EncryptedFiles::class);
    $contents = random_bytes(4096);

    $encrypted = $files->encrypt($contents);

    expect(mb_strlen($encrypted, '8bit'))->toBe(4096 + 29)
        ->and($encrypted)->not->toContain(mb_substr($contents, 0, 32, '8bit'))
        ->and($files->encrypt($contents))->not->toBe($encrypted)
        ->and($files->decrypt($encrypted))->toBe($contents);
});

it('refuses files that were changed, are plain, or were encrypted under another key', function (string $file): void {
    resolve(EncryptedFiles::class)->decrypt($file);
})->with([
    'changed' => fn (): string => resolve(EncryptedFiles::class)->encrypt('voice').'!',
    'plain' => fn (): string => 'RIFF....WEBPVP8 ',
    'another version' => fn (): string => "\x02".mb_substr(resolve(EncryptedFiles::class)->encrypt('voice'), 1, null, '8bit'),
    'another key' => fn (): string => new EncryptedFiles(new Encrypter(random_bytes(32), 'AES-256-CBC'))->encrypt('voice'),
])->throws(DecryptException::class);

it('reads files encrypted under a previous app key', function (): void {
    $previous = random_bytes(32);
    $file = new EncryptedFiles(new Encrypter($previous, 'AES-256-CBC'))->encrypt('voice');

    $rotated = new EncryptedFiles(new Encrypter(random_bytes(32), 'AES-256-CBC')->previousKeys([$previous]));

    expect($rotated->decrypt($file))->toBe('voice');
});
