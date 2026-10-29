# App/Services guidelines

- Every third-party service the app reaches over the network (a relay, an AI provider, a mailer, a payment or streaming platform) sits behind a contract, so swapping vendor changes one class.
- The contract is an interface in `app/Contracts/Services`, named after the role it plays, never the vendor: `TurnProvider`, `DnsResolver`.
- Each implementation lives in `app/Services/{Role}s/` and is named vendor + role: `app/Services/TurnProviders/CloudflareTurnProvider.php`, `app/Services/DnsResolvers/SystemDnsResolver.php`.
- Add the contract to both arrays in `AppServiceProvider`: `PRODUCTION_SERVICES` with the real implementation, and `LOCAL_SERVICES` with a `Local{Role}` one (`LocalTurnProvider`) that needs no account, credentials or network. The local environment binds only `LOCAL_SERVICES`, every other environment only `PRODUCTION_SERVICES`, so a contract missing from either has no binding there.
- Everything else depends on the contract, never on the implementation: inject it into constructors, controller methods and form request `authorize()`, or `resolve(TurnProvider::class)` where nothing can be injected.
- The contract speaks the app's language, never the vendor's: methods and shapes the feature needs (`iceServers()`, `isAvailable()`), with a `@phpstan-type` for any shape it returns. Vendor URLs, payloads and credentials stay inside the implementation.
- An implementation reads its credentials from `config/services.php`. When the feature has to keep working without them, locally for example, the contract says whether the service is usable (`isAvailable()`), and form requests and shared props check that instead of reading config.
- Classes in `app/Services` that only parse or compute (`Urls`, `Mentions`) talk to nobody and stay plain classes without a contract.
- Older services such as `OpenAI` and `GitHub` predate this rule: put them behind a contract when you change them.

@boostsnippet('Example service contract and implementation', 'php')
<?php

declare(strict_types=1);

namespace App\Contracts\Services;

interface TurnProvider
{
    public function isAvailable(): bool;

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
