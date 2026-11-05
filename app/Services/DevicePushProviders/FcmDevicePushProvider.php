<?php

declare(strict_types=1);

namespace App\Services\DevicePushProviders;

use App\Contracts\Services\DevicePushProvider;
use App\Dto\DevicePush;
use App\Models\Device;
use Firebase\JWT\JWT;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;

/**
 * Pushes through Firebase Cloud Messaging's HTTP v1 API, which reaches both platforms: Firebase hands iPhone pushes on
 * to APNs with the key uploaded in its console. The service account's JSON key comes from the environment, never the
 * repository; without it nothing is pushed. It buys an access token, kept encrypted until shortly before it expires.
 */
final readonly class FcmDevicePushProvider implements DevicePushProvider
{
    private const string AccessTokenKey = 'fcm-access-token';

    public function send(Device $device, DevicePush $push): bool
    {
        $json = config('services.fcm.credentials');

        if (! is_string($json) || $json === '') {
            return true;
        }

        /** @var array{project_id: string, client_email: string, private_key: string, token_uri: string} $credentials */
        $credentials = json_decode($json, true, flags: JSON_THROW_ON_ERROR);

        try {
            $accessToken = $this->accessToken($credentials);

            if ($accessToken === null) {
                return false;
            }

            $response = Http::withToken($accessToken)->timeout(10)
                ->post("https://fcm.googleapis.com/v1/projects/{$credentials['project_id']}/messages:send", ['message' => [
                    'token' => $device->push_token,
                    'notification' => ['title' => $push->title, 'body' => $push->body],
                    'data' => $push->data,
                    'android' => ['priority' => 'high'],
                    'apns' => ['payload' => ['aps' => ['sound' => 'default', ...$push->group === null ? [] : ['thread-id' => $push->group]]]],
                ]]);
        } catch (ConnectionException) {
            return false;
        }

        if (in_array('UNREGISTERED', $response->collect('error.details')->pluck('errorCode')->all(), true)) {
            // Only the token that was sent, in case the phone registered a new one meanwhile.
            Device::query()->whereKey($device->id)->where('push_token_hash', $device->push_token_hash)
                ->update(['push_token_hash' => null, 'push_token' => null, 'push_platform' => null]);
        }

        if ($response->unauthorized()) {
            Cache::forget(self::AccessTokenKey);
        }

        return ! $response->serverError() && ! $response->unauthorized() && $response->status() !== 429;
    }

    /**
     * Null when Google can't hand one out right now. Credentials it refuses throw, since retrying won't help.
     *
     * @param  array{project_id: string, client_email: string, private_key: string, token_uri: string}  $credentials
     */
    private function accessToken(array $credentials): ?string
    {
        $cached = Cache::get(self::AccessTokenKey);

        if (is_string($cached)) {
            return Crypt::decryptString($cached);
        }

        $assertion = JWT::encode([
            'iss' => $credentials['client_email'],
            'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
            'aud' => $credentials['token_uri'],
            'iat' => now()->getTimestamp(),
            'exp' => now()->addHour()->getTimestamp(),
        ], $credentials['private_key'], 'RS256');

        $response = Http::asForm()->timeout(10)->post($credentials['token_uri'], [
            'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
            'assertion' => $assertion,
        ]);

        if ($response->serverError() || $response->status() === 429) {
            return null;
        }

        $response->throw();

        $token = $response->json('access_token');
        assert(is_string($token));

        Cache::put(self::AccessTokenKey, Crypt::encryptString($token), now()->addSeconds($response->fluent()->integer('expires_in', 3600) - 60));

        return $token;
    }
}
