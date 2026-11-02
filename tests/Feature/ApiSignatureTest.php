<?php

declare(strict_types=1);

use App\Dto\SshPublicKey;
use App\Models\Commit;
use App\Models\Device;
use App\Models\SshKey;
use App\Models\User;
use App\Services\SignedRequests;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Tests\Support\SshKeyPair;

beforeEach(function (): void {
    $this->user = User::factory()->create();
    $this->keyPair = apiKeyPairFor($this->user);
});

it('authenticates a request signed with a key the user approved as a device', function (): void {
    $response = signedApiRequest($this->keyPair, 'GET', route('api-current-user.show'));

    $response->assertOk()
        ->assertJsonPath('data.id', $this->user->id)
        ->assertCookieMissing(config('session.cookie'));

    $this->assertAuthenticatedAs($this->user);
});

it('accepts requests signed by ssh-keygen over the documented message', function (string $message, string $signature, int $status): void {
    $this->travelTo(CarbonImmutable::createFromTimestamp(1791000000));

    // Made with `ssh-keygen -t ed25519`, then `ssh-keygen -Y sign -n devnull-api` over each message...
    $publicKey = SshPublicKey::fromOpenSsh('ssh-ed25519 AAAAC3NzaC1lZDI1NTE5AAAAIIrhcHdLGpoKRYqI6eBWDeFu3VITYyTHDGiQ7QhYXCPa vector');
    $user = User::factory()->create();

    Device::factory()->for($user)->withPublicKey($publicKey)->create();

    [$timestamp, $nonce, $method, $target, $body] = explode("\n", $message, 5);

    $response = $this->call($method, $target, server: [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_X_DEVNULL_TIMESTAMP' => $timestamp,
        'HTTP_X_DEVNULL_NONCE' => $nonce,
        'HTTP_X_SSH_SIGNATURE' => $signature,
    ], content: $body);

    $response->assertStatus($status);

    $this->assertAuthenticatedAs($user);
})->with([
    'with a query and no body' => [
        "1791000000\n4f3c2b1a09e8d7c6b5a4938271605f4e\nGET\n/api/v1/log/latest?cursor=abc\n",
        'U1NIU0lHAAAAAQAAADMAAAALc3NoLWVkMjU1MTkAAAAgiuFwd0samgpFiojp4FYN4W7dUhNjJMcMaJDtCFhcI9oAAAALZGV2bnVsbC1hcGkAAAAAAAAABnNoYTUxMgAAAFMAAAALc3NoLWVkMjU1MTkAAABAKZ8la0GdO1nis8Bj7T2TApm89jTsN0of83/YJILbEsR6k/1q08FOHkSl3eZRIPoRVIjJTkXvqWhKlGMe31QwBw==',
        200,
    ],
    'with the raw body, spaces and trailing newline included' => [
        "1791000000\n0123456789abcdef0123456789abcdef\nPOST\n/api/v1/commits\n{\"body\": \"It was DNS.\", \"parent_id\": null}\n",
        'U1NIU0lHAAAAAQAAADMAAAALc3NoLWVkMjU1MTkAAAAgiuFwd0samgpFiojp4FYN4W7dUhNjJMcMaJDtCFhcI9oAAAALZGV2bnVsbC1hcGkAAAAAAAAABnNoYTUxMgAAAFMAAAALc3NoLWVkMjU1MTkAAABAZrHU0K+nsKCLlC5abo8rZbyiRE0aBSBDvXD2285XW8VE/L1Zx6VBP+Hx+XltHDV9TfGNJPDW+lmrHvsIEjU8BA==',
        201,
    ],
]);

it('rejects a request that differs from the one that was signed', function (): void {
    $server = signApiRequest($this->keyPair, 'GET', route('api-current-user.show'));

    $response = $this->call('GET', route('api-current-user.show', ['tampered' => 1]), server: $server);

    $response->assertUnauthorized()
        ->assertJsonPath('message', 'Invalid signature.')
        ->assertJsonPath('code', 'invalid_signature');
});

it('rejects a signature for another namespace', function (): void {
    $response = signedApiRequest($this->keyPair, 'GET', route('api-current-user.show'), namespace: 'devnull-commit');

    $response->assertUnauthorized()
        ->assertJsonPath('message', 'Invalid signature.')
        ->assertJsonPath('code', 'invalid_signature');
});

it('rejects a request without a signature', function (): void {
    $response = $this->getJson(route('api-current-user.show'));

    $response->assertUnauthorized()
        ->assertJsonPath('message', 'Malformed SSH signature.')
        ->assertJsonPath('code', 'invalid_signature');
});

it('rejects an expired or future timestamp', function (int $offset): void {
    $response = signedApiRequest($this->keyPair, 'GET', route('api-current-user.show'), timestamp: now()->getTimestamp() + $offset);

    $response->assertUnauthorized()
        ->assertJsonPath('message', 'Signature expired. Check that your clock is right.')
        ->assertJsonPath('code', 'invalid_signature');
})->with([-301, 301]);

it('rejects a malformed nonce', function (): void {
    $response = signedApiRequest($this->keyPair, 'GET', route('api-current-user.show'), nonce: 'nonce');

    $response->assertUnauthorized()
        ->assertJsonPath('message', 'Malformed payload.')
        ->assertJsonPath('code', 'invalid_signature');
});

it('rejects a reused nonce', function (): void {
    $nonce = bin2hex(random_bytes(16));

    signedApiRequest($this->keyPair, 'GET', route('api-current-user.show'), nonce: $nonce)->assertOk();

    $response = signedApiRequest($this->keyPair, 'GET', route('api-current-user.show'), nonce: $nonce);

    $response->assertUnauthorized()
        ->assertJsonPath('message', 'This signature was already used.')
        ->assertJsonPath('code', 'invalid_signature');
});

it('rejects a key nobody approved as a device, without asking github', function (): void {
    $keyPair = SshKeyPair::generate();
    $user = User::factory()->create();

    // Even on the user's GitHub account, a key needs approving on the web...
    SshKey::factory()->for($user)->withPublicKey($keyPair->publicKey)->create();

    $response = signedApiRequest($keyPair, 'GET', route('api-current-user.show'));

    $response->assertUnauthorized()
        ->assertExactJson([
            'message' => "This key isn't approved for the /dev/null app. Sign in again in the app.",
            'code' => 'device_not_approved',
        ]);

    $this->assertGuest();
    Http::assertNothingSent();
});

it('rejects a key that is not approved as a device on the public endpoints too', function (Closure $uri): void {
    $keyPair = SshKeyPair::generate();
    $commit = Commit::factory()->create();

    $response = signedApiRequest($keyPair, 'GET', $uri($commit));

    $response->assertUnauthorized()
        ->assertJsonPath('code', 'device_not_approved')
        ->assertJsonMissingPath('data');

    $this->assertGuest();
})->with([
    'a profile' => fn (Commit $commit): string => route('api-user.show', $commit->user),
    'a list of commits' => fn (Commit $commit): string => route('api-user-commit.index', $commit->user),
    'a commit' => fn (Commit $commit): string => route('api-commit.show', $commit),
]);

it("signs in with an approved device whose key is on nobody's github account, without asking github", function (): void {
    expect(SshKey::query()->exists())->toBeFalse();

    signedApiRequest($this->keyPair, 'GET', route('api-current-user.show'))
        ->assertOk()
        ->assertJsonPath('data.id', $this->user->id);

    Http::assertNothingSent();
});

it('signs in as the user who approved the device, whoever has the key on github', function (): void {
    $other = User::factory()->create();

    SshKey::factory()->for($other)->withPublicKey($this->keyPair->publicKey)->create();

    signedApiRequest($this->keyPair, 'GET', route('api-current-user.show'))
        ->assertOk()
        ->assertJsonPath('data.id', $this->user->id);

    $this->assertAuthenticatedAs($this->user);
});

it('rejects a revoked device', function (): void {
    signedApiRequest($this->keyPair, 'GET', route('api-current-user.show'))->assertOk();

    $this->user->devices()->sole()->delete();
    Auth::forgetGuards();

    $response = signedApiRequest($this->keyPair, 'GET', route('api-current-user.show'));

    $response->assertUnauthorized()
        ->assertJsonPath('code', 'device_not_approved');
});

it('records when a device was last used, at most once a minute', function (): void {
    $device = $this->user->devices()->sole();

    expect($device->last_used_at)->toBeNull();

    signedApiRequest($this->keyPair, 'GET', route('api-current-user.show'))->assertOk();

    expect($device->refresh()->last_used_at?->toDateTimeString())->toBe(now()->toDateTimeString());

    $usedAt = now();
    $this->travel(59)->seconds();

    signedApiRequest($this->keyPair, 'GET', route('api-current-user.show'))->assertOk();

    expect($device->refresh()->last_used_at?->toDateTimeString())->toBe($usedAt->toDateTimeString());

    $this->travel(1)->seconds();

    signedApiRequest($this->keyPair, 'GET', route('api-current-user.show'))->assertOk();

    expect($device->refresh()->last_used_at?->toDateTimeString())->toBe(now()->toDateTimeString());
});

it('never answers a badly signed request to a public endpoint with the public view', function (Closure $uri, Closure $server, string $message): void {
    $uri = $uri(Commit::factory()->create());

    $response = $this->call('GET', $uri, server: $server($this->keyPair, $uri));

    $response->assertUnauthorized()
        ->assertJsonPath('message', $message)
        ->assertJsonPath('code', 'invalid_signature')
        ->assertJsonMissingPath('data');

    $this->assertGuest();
})->with([
    'a profile' => fn (Commit $commit): string => route('api-user.show', $commit->user),
    'a list of commits' => fn (Commit $commit): string => route('api-user-commit.index', $commit->user),
    'a commit' => fn (Commit $commit): string => route('api-commit.show', $commit),
    'a commit that does not exist' => fn (): string => 'api/v1/commits/01a13f6e-ebdd-72c7-bf9d-be2a9b2315a3',
])->with([
    'a wrong signature' => [
        fn (SshKeyPair $keyPair, string $uri): array => [...signApiRequest($keyPair, 'GET', $uri), 'HTTP_X_DEVNULL_NONCE' => str_repeat('0', 32)],
        'Invalid signature.',
    ],
    'an empty signature' => [
        fn (SshKeyPair $keyPair, string $uri): array => [...signApiRequest($keyPair, 'GET', $uri), 'HTTP_X_SSH_SIGNATURE' => ''],
        'Malformed SSH signature.',
    ],
    'an expired signature' => [
        fn (SshKeyPair $keyPair, string $uri): array => signApiRequest($keyPair, 'GET', $uri, timestamp: now()->getTimestamp() - 301),
        'Signature expired. Check that your clock is right.',
    ],
]);

it('answers a request without a signature with the public view, whoever is signed in on the web', function (Closure $uri): void {
    $commit = Commit::factory()->for($this->user)->pending()->create();
    $public = Commit::factory()->for($this->user)->create();

    $this->actingAs($this->user)
        ->getJson($uri($commit))
        ->assertJsonMissingPath('data.status')
        ->assertJsonMissing(['id' => $commit->id]);

    $this->assertAuthenticatedAs($this->user);
    $this->getJson(route('api-commit.show', $public))->assertOk()->assertJsonMissingPath('data.status');
})->with([
    'a profile' => fn (Commit $commit): string => route('api-user.show', $commit->user),
    'a list of commits' => fn (Commit $commit): string => route('api-user-commit.index', $commit->user),
    'a commit' => fn (Commit $commit): string => route('api-commit.show', $commit),
]);

it('treats any one of the signature headers as a signed request', function (string $header): void {
    $commit = Commit::factory()->create();

    $response = $this->getJson(route('api-commit.show', $commit), [$header => '1']);

    $response->assertUnauthorized();
})->with(['X-Devnull-Timestamp', 'X-Devnull-Nonce', 'X-SSH-Signature']);

it('limits each signed-in user to 120 requests a minute, wherever they come from', function (): void {
    $sign = fn (string $address): array => [...signApiRequest($this->keyPair, 'GET', route('api-current-user.show')), 'REMOTE_ADDR' => $address];

    foreach (range(1, 120) as $attempt) {
        $this->call('GET', route('api-current-user.show'), server: $sign('10.0.0.1'))->assertOk();
    }

    $this->call('GET', route('api-current-user.show'), server: $sign('10.0.0.1'))
        ->assertTooManyRequests()
        ->assertHeader('Retry-After', 60)
        ->assertJsonPath('message', 'Too Many Attempts.')
        ->assertJsonPath('code', 'rate_limited');

    $this->call('GET', route('api-current-user.show'), server: $sign('10.0.0.2'))->assertTooManyRequests();

    $this->call('GET', route('api-current-user.show'), server: [...signApiRequest(apiKeyPairFor(User::factory()->create()), 'GET', route('api-current-user.show')), 'REMOTE_ADDR' => '10.0.0.1'])
        ->assertOk();

    Auth::forgetGuards();

    $this->getJson(route('api-user.show', $this->user), ['REMOTE_ADDR' => '10.0.0.1'])
        ->assertOk()
        ->assertJsonMissingPath('data.lane');
});

it('rejects form fields and files, which the signature does not cover', function (array $parameters, array $files): void {
    // PHP hands a multipart body over as fields and files, and keeps no raw body for the signature to cover...
    $server = [...signApiRequest($this->keyPair, 'POST', route('api-commit.store')), 'CONTENT_TYPE' => 'multipart/form-data; boundary=devnull'];

    $this->call('POST', route('api-commit.store'), $parameters, files: $files, server: $server)
        ->assertUnauthorized()
        ->assertExactJson(['message' => 'Send the body of a signed request as JSON.', 'code' => 'invalid_signature']);

    expect(Commit::query()->exists())->toBeFalse();
})->with([
    'fields' => fn (): array => [['body' => 'Not what was signed'], []],
    'a file' => fn (): array => [[], ['image' => UploadedFile::fake()->image('dns.png')]],
]);

it('rejects a signed body sent as anything but json, which could read as other fields', function (): void {
    // Read as a form, the same signed bytes would make the body: Changed text"}...
    $body = '{"body":"Expected text &body=Changed text"}';
    $server = [...signApiRequest($this->keyPair, 'POST', route('api-commit.store'), $body), 'CONTENT_TYPE' => 'application/x-www-form-urlencoded'];

    $this->call('POST', route('api-commit.store'), server: $server, content: $body)
        ->assertUnauthorized()
        ->assertExactJson(['message' => 'Send the body of a signed request as JSON.', 'code' => 'invalid_signature']);

    expect(Commit::query()->exists())->toBeFalse();
});

it('rejects images that do not match the hashes that were signed', function (Closure $signed): void {
    $images = [UploadedFile::fake()->image('wide.png', 40, 10), UploadedFile::fake()->image('tall.png', 10, 40)];

    signedApiRequestWithImages($this->keyPair, 'POST', route('api-commit.store'), ['body' => 'It was DNS.'], $images, $signed($images))
        ->assertUnauthorized()
        ->assertExactJson(['message' => 'Invalid signature.', 'code' => 'invalid_signature']);

    expect(Commit::query()->exists())->toBeFalse();
})->with([
    'another image' => fn (array $images): array => [$images[0], UploadedFile::fake()->image('other.png', 10, 10)],
    'another order' => array_reverse(...),
    'one image less' => fn (array $images): array => [$images[0]],
]);

it('rejects images signed as a json body, without their own namespace', function (): void {
    signedApiRequestWithImages($this->keyPair, 'POST', route('api-commit.store'), ['body' => 'It was DNS.'], [UploadedFile::fake()->image('dns.png', 10, 10)], namespace: SignedRequests::Namespace)
        ->assertUnauthorized()
        ->assertExactJson(['message' => 'Invalid signature.', 'code' => 'invalid_signature']);

    expect(Commit::query()->exists())->toBeFalse();
});

it('rejects images sent any other way than beside a json payload', function (array $parameters, Closure $files): void {
    $image = UploadedFile::fake()->image('dns.png', 10, 10);
    $payload = '{"body":"It was DNS."}';
    $server = [
        ...signApiRequest($this->keyPair, 'POST', route('api-commit.store'), hash_file('sha256', $image->getPathname())."\n".$payload, namespace: SignedRequests::ImagesNamespace),
        'CONTENT_TYPE' => 'multipart/form-data; boundary=devnull',
    ];

    $this->call('POST', route('api-commit.store'), $parameters, files: $files($image, $payload), server: $server)
        ->assertUnauthorized()
        ->assertExactJson(['message' => 'Send the body of a signed request as JSON.', 'code' => 'invalid_signature']);

    expect(Commit::query()->exists())->toBeFalse();
})->with([
    'with a field beside the payload' => [['body' => 'Not what was signed'], fn (UploadedFile $image, string $payload): array => ['payload' => UploadedFile::fake()->createWithContent('payload', $payload), 'images' => [$image]]],
    'with the payload as a field' => [['payload' => '{"body":"It was DNS."}'], fn (UploadedFile $image, string $payload): array => ['images' => [$image]]],
    'with a payload that is not json' => [[], fn (UploadedFile $image, string $payload): array => ['payload' => UploadedFile::fake()->createWithContent('payload', 'body=It was DNS.'), 'images' => [$image]]],
    'with another file' => [[], fn (UploadedFile $image, string $payload): array => ['payload' => UploadedFile::fake()->createWithContent('payload', $payload), 'images' => [$image], 'avatar' => $image]],
    'with one image that is not in a list' => [[], fn (UploadedFile $image, string $payload): array => ['payload' => UploadedFile::fake()->createWithContent('payload', $payload), 'images' => $image]],
    'with nested images' => [[], fn (UploadedFile $image, string $payload): array => ['payload' => UploadedFile::fake()->createWithContent('payload', $payload), 'images' => [[$image]]]],
]);

it('rejects a voice note that does not match the hash that was signed', function (): void {
    $nuno = User::factory()->withRemotes()->create();
    $this->user->update(['remotes_enabled' => true]);
    $nuno->following()->attach($this->user);
    $this->user->following()->attach($nuno);

    signedApiRequestWithVoice($this->keyPair, 'POST', route('api-conversation-message.store', $nuno), ['client_id' => fake()->uuid(), 'voice_waveform' => voiceNoteWaveform()], voiceNoteUpload(), voiceNoteUpload(voiceNoteWav()))
        ->assertUnauthorized()
        ->assertExactJson(['message' => 'Invalid signature.', 'code' => 'invalid_signature']);
});

it('rejects a voice note sent any other way than alone beside a json payload', function (Closure $files): void {
    $voice = voiceNoteUpload();
    $payload = '{"body":"It was DNS."}';
    $server = [
        ...signApiRequest($this->keyPair, 'POST', route('api-commit.store'), hash_file('sha256', $voice->getPathname())."\n".$payload, namespace: SignedRequests::ImagesNamespace),
        'CONTENT_TYPE' => 'multipart/form-data; boundary=devnull',
    ];

    $this->call('POST', route('api-commit.store'), files: $files($voice, $payload), server: $server)
        ->assertUnauthorized()
        ->assertExactJson(['message' => 'Send the body of a signed request as JSON.', 'code' => 'invalid_signature']);
})->with([
    'with images' => fn (UploadedFile $voice, string $payload): array => ['payload' => UploadedFile::fake()->createWithContent('payload', $payload), 'voice' => $voice, 'images' => [UploadedFile::fake()->image('dns.png', 10, 10)]],
    'in a list' => fn (UploadedFile $voice, string $payload): array => ['payload' => UploadedFile::fake()->createWithContent('payload', $payload), 'voice' => [$voice]],
]);

it('says when a voice note did not upload, which is no fault of the signature', function (): void {
    $nuno = User::factory()->withRemotes()->create();
    $this->user->update(['remotes_enabled' => true]);
    $voice = voiceNoteUpload();

    signedApiRequestWithVoice($this->keyPair, 'POST', route('api-conversation-message.store', $nuno), ['client_id' => fake()->uuid(), 'voice_waveform' => voiceNoteWaveform()], new UploadedFile($voice->getPathname(), 'voice-note', 'audio/webm', UPLOAD_ERR_INI_SIZE, test: true), $voice)
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['voice' => 'The voice note failed to upload.']);
});

it('says when an image did not upload, which is no fault of the signature', function (): void {
    $image = UploadedFile::fake()->image('dns.png', 10, 10);

    signedApiRequestWithImages($this->keyPair, 'POST', route('api-commit.store'), ['body' => 'It was DNS.'], [new UploadedFile($image->getPathname(), 'dns.png', 'image/png', UPLOAD_ERR_INI_SIZE, test: true)], [$image])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['images' => 'The image failed to upload.']);

    expect(Commit::query()->exists())->toBeFalse();
});

it('limits each address to 60 failed signatures a minute, before checking any more', function (): void {
    $stranger = SshKeyPair::generate();
    $sign = fn (SshKeyPair $keyPair, string $address): array => [...signApiRequest($keyPair, 'GET', route('api-current-user.show')), 'REMOTE_ADDR' => $address];

    foreach (range(1, 30) as $attempt) {
        $this->call('GET', route('api-current-user.show'), server: $sign($stranger, '10.0.0.1'))->assertUnauthorized();
        $this->getJson(route('api-current-user.show'), ['REMOTE_ADDR' => '10.0.0.1', 'HTTP_X_SSH_SIGNATURE' => 'forged'])->assertUnauthorized();
    }

    $this->call('GET', route('api-current-user.show'), server: $sign($this->keyPair, '10.0.0.1'))
        ->assertTooManyRequests()
        ->assertHeader('Retry-After', 60)
        ->assertExactJson(['message' => 'Too Many Attempts.', 'code' => 'rate_limited']);

    $this->call('GET', route('api-current-user.show'), server: $sign($this->keyPair, '10.0.0.2'))->assertOk();
});

it('counts only failed signatures against an address', function (): void {
    foreach (range(1, 61) as $attempt) {
        $this->call('GET', route('api-current-user.show'), server: [...signApiRequest($this->keyPair, 'GET', route('api-current-user.show')), 'REMOTE_ADDR' => '10.0.0.1'])->assertOk();
    }
});

it("leaves the public api's 429 without a code", function (): void {
    $commit = Commit::factory()->create();

    foreach (range(1, 60) as $attempt) {
        $this->getJson(route('api-commit.show', $commit))->assertOk();
    }

    $this->getJson(route('api-commit.show', $commit))
        ->assertTooManyRequests()
        ->assertJsonPath('message', 'Too Many Attempts.')
        ->assertJsonMissingPath('code');
});

it('answers unknown api routes in json', function (): void {
    $response = $this->get('api/v1/unknown');

    $response->assertNotFound()
        ->assertHeader('Content-Type', 'application/json');
});
