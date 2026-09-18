# einvoicing/laravel

Peppol e-invoicing for Laravel: validate, convert and look up documents with
the [einvoicing.dev](https://www.einvoicing.dev) API.

It wraps [einvoicing/sdk](https://github.com/einvoicing/sdk) in the things a
Laravel application already expects — a config file, a facade, a validation
rule, Artisan commands, cached lookups and a testing fake — so nothing here
needs a wrapper of your own.

## Install

```bash
composer require einvoicing/laravel
php artisan vendor:publish --tag=einvoicing-config
```

```dotenv
EINVOICING_KEY=sk_test_...
EINVOICING_RULESET=peppol-bis-billing-3.0.21
```

Pin the ruleset. Leave it out and validation follows whatever release is
current, which means a change elsewhere can turn a passing build red without
anything of yours changing.

## Validating

```php
use Einvoicing\Laravel\Facades\Einvoicing;

$report = Einvoicing::validate($xml);

if (! $report->valid) {
    foreach ($report->errors() as $finding) {
        logger()->warning("{$finding->ruleId}: {$finding->message}", [
            'fix' => $finding->fix,
            'terms' => $finding->businessTerms,
        ]);
    }
}
```

An invalid document is a successful call. The report carries every finding,
not only the first — the second one is usually the interesting one.

### As a validation rule

```php
use Einvoicing\Laravel\Rules\PeppolDocument;

$request->validate([
    'invoice' => ['required', 'string', new PeppolDocument],
]);
```

Each error becomes its own message, naming the rule and quoting the fix.
Warnings are ignored unless you ask for them: `new PeppolDocument(warningsFail: true)`.

## Converting your own data

```php
$conversion = Einvoicing::convert([
    'number' => $invoice->number,
    'issued' => $invoice->issued_at->toDateString(),
    'currency' => $invoice->currency,
    // seller, buyer, lines, payment...
]);

Storage::put("invoices/{$invoice->id}.xml", $conversion->document);
```

Totals and the VAT breakdown are worked out from the lines, and the result is
validated before it is returned. An invoice that cannot produce a valid
document throws `InvalidInvoiceException`, whose `findings()` say why.

## Looking a participant up

```php
if (! Einvoicing::canReceive($customer->peppol_id)) {
    // Tell them, rather than failing a send later.
}

$participant = Einvoicing::participant($customer->peppol_id);
$participant->registered;
$participant->capabilities;
```

Lookups go through Laravel's cache — five minutes by default, matching the
API's own caching. A participant who moves Access Point keeps their
identifier, so re-check with `Einvoicing::participant($id, fresh: true)` when
a send fails.

Two traps the SDK handles for you. A business absent from the optional Peppol
Directory may still be registered and reachable, so `directory` being null
means nothing on its own. And a UK VAT number is registered with or without
its `GB` prefix, as two different participants; the lookup tries both and
reports the form that answered. Store that form.

## From the command line

```bash
php artisan einvoicing:validate storage/app/invoice.xml
php artisan einvoicing:validate storage/app/invoice.xml --strict
php artisan einvoicing:participant 9932:GB123456789
php artisan einvoicing:usage
```

`einvoicing:validate` exits non-zero on an invalid document, so it drops into
CI as it stands.

## Testing

```php
use Einvoicing\Laravel\Facades\Einvoicing;

it('will not send an invoice the network would reject', function () {
    $fake = Einvoicing::fake()->shouldBeInvalid(['PEPPOL-EN16931-R003']);

    $this->post('/invoices/1/send')->assertSessionHasErrors();

    $fake->assertValidated();
});
```

Nothing leaves the machine once the fake is installed. Documents come back
valid and participants come back registered unless you say otherwise, because
the interesting tests are the ones where you say otherwise:

```php
Einvoicing::fake()->shouldBeInvalid(['BR-CO-10']);              // by rule id
Einvoicing::fake()->shouldBeInvalid([[...]]);                    // full findings
Einvoicing::fake()->shouldFind('9932:GB1', registered: false);   // off the network
Einvoicing::fake()->shouldFind('9932:GB1', documentTypes: ['Order-2::Order']);
Einvoicing::fake()->shouldFindNobody();
```

And the assertions:

```php
$fake->assertValidated();
$fake->assertValidated(fn (string $xml) => str_contains($xml, 'INV-1'));
$fake->assertValidatedCount(1);
$fake->assertNothingValidated();
$fake->assertConverted(fn (array $invoice) => $invoice['number'] === 'INV-1');
$fake->assertLookedUp('9932:GB123456789');
$fake->assertNothingLookedUp();
```

## Swapping the HTTP client

The package binds Guzzle as its PSR-18 client and PSR-17 factories, because
that is what Laravel ships with. Bind any of the three yourself and yours is
used instead:

```php
$this->app->bind(ClientInterface::class, fn () => new MyClient);
```

## Errors

Every failure is an `Einvoicing\Exceptions\EinvoicingException`:
`UnauthenticatedException`, `AllowanceExhaustedException`,
`RateLimitedException` (with `retryAfter()`), `NotFoundException`,
`InvalidInvoiceException` (with `findings()`), `ProblemException` and
`TransportException`. Branch on `$e->type` or `$e->slug()`, never on the
title or the detail — those are prose for a human reading a log.

## Development

```bash
composer test   # Pest, against Testbench
composer stan   # PHPStan, level 10
composer lint   # Pint
```

## Licence

MIT.
