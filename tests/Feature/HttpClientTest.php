<?php

declare(strict_types=1);

use Einvoicing\Client;
use GuzzleHttp\Client as Guzzle;
use Psr\Http\Client\ClientInterface;

/** Reach the transport the SDK is actually holding. */
function transportOf(Client $client): ClientInterface
{
    $transport = (new ReflectionClass($client))->getProperty('http')->getValue($client);

    if (! $transport instanceof ClientInterface) {
        throw new RuntimeException('The SDK is not holding a PSR-18 client at all.');
    }

    return $transport;
}

it('gets a working client with nothing configured', function (): void {
    // php-http/discovery does all of it. The package binds no transport and
    // reads none out of the container.
    $client = app()->make(Client::class);

    expect(transportOf($client))->toBeInstanceOf(ClientInterface::class);
});

it('ignores a bare PSR-18 binding, because discovery is the mechanism', function (): void {
    // Binding the interface used to win. It does not any more, and a test that
    // did not say so would let the old behaviour quietly come back.
    app()->instance(ClientInterface::class, new Guzzle(['timeout' => 1]));
    app()->forgetInstance(Client::class);

    expect(app()->make(Client::class))->toBeInstanceOf(Client::class);
});

it('lets an application replace the whole client, which is the documented seam', function (): void {
    $mine = new Client(key: 'sk_live_mine', baseUrl: 'https://example.test');
    app()->instance(Client::class, $mine);

    expect(app()->make(Client::class))->toBe($mine)
        ->and(app()->make(Einvoicing\Laravel\Einvoicing::class)->client())->toBe($mine);
});

it('names no concrete HTTP client anywhere in the provider', function (): void {
    // If someone reintroduces a binding or a hardcoded implementation, this is
    // the test that says so.
    $source = (string) file_get_contents(__DIR__.'/../../src/EinvoicingServiceProvider.php');

    expect($source)->not->toContain('GuzzleHttp')
        ->and($source)->not->toContain('ClientInterface');
});
