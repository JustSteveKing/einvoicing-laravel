<?php

declare(strict_types=1);

use Einvoicing\Laravel\Facades\Einvoicing;
use Illuminate\Support\Facades\Cache;

it('resolves a configured client through the container', function (): void {
    $history = fakeTransport([json(['data' => ['valid' => true]])]);

    Einvoicing::validate('<Invoice/>');

    $request = lastRequest($history);
    expect((string) $request->getUri())->toBe('https://api.einvoicing.dev/v1/validations')
        ->and($request->getHeaderLine('Authorization'))->toBe('Bearer sk_test_example')
        ->and($request->getHeaderLine('Content-Type'))->toBe('application/xml');
});

it('applies the configured ruleset without being asked', function (): void {
    config()->set('einvoicing.ruleset', 'peppol-bis-billing-3.0.21');

    $history = fakeTransport([json(['data' => ['valid' => true]])]);

    Einvoicing::validate('<Invoice/>');

    expect((string) lastRequest($history)->getUri())
        ->toContain('ruleset=peppol-bis-billing-3.0.21');
});

it('lets the call override the configured ruleset', function (): void {
    config()->set('einvoicing.ruleset', 'peppol-bis-billing-3.0.21');

    $history = fakeTransport([json(['data' => ['valid' => true]])]);

    Einvoicing::validate('<Invoice/>', 'peppol-bis-billing-3.0.20');

    expect((string) lastRequest($history)->getUri())->toContain('3.0.20');
});

it('caches a participant lookup', function (): void {
    $history = fakeTransport([
        json(['data' => [
            'id' => '9932:gb123456789',
            'scheme' => '9932',
            'identifier' => 'gb123456789',
            'registered' => true,
            'capabilities' => [['document_type' => 'Invoice-2::Invoice']],
            'checked_at' => '2026-09-18T09:00:00.000Z',
        ]]),
    ]);

    $first = Einvoicing::participant('9932:GB123456789');
    $second = Einvoicing::participant('9932:GB123456789');

    expect($history)->toHaveCount(1)
        ->and($second->identifier)->toBe($first->identifier)
        ->and(Cache::has('einvoicing:participant:9932:GB123456789'))->toBeTrue();
});

it('asks again when told to', function (): void {
    $participant = json(['data' => [
        'id' => '9932:gb123456789',
        'registered' => true,
        'capabilities' => [],
        'checked_at' => '2026-09-18T09:00:00.000Z',
    ]]);

    $history = fakeTransport([$participant, $participant]);

    Einvoicing::participant('9932:GB123456789');
    Einvoicing::participant('9932:GB123456789', fresh: true);

    expect($history)->toHaveCount(2);
});

it('does not cache when the ttl is zero', function (): void {
    config()->set('einvoicing.cache.ttl', 0);

    $participant = json(['data' => ['registered' => true, 'capabilities' => []]]);

    $history = fakeTransport([$participant, $participant]);

    Einvoicing::participant('9932:GB123456789');
    Einvoicing::participant('9932:GB123456789');

    expect($history)->toHaveCount(2)
        ->and(Cache::has('einvoicing:participant:9932:GB123456789'))->toBeFalse();
});

it('answers whether a participant can receive an invoice', function (): void {
    fakeTransport([
        json(['data' => [
            'registered' => true,
            'capabilities' => [['document_type' => 'urn:…:Invoice-2::Invoice']],
        ]]),
        json(['data' => ['registered' => true, 'capabilities' => [['document_type' => 'Order-2::Order']]]]),
    ]);

    expect(Einvoicing::canReceive('9932:GB123456789'))->toBeTrue()
        ->and(Einvoicing::canReceive('9932:GB999999999'))->toBeFalse();
});
