<?php

declare(strict_types=1);

namespace Einvoicing\Laravel\Tests;

use Einvoicing\Laravel\EinvoicingServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    /** @return list<class-string> */
    protected function getPackageProviders($app): array
    {
        return [EinvoicingServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('einvoicing.key', 'sk_test_example');
        $app['config']->set('cache.default', 'array');
    }
}
