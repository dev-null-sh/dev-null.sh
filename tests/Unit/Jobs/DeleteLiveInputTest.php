<?php

declare(strict_types=1);

use App\Jobs\DeleteLiveInput;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

it('deletes the live input on Cloudflare', function (): void {
    config(['services.cloudflare_stream' => ['account_id' => 'account', 'api_token' => 'token', 'customer_subdomain' => 'customer-abc.cloudflarestream.com']]);
    Http::fake(['api.cloudflare.com/*' => Http::response()]);

    dispatch_sync(new DeleteLiveInput('input-1'));

    Http::assertSent(fn (Request $request): bool => $request->method() === 'DELETE'
        && $request->url() === 'https://api.cloudflare.com/client/v4/accounts/account/stream/live_inputs/input-1');
});
