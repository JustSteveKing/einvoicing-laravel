<?php

declare(strict_types=1);

use Einvoicing\Laravel\Facades\Einvoicing;

it('passes everything by default and records what it was asked', function (): void {
    $fake = Einvoicing::fake();

    $report = Einvoicing::validate('<Invoice>INV-1</Invoice>');

    expect($report->valid)->toBeTrue();

    $fake->assertValidated();
    $fake->assertValidatedCount(1);
    $fake->assertValidated(fn (string $document): bool => str_contains($document, 'INV-1'));
});

it('fails on demand, with the rules you name', function (): void {
    Einvoicing::fake()->shouldBeInvalid(['PEPPOL-EN16931-R003', 'BR-CO-10']);

    $report = Einvoicing::validate('<Invoice/>');

    expect($report->valid)->toBeFalse()
        ->and($report->errors())->toHaveCount(2)
        ->and($report->errors()[0]->ruleId)->toBe('PEPPOL-EN16931-R003');
});

it('takes a full finding when a test cares about the detail', function (): void {
    Einvoicing::fake()->shouldBeInvalid([[
        'rule_id' => 'BR-CO-10',
        'layer' => 'en16931',
        'severity' => 'error',
        'message' => 'Sum of line amounts must equal the invoice total.',
        'fix' => 'Check the line totals.',
        'business_terms' => ['BT-106'],
    ]]);

    $finding = Einvoicing::validate('<Invoice/>')->errors()[0];

    expect($finding->layer)->toBe('en16931')
        ->and($finding->fix)->toBe('Check the line totals.')
        ->and($finding->businessTerms)->toBe(['BT-106']);
});

it('says nothing was validated when nothing was', function (): void {
    Einvoicing::fake()->assertNothingValidated();
});

it('records conversions', function (): void {
    $fake = Einvoicing::fake();

    $conversion = Einvoicing::convert(['number' => 'INV-1']);

    expect($conversion->validation->valid)->toBeTrue();
    $fake->assertConverted(fn (array $invoice): bool => $invoice['number'] === 'INV-1');
});

it('finds everyone registered until told otherwise', function (): void {
    $fake = Einvoicing::fake();

    expect(Einvoicing::canReceive('9932:GB123456789'))->toBeTrue();

    $fake->assertLookedUp('9932:GB123456789');
});

it('can put a participant off the network', function (): void {
    Einvoicing::fake()->shouldFind('9932:GB999999999', registered: false);

    $participant = Einvoicing::participant('9932:GB999999999');

    expect($participant->registered)->toBeFalse()
        ->and($participant->capabilities)->toBe([]);
});

it('can put a participant on the network without invoices', function (): void {
    Einvoicing::fake()->shouldFind('9932:GB123456789', documentTypes: ['Order-2::Order']);

    expect(Einvoicing::participant('9932:GB123456789')->registered)->toBeTrue()
        ->and(Einvoicing::canReceive('9932:GB123456789'))->toBeFalse();
});

it('can empty the whole network', function (): void {
    Einvoicing::fake()->shouldFindNobody();

    expect(Einvoicing::participant('9932:GB123456789')->registered)->toBeFalse();
});
