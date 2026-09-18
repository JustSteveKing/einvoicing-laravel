<?php

declare(strict_types=1);

namespace Einvoicing\Laravel;

use Einvoicing\Client;
use Einvoicing\Laravel\Commands\ParticipantCommand;
use Einvoicing\Laravel\Commands\UsageCommand;
use Einvoicing\Laravel\Commands\ValidateCommand;
use Illuminate\Contracts\Cache\Factory as CacheFactory;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Foundation\Console\AboutCommand;
use Illuminate\Support\ServiceProvider;

final class EinvoicingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/einvoicing.php', 'einvoicing');

        // No HTTP wiring here at all. php-http/discovery finds the PSR-18
        // client and PSR-17 factories from whatever the application installed,
        // which is the same answer three container lookups would have reached
        // more slowly.
        //
        // To use your own transport, replace this binding rather than the
        // pieces inside it: bind Einvoicing\Client and construct it with the
        // client you want. One override point, and it is the whole object.
        $this->app->singleton(Client::class, static fn (Application $app): Client => new Client(
            key: self::config($app, 'key') ?? '',
            baseUrl: self::config($app, 'url') ?? 'https://api.einvoicing.dev',
        ));

        $this->app->singleton(Einvoicing::class, static function (Application $app): Einvoicing {
            $cache = $app->make(CacheFactory::class);

            return new Einvoicing(
                client: $app->make(Client::class),
                cache: $cache->store(self::config($app, 'cache.store')),
                ruleset: self::config($app, 'ruleset'),
                ttl: (int) (self::config($app, 'cache.ttl') ?? 300),
                prefix: self::config($app, 'cache.prefix') ?? 'einvoicing:participant:',
            );
        });

        $this->app->alias(Einvoicing::class, 'einvoicing');
    }

    public function boot(): void
    {
        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->publishes([
            __DIR__.'/../config/einvoicing.php' => $this->app->configPath('einvoicing.php'),
        ], 'einvoicing-config');

        $this->commands([
            ValidateCommand::class,
            ParticipantCommand::class,
            UsageCommand::class,
        ]);

        if (class_exists(AboutCommand::class)) {
            AboutCommand::add('Einvoicing', fn (): array => [
                'Host' => self::config($this->app, 'url') ?? 'https://api.einvoicing.dev',
                'Ruleset' => self::config($this->app, 'ruleset') ?? 'current',
                'Key' => self::config($this->app, 'key') === null ? 'NOT SET' : 'set',
            ]);
        }
    }

    /** @return list<string> */
    public function provides(): array
    {
        return [Client::class, Einvoicing::class, 'einvoicing'];
    }

    /**
     * Config is `mixed` all the way down, and a package that casts it blindly
     * turns a typo in someone's .env into a confusing error somewhere else.
     * Anything that is not a string is treated as absent.
     */
    private static function config(Application $app, string $key): ?string
    {
        $value = $app->make('config')->get("einvoicing.{$key}");

        if (is_string($value)) {
            return $value === '' ? null : $value;
        }

        return is_int($value) ? (string) $value : null;
    }
}
