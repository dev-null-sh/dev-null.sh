<?php

declare(strict_types=1);

use App\Dto\DevicePush;
use App\Models\Device;
use App\Services\DevicePushProviders\FcmDevicePushProvider;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
    openssl_pkey_export($key, $privateKey);
    $this->publicKey = openssl_pkey_get_details($key)['key'];

    config(['services.fcm.credentials' => json_encode([
        'type' => 'service_account',
        'project_id' => 'devnull-test',
        'client_email' => 'push@devnull-test.iam.gserviceaccount.com',
        'private_key' => $privateKey,
        'token_uri' => 'https://oauth2.googleapis.com/token',
    ])]);

    $this->device = Device::factory()->withPushToken('fcm-phone')->create();
    $this->push = new DevicePush('Alice', 'Sent you a message', ['type' => 'message', 'conversation_id' => 'c1'], 'c1');
});

function fakeFcm(int $status = 200, array $body = ['name' => 'projects/devnull-test/messages/1']): void
{
    Http::fake([
        'https://oauth2.googleapis.com/token' => Http::response(['access_token' => 'access-1', 'expires_in' => 3599]),
        'https://fcm.googleapis.com/*' => Http::response($body, $status),
    ]);
}

it('sends who did something to the phone, signed with the service account', function (): void {
    fakeFcm();

    expect(new FcmDevicePushProvider()->send($this->device, $this->push))->toBeTrue();

    Http::assertSent(function (Request $request): bool {
        if ($request->url() !== 'https://oauth2.googleapis.com/token') {
            return true;
        }

        $claims = JWT::decode($request['assertion'], new Key($this->publicKey, 'RS256'));

        return $request['grant_type'] === 'urn:ietf:params:oauth:grant-type:jwt-bearer'
            && $claims->iss === 'push@devnull-test.iam.gserviceaccount.com'
            && $claims->scope === 'https://www.googleapis.com/auth/firebase.messaging'
            && $claims->aud === 'https://oauth2.googleapis.com/token';
    });
    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://fcm.googleapis.com/v1/projects/devnull-test/messages:send'
        && $request->header('Authorization') === ['Bearer access-1']
        && json_decode($request->body(), true)['message'] === [
            'token' => 'fcm-phone',
            'notification' => ['title' => 'Alice', 'body' => 'Sent you a message'],
            'data' => ['type' => 'message', 'conversation_id' => 'c1', 'url' => 'devnull://push?type=message&conversation_id=c1'],
            'android' => ['priority' => 'high'],
            'apns' => ['payload' => ['aps' => ['sound' => 'default', 'thread-id' => 'c1']]],
        ]);
});

it('leaves the thread out of a push that has no group', function (): void {
    fakeFcm();

    new FcmDevicePushProvider()->send($this->device, new DevicePush('Alice', 'Followed you', []));

    Http::assertSent(fn (Request $request): bool => str_starts_with($request->url(), 'https://fcm.googleapis.com/')
        && json_decode($request->body(), true)['message']['apns'] === ['payload' => ['aps' => ['sound' => 'default']]]);
});

it('keeps the access token, encrypted, until shortly before it expires', function (): void {
    fakeFcm();
    $provider = new FcmDevicePushProvider();

    $provider->send($this->device, $this->push);
    expect(Cache::get('fcm-access-token'))->not->toContain('access-1')
        ->and(Crypt::decryptString(Cache::get('fcm-access-token')))->toBe('access-1');
    $this->travel(3500)->seconds();
    $provider->send($this->device, $this->push);
    Http::assertSentCount(3);

    $this->travel(40)->seconds();
    $provider->send($this->device, $this->push);
    Http::assertSentCount(5);
});

it('forgets a token the phone no longer has', function (int $status, array $body): void {
    fakeFcm($status, $body);

    expect(new FcmDevicePushProvider()->send($this->device, $this->push))->toBeTrue()
        ->and($this->device->fresh())
        ->push_token_hash->toBeNull()
        ->push_token->toBeNull()
        ->push_platform->toBeNull();
})->with([
    'not found' => [404, ['error' => ['status' => 'NOT_FOUND', 'details' => [['@type' => 'type.googleapis.com/google.firebase.fcm.v1.FcmError', 'errorCode' => 'UNREGISTERED']]]]],
    'unregistered' => [400, ['error' => ['status' => 'INVALID_ARGUMENT', 'details' => [['@type' => 'type.googleapis.com/google.firebase.fcm.v1.FcmError', 'errorCode' => 'UNREGISTERED']]]]],
]);

it('keeps a token the phone registered while the push was on its way', function (): void {
    fakeFcm(404, ['error' => ['details' => [['errorCode' => 'UNREGISTERED']]]]);
    Device::query()->whereKey($this->device->id)->update(['push_token_hash' => hash('sha256', 'fcm-new')]);

    new FcmDevicePushProvider()->send($this->device, $this->push);

    expect($this->device->fresh()->push_token_hash)->toBe(hash('sha256', 'fcm-new'));
});

it('asks to retry temporary failures, and keeps the token', function (int $status, bool $sent): void {
    fakeFcm($status, ['error' => ['status' => 'X']]);

    expect(new FcmDevicePushProvider()->send($this->device, $this->push))->toBe($sent)
        ->and($this->device->fresh()->push_token)->toBe('fcm-phone');
})->with([
    'a bad request' => [400, true],
    'a wrong project' => [404, true],
    'forbidden' => [403, true],
    'rate limited' => [429, false],
    'unavailable' => [503, false],
]);

it('fetches a new access token after one is refused', function (): void {
    fakeFcm(401);
    $provider = new FcmDevicePushProvider();

    expect($provider->send($this->device, $this->push))->toBeFalse();
    $provider->send($this->device, $this->push);

    Http::assertSentCount(4);
});

it('asks to retry when Firebase cannot be reached', function (): void {
    Http::fake(fn () => throw new ConnectionException('timed out'));

    expect(new FcmDevicePushProvider()->send($this->device, $this->push))->toBeFalse();
});

it('fails loudly when the service account is refused', function (): void {
    Http::fake(['https://oauth2.googleapis.com/token' => Http::response(['error' => 'invalid_grant'], 400)]);

    new FcmDevicePushProvider()->send($this->device, $this->push);
})->throws(RequestException::class);

it('asks to retry when Google cannot hand out an access token right now', function (int $status): void {
    Http::fake(['https://oauth2.googleapis.com/token' => Http::response([], $status)]);

    expect(new FcmDevicePushProvider()->send($this->device, $this->push))->toBeFalse();

    Http::assertSentCount(1);
})->with([429, 503]);

it('pushes nothing without a service account', function (?string $credentials): void {
    config(['services.fcm.credentials' => $credentials]);
    Http::fake();

    expect(new FcmDevicePushProvider()->send($this->device, $this->push))->toBeTrue();

    Http::assertNothingSent();
})->with([null, '']);
