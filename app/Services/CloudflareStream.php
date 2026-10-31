<?php

declare(strict_types=1);

namespace App\Services;

use App\Dto\LiveInput;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

/**
 * Streams that go live on /dev/null itself, through Cloudflare Stream. Each user has one live input, which keeps its
 * stream key between streams, so streaming software only needs setting up once.
 */
final readonly class CloudflareStream
{
    /**
     * Recordings are what play while a stream is on, and Cloudflare keeps them for no less than 30 days.
     */
    private const int RecordingDays = 30;

    public function configured(): bool
    {
        return filled(config('services.cloudflare_stream.account_id'))
            && filled(config('services.cloudflare_stream.api_token'))
            && filled(config('services.cloudflare_stream.customer_subdomain'));
    }

    public function createLiveInput(string $name): string
    {
        $uid = $this->request()
            ->post('live_inputs', [
                'meta' => ['name' => $name],
                'recording' => ['mode' => 'automatic'],
                'deleteRecordingAfterDays' => self::RecordingDays,
            ])
            ->throw()
            ->json('result.uid');

        assert(is_string($uid));

        return $uid;
    }

    /**
     * Null when the live input no longer exists on Cloudflare.
     */
    public function liveInput(string $uid): ?LiveInput
    {
        $response = $this->request()->get("live_inputs/{$uid}");

        if ($response->notFound()) {
            return null;
        }

        $rtmps = $response->throw()->json('result.rtmps');

        assert(is_array($rtmps) && is_string($rtmps['url']) && is_string($rtmps['streamKey']));

        return new LiveInput($rtmps['url'], $rtmps['streamKey']);
    }

    public function deleteLiveInput(string $uid): void
    {
        $response = $this->request()->delete("live_inputs/{$uid}");

        if (! $response->notFound()) {
            $response->throw();
        }
    }

    /**
     * Played by its live input, the player shows whatever is on, and that the stream has not started while nothing is.
     */
    public function playerUrl(string $uid): string
    {
        $subdomain = config('services.cloudflare_stream.customer_subdomain');

        assert(is_string($subdomain));

        return "https://{$subdomain}/{$uid}/iframe";
    }

    private function request(): PendingRequest
    {
        $accountId = config('services.cloudflare_stream.account_id');
        $token = config('services.cloudflare_stream.api_token');

        assert(is_string($accountId) && is_string($token));

        return Http::baseUrl("https://api.cloudflare.com/client/v4/accounts/{$accountId}/stream")
            ->withToken($token)
            ->acceptJson()
            ->connectTimeout(3)
            ->timeout(5);
    }
}
