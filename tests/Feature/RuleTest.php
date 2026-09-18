<?php

declare(strict_types=1);

use Einvoicing\Laravel\Facades\Einvoicing;
use Einvoicing\Laravel\Rules\PeppolDocument;
use Illuminate\Support\Facades\Validator;

it('passes a valid document', function (): void {
    Einvoicing::fake();

    $validator = Validator::make(
        ['invoice' => '<Invoice/>'],
        ['invoice' => [new PeppolDocument]],
    );

    expect($validator->passes())->toBeTrue();
});

it('reports every error, not only the first', function (): void {
    Einvoicing::fake()->shouldBeInvalid(['PEPPOL-EN16931-R003', 'BR-CO-10']);

    $validator = Validator::make(
        ['invoice' => '<Invoice/>'],
        ['invoice' => [new PeppolDocument]],
    );

    expect($validator->fails())->toBeTrue()
        ->and($validator->errors()->get('invoice'))->toHaveCount(2)
        ->and($validator->errors()->first('invoice'))->toContain('PEPPOL-EN16931-R003');
});

it('rejects something that is not a document at all', function (): void {
    $fake = Einvoicing::fake();

    $validator = Validator::make(
        ['invoice' => 42],
        ['invoice' => [new PeppolDocument]],
    );

    expect($validator->fails())->toBeTrue()
        ->and($validator->errors()->first('invoice'))->toContain('must be a UBL document');

    // Nothing was sent anywhere to learn that.
    $fake->assertNothingValidated();
});

it('leaves an absent value to the required rule', function (): void {
    Einvoicing::fake();

    $validator = Validator::make(
        ['invoice' => ''],
        ['invoice' => ['required', new PeppolDocument]],
    );

    expect($validator->fails())->toBeTrue()
        ->and($validator->errors()->first('invoice'))->toContain('required');
});

it('leaves warnings alone unless asked not to', function (): void {
    Einvoicing::fake()->shouldBeInvalid([
        ['rule_id' => 'PEPPOL-EN16931-R110', 'layer' => 'peppol', 'severity' => 'warning', 'message' => 'Dates read backwards.'],
    ]);

    $lenient = Validator::make(['invoice' => '<Invoice/>'], ['invoice' => [new PeppolDocument]]);
    $strict = Validator::make(['invoice' => '<Invoice/>'], ['invoice' => [new PeppolDocument(warningsFail: true)]]);

    expect($lenient->passes())->toBeTrue()
        ->and($strict->fails())->toBeTrue();
});
