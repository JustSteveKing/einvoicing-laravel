<?php

declare(strict_types=1);

use Einvoicing\Client;
use GuzzleHttp\Client as Guzzle;
use Psr\Http\Client\ClientInterface;

/** Reach the client the SDK is actually holding. */
function transportOf(Client $client): ClientInterface
{
    $transport = (new ReflectionClass($client))->getProperty('http')->getValue($client);

    if (! $transport instanceof ClientInterface) {
        throw new RuntimeException('The SDK is not holding a PSR-18 client at all.');
    }

    return $transport;
}

it('discovers a client when the container has no opinion', function (): void {
    // Nothing is bound: no Guzzle binding from the provider any more, and the
    // test has not bound one either. Discovery has to find it.
    expect(app()->bound(ClientInterface::class))->toBeFalse();

    $client = app()->make(Client::class);

    expect(transportOf($client))->toBeInstanceOf(ClientInterface::class);
});

it('prefers a container binding over discovery', function (): void {
    $mine = new Guzzle(['timeout' => 1]);
    app()->instance(ClientInterface::class, $mine);
    app()->forgetInstance(Client::class);

    expect(transportOf(app()->make(Client::class)))->toBe($mine);
});

it('resolves without guzzle being named anywhere in the package', function (): void {
    // The provider must not mention a concrete implementation. If someone
    // reintroduces one, this is the test that says so.
    $source = file_get_contents(__DIR__.'/../../src/EinvoicingServiceProvider.php');

    expect($source)->not->toContain('GuzzleHttp');
});
