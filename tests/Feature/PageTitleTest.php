<?php

declare(strict_types=1);

use Dotenv\Repository\Adapter\EnvConstAdapter;
use Dotenv\Repository\Adapter\PutenvAdapter;
use Dotenv\Repository\Adapter\ServerConstAdapter;

it('titles pages /dev/null whatever APP_NAME says', function (): void {
    $adapters = [EnvConstAdapter::create()->get(), ServerConstAdapter::create()->get(), PutenvAdapter::create()->get()];
    $original = [];

    foreach ($adapters as $index => $adapter) {
        $original[$index] = $adapter->read('APP_NAME')->getOrElse(null);

        $adapter->write('APP_NAME', 'devnull');
    }

    try {
        config(['app' => require config_path('app.php')]);

        $this->get(route('changelog.index'))
            ->assertOk()
            ->assertSee('<title>/dev/null</title>', false);
    } finally {
        foreach ($adapters as $index => $adapter) {
            if ($original[$index] === null) {
                $adapter->delete('APP_NAME');
            } else {
                $adapter->write('APP_NAME', $original[$index]);
            }
        }
    }
});
