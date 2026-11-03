# App/Services guidelines

- Every third-party service the app reaches over the network (a relay, an AI provider, a mailer, a payment or streaming platform) sits behind a contract, so swapping vendor changes one class.
- The contract is an interface in `app/Contracts/Services`, named after the role it plays, never the vendor: `TurnProvider`, `AnalyticsProvider`.
- Each implementation lives in `app/Services/{Role}s/` and is named vendor + role: `app/Services/TurnProviders/CloudflareTurnProvider.php`, `app/Services/AnalyticsProviders/FathomAnalyticsProvider.php`.
- Add the contract to both arrays in `AppServiceProvider`: `PRODUCTION_SERVICES` with the real implementation, and `LOCAL_SERVICES` with a local one that needs no account, credentials or network: `Local{Role}` when it still does the job another way (`LocalTurnProvider` connects browsers directly), `Null{Role}` when it answers nothing (`NullModerationProvider` flags nothing). The local and testing environments bind only `LOCAL_SERVICES`, every other environment only `PRODUCTION_SERVICES`, so a contract missing from either has no binding there. Tests of the real implementation create it themselves and fake its HTTP calls.
- Everything else depends on the contract, never on the implementation: inject it into constructors, controller methods and form request `authorize()`, or `resolve(TurnProvider::class)` where nothing can be injected.
- The contract speaks the app's language, never the vendor's: methods and shapes the feature needs (`iceServers()`, `isRelayOnly()`), with a `@phpstan-type` for any shape it returns. Vendor URLs, payloads and credentials stay inside the implementation.
- An implementation reads its credentials from `config/services.php`, only where it uses them, and asserts their type there: `$apiToken = config('services.cloudflare_turn.api_token'); assert(is_string($apiToken));`. Production always has them, and local and testing bind the local implementation, so nothing checks whether a service is configured: no `isAvailable()`, `isConfigured()` or `isAuthenticated()`, and no reading a service's config outside its implementation.
- When the app is built around one vendor and could not swap it (GitHub: accounts, SSH keys and organizations all come from it), the contract is named after the vendor with the `Provider` suffix (`GitHubProvider`), the real implementation is `Http{Vendor}Provider` and the local one `Null{Vendor}Provider`, which knows nothing and returns empty answers. Its methods may use the vendor's vocabulary, but still never expose a raw request.
- Only the real implementation's own unit test fakes HTTP. Every other test runs against the local implementation, and mocks the contract when it needs specific answers. Never test which implementation an environment binds (no `detectEnvironment()` and re-registering `AppServiceProvider`): the production branch of the bindings is excluded from coverage instead.
- Classes in `app/Services` that only parse or compute (`Urls`, `Mentions`) talk to nobody and stay plain classes without a contract.

@boostsnippet('Example service contract and implementation', 'php')
<?php

declare(strict_types=1);

namespace App\Contracts\Services;

interface TurnProvider
{
    /**
     * @return list<array{urls: list<string>, username: string, credential: string}>
     */
    public function iceServers(): array;
}

namespace App\Services\TurnProviders;

use App\Contracts\Services\TurnProvider;

final readonly class CloudflareTurnProvider implements TurnProvider
{
    // ...
}
@endboostsnippet
