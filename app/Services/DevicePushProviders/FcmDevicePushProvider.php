<?php

declare(strict_types=1);

namespace App\Services\DevicePushProviders;

use App\Contracts\Services\DevicePushProvider;
use App\Dto\DevicePush;
use App\Dto\FcmCredentials;
use App\Models\Device;
use Firebase\JWT\JWT;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;

/**
 * Pushes through Firebase Cloud Messaging's HTTP v1 API, which reaches both platforms: Firebase hands iPhone pushes on
 * to APNs with the key uploaded in its console. The service account's key comes from the environment, never the
 * repository; without it nothing is pushed. It buys an access token, kept encrypted until shortly before it expires.
 */
final readonly class FcmDevicePushProvider implements DevicePushProvider
{
    private const string AccessTokenKey = 'fcm-access-token';

    public function send(Device $device, DevicePush $push): bool
    {
        $credentials = FcmCredentials::fromConfig();

        if (! $credentials instanceof FcmCredentials) {
            return true;
        }

        try {
            $accessToken = $this->accessToken($credentials);

            if ($accessToken === null) {
                return false;
            }

            $response = Http::withToken($accessToken)->timeout(10)
                ->post("https://fcm.googleapis.com/v1/projects/{$credentials->projectId}/messages:send", ['message' => [
                    'token' => $device->push_token,
                    'notification' => ['title' => $push->title, 'body' => $push->body],
                    'data' => $push->data,
                    'android' => ['priority' => 'high', 'ttl' => $push->ttl.'s'],
                    'apns' => ['headers' => ['apns-expiration' => (string) now()->addSeconds($push->ttl)->getTimestamp()], 'payload' => ['aps' => ['sound' => 'default', ...$push->group === null ? [] : ['thread-id' => $push->group]]]],
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
     */
    private function accessToken(FcmCredentials $credentials): ?string
    {
        $cached = Cache::get(self::AccessTokenKey);

        if (is_string($cached)) {
            try {
                return Crypt::decryptString($cached);
            } catch (DecryptException) {
                // Cached under an app key that has since been rotated.
                Cache::forget(self::AccessTokenKey);
            }
        }

        $assertion = JWT::encode([
            'iss' => $credentials->clientEmail,
            'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
            'aud' => $credentials->tokenUri,
            'iat' => now()->getTimestamp(),
            'exp' => now()->addHour()->getTimestamp(),
        ], $credentials->privateKey, 'RS256');

        $response = Http::asForm()->timeout(10)->post($credentials->tokenUri, [
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
