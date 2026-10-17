<?php

declare(strict_types=1);

use Dotenv\Repository\Adapter\EnvConstAdapter;
use Dotenv\Repository\Adapter\PutenvAdapter;
use Dotenv\Repository\Adapter\ServerConstAdapter;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Route;

it('protects session and xsrf cookies according to the environment and request', function (?string $environment, ?string $override, string $scheme, bool $secure): void {
    $variables = ['APP_ENV' => $environment, 'SESSION_SECURE_COOKIE' => $override];
    $adapters = [EnvConstAdapter::create()->get(), ServerConstAdapter::create()->get(), PutenvAdapter::create()->get()];
    $original = [];

    foreach ($adapters as $index => $adapter) {
        foreach ($variables as $name => $value) {
            $original[$index][$name] = $adapter->read($name)->getOrElse(null);

            if ($value === null) {
                $adapter->delete($name);
            } else {
                $adapter->write($name, $value);
            }
        }
    }

    try {
        config(['session' => array_replace(require config_path('session.php'), ['driver' => 'array'])]);
        Route::middleware('web')->get('/session-cookie-test', fn (): Response => response('ok'));

        $response = $this->get($scheme.'://localhost/session-cookie-test');

        $response->assertOk()
            ->assertCookie(config('session.cookie'))
            ->assertCookie('XSRF-TOKEN');

        expect($response->getCookie(config('session.cookie'), decrypt: false))->isSecure()->toBe($secure);
        expect($response->getCookie('XSRF-TOKEN', decrypt: false))->isSecure()->toBe($secure);
    } finally {
        foreach ($adapters as $index => $adapter) {
            foreach ($original[$index] as $name => $value) {
                if ($value === null) {
                    $adapter->delete($name);
                } else {
                    $adapter->write($name, $value);
                }
            }
        }
    }
})->with([
    'production over HTTP' => ['production', null, 'http', true],
    'production over HTTPS' => ['production', null, 'https', true],
    'implicit production over HTTP' => [null, null, 'http', true],
    'implicit production over HTTPS' => [null, null, 'https', true],
    'staging over HTTPS' => ['staging', null, 'https', true],
    'staging over HTTP' => ['staging', null, 'http', false],
    'local over HTTP' => ['local', null, 'http', false],
    'explicitly enabled over HTTP' => ['staging', 'true', 'http', true],
    'explicitly disabled in production' => ['production', 'false', 'https', false],
    'explicit automatic HTTPS detection' => ['production', 'null', 'https', true],
    'explicit automatic HTTP detection' => ['production', 'null', 'http', false],
]);
