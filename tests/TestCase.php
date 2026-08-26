<?php

namespace Homemove\AbTesting\Tests;

use Homemove\AbTesting\Providers\AbTestingServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;
use Illuminate\Foundation\Testing\RefreshDatabase;

abstract class TestCase extends Orchestra
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->loadLaravelMigrations();
    }

    protected function getPackageProviders($app): array
    {
        return [
            AbTestingServiceProvider::class,
        ];
    }

    protected function getPackageAliases($app): array
    {
        return [
            'AbTest' => \Homemove\AbTesting\Facades\AbTest::class,
        ];
    }

    protected function getEnvironmentSetUp($app): void
    {
        // Dashboard routes use the web (encrypted-cookie) middleware — a key
        // is required for any feature test that hits them.
        $app['config']->set('app.key', 'base64:' . base64_encode(random_bytes(32)));
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        $app['config']->set('cache.default', 'array');
        $app['config']->set('session.driver', 'array');
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../src/database/migrations');
    }
}
