<?php

declare(strict_types=1);

namespace Tests;

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    public function createApplication(): Application
    {
        $app = parent::createApplication();

        throw_if(! $app->environment('testing') || ! preg_match('/^testing(?:_test_\d+)?$/', DB::connection()->getDatabaseName()), RuntimeException::class, 'Refusing to run database tests outside the isolated testing database.');

        return $app;
    }
}
