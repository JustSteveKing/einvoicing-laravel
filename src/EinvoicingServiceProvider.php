<?php

declare(strict_types=1);

namespace Einvoicing\Laravel;

use Einvoicing\Client;
use Einvoicing\Laravel\Commands\ParticipantCommand;
use Einvoicing\Laravel\Commands\UsageCommand;
use Einvoicing\Laravel\Commands\ValidateCommand;
use GuzzleHttp\Client as Guzzle;
use GuzzleHttp\Psr7\HttpFactory;
use Illuminate\Contracts\Cache\Factory as CacheFactory;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Foundation\Console\AboutCommand;
use Illuminate\Support\ServiceProvider;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;

final class EinvoicingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/einvoicing.php', 'einvoicing');

        // Guzzle is what Laravel ships with, and it is both a PSR-18 client
        // and a source of PSR-17 factories. Bind any of these three yourself
        // and the package uses yours instead.
        $this->app->bindIf(ClientInterface::class, static fn (): ClientInterface => new Guzzle);
        $this->app->bindIf(RequestFactoryInterface::class, static fn (): RequestFactoryInterface => new HttpFactory);
        $this->app->bindIf(StreamFactoryInterface::class, static fn (): StreamFactoryInterface => new HttpFactory);

        $this->app->singleton(Client::class, static fn (Application $app): Client => new Client(
            http: $app->make(ClientInterface::class),
            requests: $app->make(RequestFactoryInterface::class),
            streams: $app->make(StreamFactoryInterface::class),
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
