<?php

declare(strict_types=1);

namespace App\Services\DevicePushProviders;

use App\Contracts\Services\DevicePushProvider;
use App\Dto\DevicePush;
use App\Dto\DevicePushResult;
use App\Dto\FcmCredentials;
use App\Exceptions\DevicePushCredentialsRejected;
use App\Models\Device;
use DomainException;
use Firebase\JWT\JWT;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\QueryException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Pushes through Firebase Cloud Messaging's HTTP v1 API, which reaches both platforms: Firebase hands iPhone pushes on
 * to APNs with the key uploaded in its console. The service account's key comes from the environment, never the
 * repository; without it nothing is pushed. It buys an access token, kept encrypted until shortly before it expires.
 */
final readonly class FcmDevicePushProvider implements DevicePushProvider
{
    /**
     * Google asks for at least a minute after a rate limit before the next try.
     */
    private const int RATE_LIMIT_SECONDS = 60;

    public function send(Device $device, DevicePush $push): DevicePushResult
    {
        $credentials = FcmCredentials::fromConfig();

        if (! $credentials instanceof FcmCredentials) {
            return DevicePushResult::settled();
        }

        try {
            $accessToken = $this->accessToken($credentials);

            if ($accessToken instanceof DevicePushResult) {
                return $accessToken;
            }

            return DB::transaction(function () use ($device, $push, $credentials, $accessToken): DevicePushResult {
                if (! DB::scalar('select pg_try_advisory_xact_lock(hashtextextended(?, 0))', ['device-push:'.$device->id])) {
                    return DevicePushResult::retry();
                }

                // The unique token hash makes registration changes conflict, but last_used_at can still be written.
                $currentDevice = Device::query()->whereKey($device->id)
                    ->where('user_id', $device->user_id)->where('push_token_hash', $device->push_token_hash)
                    ->lock('for key share nowait')->first();

                if (! $currentDevice instanceof Device || $currentDevice->push_token === null) {
                    return DevicePushResult::settled();
                }

                $response = Http::withToken($accessToken)->timeout(10)
                    ->post("https://fcm.googleapis.com/v1/projects/{$credentials->projectId}/messages:send", ['message' => [
                        'token' => $currentDevice->push_token,
                        'notification' => ['title' => $push->title, 'body' => $push->body],
                        'data' => $push->data,
                        'android' => ['priority' => 'high', 'ttl' => $push->ttl.'s'],
                        'apns' => ['headers' => ['apns-expiration' => (string) now()->addSeconds($push->ttl)->getTimestamp()], 'payload' => ['aps' => ['sound' => 'default', ...$push->group === null ? [] : ['thread-id' => $push->group]]]],
                    ]]);

                if (in_array('UNREGISTERED', $response->collect('error.details')->pluck('errorCode')->all(), true)) {
                    // Only the token that was sent, in case the phone registered a new one meanwhile.
                    Device::query()->whereKey($currentDevice->id)->where('push_token_hash', $currentDevice->push_token_hash)
                        ->update(['push_token_hash' => null, 'push_token' => null, 'push_platform' => null]);

                    return DevicePushResult::settled();
                }

                if (in_array('THIRD_PARTY_AUTH_ERROR', $response->collect('error.details')->pluck('errorCode')->all(), true)) {
                    $this->reportFailure(new RuntimeException('FCM APNs credentials were rejected (THIRD_PARTY_AUTH_ERROR).'));

                    return DevicePushResult::settled();
                }

                if ($response->unauthorized()) {
                    $cacheKey = $this->accessTokenKey($credentials);
                    Cache::lock($cacheKey.':lock', 30)->get(function () use ($cacheKey, $accessToken): void {
                        $cached = Cache::get($cacheKey);

                        if (is_string($cached) && Crypt::decryptString($cached) === $accessToken) {
                            Cache::forget($cacheKey);
                        }
                    });
                }

                if (! $response->serverError() && ! $response->unauthorized() && $response->status() !== 429) {
                    if ($response->clientError()) {
                        $this->reportFailure(new RuntimeException("FCM send request failed (HTTP {$response->status()})."));
                    }

                    return DevicePushResult::settled();
                }

                return DevicePushResult::retry($this->retryAfterSeconds($response));
            });
        } catch (QueryException $exception) {
            throw_if($exception->getCode() !== '55P03', $exception);

            return DevicePushResult::retry();
        } catch (ConnectionException) {
            return DevicePushResult::retry();
        } catch (DecryptException) {
            $this->reportFailure(new RuntimeException('FCM device token could not be decrypted.'));

            return DevicePushResult::settled();
        }
    }

    private function reportFailure(RuntimeException $exception): void
    {
        report_if(Cache::add('fcm-error:'.hash('sha256', $exception->getMessage()), true, now()->addMinutes(5)), $exception);
    }

    private function accessTokenKey(FcmCredentials $credentials): string
    {
        return 'fcm-access-token:'.hash('sha256', implode("\0", [
            $credentials->projectId, $credentials->clientEmail, $credentials->privateKey, $credentials->tokenUri,
        ]));
    }

    /**
     * What Google asked us to wait before the next try, in seconds. A rate limit waits a minute even without a header.
     */
    private function retryAfterSeconds(Response $response): ?int
    {
        $header = mb_trim($response->header('Retry-After'));
        $seconds = ctype_digit($header) ? (int) $header : $this->dateSeconds($header);

        if ($response->status() === 429) {
            return max($seconds ?? 0, self::RATE_LIMIT_SECONDS);
        }

        return $seconds;
    }

    private function dateSeconds(string $header): ?int
    {
        $timestamp = strtotime($header);

        return $timestamp === false ? null : max(0, $timestamp - now()->getTimestamp());
    }

    /**
     * The access token, or a retry result when Google temporarily cannot hand one out.
     */
    private function accessToken(FcmCredentials $credentials): string|DevicePushResult
    {
        $cacheKey = $this->accessTokenKey($credentials);
        $lock = Cache::lock($cacheKey.':lock', 30);

        if (! $lock->get()) {
            return DevicePushResult::retry();
        }

        try {
            $cached = Cache::get($cacheKey);

            if (is_int($cached)) {
                return DevicePushResult::retry(max(0, $cached - now()->getTimestamp()));
            }

            if (is_string($cached)) {
                try {
                    return Crypt::decryptString($cached);
                } catch (DecryptException) {
                    // Cached under an app key that has since been rotated.
                    Cache::forget($cacheKey);
                }
            }

            try {
                $assertion = JWT::encode([
                    'iss' => $credentials->clientEmail,
                    'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
                    'aud' => $credentials->tokenUri,
                    'iat' => now()->getTimestamp(),
                    'exp' => now()->addHour()->getTimestamp(),
                ], $credentials->privateKey, 'RS256');
            } catch (DomainException) {
                $exception = new DevicePushCredentialsRejected('FCM OAuth credentials could not sign a request.');
                $this->reportFailure($exception);

                throw $exception;
            }

            $response = Http::asForm()->timeout(10)->post($credentials->tokenUri, [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $assertion,
            ]);

            if ($response->status() === 429) {
                $seconds = $this->retryAfterSeconds($response) ?? self::RATE_LIMIT_SECONDS;
                Cache::put($cacheKey, now()->getTimestamp() + $seconds, $seconds);

                return DevicePushResult::retry($seconds);
            }

            if ($response->serverError()) {
                return DevicePushResult::retry($this->retryAfterSeconds($response));
            }

            if (! $response->successful()) {
                $exception = new DevicePushCredentialsRejected("FCM OAuth request failed (HTTP {$response->status()}).");
                $this->reportFailure($exception);

                throw $exception;
            }

            $token = $response->json('access_token');
            assert(is_string($token));

            Cache::put($cacheKey, Crypt::encryptString($token), now()->addSeconds($response->fluent()->integer('expires_in', 3600) - 60));

            return $token;
        } finally {
            $lock->release();
        }
    }
}
