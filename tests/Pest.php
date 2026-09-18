<?php

declare(strict_types=1);

use Einvoicing\Client;
use Einvoicing\Laravel\Tests\TestCase;
use GuzzleHttp\Client as Guzzle;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Illuminate\Testing\PendingCommand;

use function Pest\Laravel\artisan;

use Psr\Http\Message\RequestInterface;

uses(TestCase::class)->in('Feature', 'Unit');

/**
 * Put a mock transport under the package by binding the SDK client itself.
 *
 * That is the same override point the README gives an application, so a test
 * exercises the documented seam rather than one that only exists for tests.
 * Everything above it — manager, cache, commands — is the real thing.
 *
 * @param  list<Response>  $responses
 * @return ArrayObject<int, RequestInterface>
 */
function fakeTransport(array $responses): ArrayObject
{
    /** @var ArrayObject<int, RequestInterface> $history */
    $history = new ArrayObject;

    $stack = HandlerStack::create(new MockHandler($responses));

    // Guzzle ships a history middleware; this one is three lines and keeps
    // the recorded type honest.
    $stack->push(static fn (callable $handler): callable => static function (
        RequestInterface $request,
        array $options,
    ) use ($handler, $history): mixed {
        $history->append($request);

        return $handler($request, $options);
    });

    app()->instance(Client::class, new Client(
        key: 'sk_test_example',
        http: new Guzzle(['handler' => $stack]),
    ));

    // The manager caches the client it was built with, so it has to go.
    app()->forgetInstance(Einvoicing\Laravel\Einvoicing::class);

    return $history;
}

/** @param array<string, mixed>|string $body */
function json(array|string $body, int $status = 200): Response
{
    return new Response(
        $status,
        ['Content-Type' => 'application/json'],
        is_string($body) ? $body : (string) json_encode($body),
    );
}

/** @param ArrayObject<int, RequestInterface> $history */
function lastRequest(ArrayObject $history): RequestInterface
{
    $sent = $history->getArrayCopy();
    $last = end($sent);

    if ($last === false) {
        throw new RuntimeException('Nothing has been sent.');
    }

    return $last;
}

/**
 * Pest's artisan() can hand back an exit code instead of a pending command
 * when there is nothing to assert against. There always is here.
 *
 * @param  array<string, mixed>  $parameters
 */
function runArtisan(string $command, array $parameters = []): PendingCommand
{
    $pending = artisan($command, $parameters);

    if (! $pending instanceof PendingCommand) {
        throw new RuntimeException("{$command} did not run.");
    }

    return $pending;
}
