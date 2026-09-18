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
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;

final class EinvoicingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/einvoicing.php', 'einvoicing');

        // The container wins where it has an opinion; the SDK discovers the
        // rest. Binding any of the three is the documented way to supply your
        // own transport — an instrumented client, a fake — and a binding beats
        // discovery because somebody chose it.
        //
        // Nothing is bound here, so this package names no HTTP client. Laravel
        // does not ship one either: whatever the application installed is what
        // gets found.
        $this->app->singleton(Client::class, static fn (Application $app): Client => new Client(
            key: self::config($app, 'key') ?? '',
            http: self::bound($app, ClientInterface::class),
            requests: self::bound($app, RequestFactoryInterface::class),
            streams: self::bound($app, StreamFactoryInterface::class),
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
     * A container binding if there is one, null to let the SDK discover.
     *
     * Deliberately not `make()`: resolving an unbound interface would have
     * Laravel try to instantiate it and fail, when "nobody chose one" is the
     * ordinary case and discovery is the answer to it.
     *
     * @template T of object
     *
     * @param  class-string<T>  $abstract
     * @return T|null
     */
    private static function bound(Application $app, string $abstract): ?object
    {
        if (! $app->bound($abstract)) {
            return null;
        }

        $resolved = $app->make($abstract);

        // A binding that resolves to something else is the application's bug,
        // but discovering a working client beats a TypeError from in here.
        return $resolved instanceof $abstract ? $resolved : null;
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
