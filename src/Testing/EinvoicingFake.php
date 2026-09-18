<?php

declare(strict_types=1);

namespace Einvoicing\Laravel\Testing;

use Closure;
use Einvoicing\Client;
use Einvoicing\Laravel\Einvoicing;
use Einvoicing\Responses\Conversion;
use Einvoicing\Responses\Finding;
use Einvoicing\Responses\Participant;
use Einvoicing\Responses\Usage;
use Einvoicing\Responses\ValidationReport;
use PHPUnit\Framework\Assert as PHPUnit;
use RuntimeException;

/**
 * The API, in memory.
 *
 * Nothing leaves the machine once this is installed, and everything it was
 * asked is recorded. Documents come back valid unless you say otherwise, and
 * participants come back registered, because the interesting tests are the
 * ones where you say otherwise.
 */
final class EinvoicingFake extends Einvoicing
{
    /** @var list<array{document: string, ruleset: string|null}> */
    public array $validated = [];

    /** @var list<array<string, mixed>> */
    public array $converted = [];

    /** @var list<string> */
    public array $lookups = [];

    private ?ValidationReport $report = null;

    /** @var array<string, Participant> */
    private array $participants = [];

    private ?Participant $default = null;

    /** Deliberately does not call the parent: there is nothing to talk to. */
    public function __construct() {}

    /**
     * Answer every validation with a failure.
     *
     * Pass rule ids for the common case, or full findings when a test cares
     * about the message, the layer or the business terms.
     *
     * @param  list<string|array<string, mixed>>  $findings
     */
    public function shouldBeInvalid(array $findings = ['PEPPOL-EN16931-R003']): self
    {
        $this->report = new ValidationReport(
            valid: false,
            ruleset: ['id' => 'peppol-bis-billing-3.0.21'],
            document: [],
            layers: [],
            summary: ['errors' => count($findings), 'warnings' => 0],
            findings: array_map(
                static fn (string|array $finding): Finding => Finding::fromArray(
                    is_string($finding)
                        ? ['rule_id' => $finding, 'layer' => 'peppol', 'severity' => 'error']
                        : $finding,
                ),
                $findings,
            ),
        );

        return $this;
    }

    /** Answer every validation with a pass. This is the default. */
    public function shouldBeValid(): self
    {
        $this->report = null;

        return $this;
    }

    /**
     * Say what a particular identifier resolves to.
     *
     * @param  list<string>  $documentTypes
     */
    public function shouldFind(string $id, bool $registered = true, array $documentTypes = ['Invoice-2::Invoice']): self
    {
        $this->participants[$id] = new Participant(
            id: mb_strtolower($id),
            scheme: str_contains($id, ':') ? explode(':', $id)[0] : '',
            identifier: str_contains($id, ':') ? mb_strtolower(explode(':', $id, 2)[1]) : mb_strtolower($id),
            registered: $registered,
            capabilities: array_map(
                static fn (string $type): array => ['document_type' => $type],
                $registered ? $documentTypes : [],
            ),
            directory: null,
            checkedAt: now()->toIso8601ZuluString(),
        );

        return $this;
    }

    /** Nobody is on the network. */
    public function shouldFindNobody(): self
    {
        $this->default = new Participant('', '', '', false, [], null, now()->toIso8601ZuluString());

        return $this;
    }

    public function validate(string $document, ?string $ruleset = null): ValidationReport
    {
        $this->validated[] = ['document' => $document, 'ruleset' => $ruleset];

        return $this->report ?? new ValidationReport(
            valid: true,
            ruleset: ['id' => 'peppol-bis-billing-3.0.21'],
            document: [],
            layers: [],
            summary: ['errors' => 0, 'warnings' => 0],
            findings: [],
        );
    }

    /** @param array<string, mixed> $invoice */
    public function convert(array $invoice, string $target = 'peppol-bis-billing-3', ?string $ruleset = null): Conversion
    {
        $this->converted[] = $invoice;

        return new Conversion(
            target: $target,
            document: '<?xml version="1.0" encoding="UTF-8"?><Invoice/>',
            totals: [],
            vatBreakdown: [],
            validation: $this->validate('<Invoice/>', $ruleset),
        );
    }

    public function participant(string $id, bool $fresh = false): Participant
    {
        $this->lookups[] = $id;

        return $this->participants[$id]
            ?? $this->default
            ?? $this->shouldFind($id)->participants[$id];
    }

    public function usage(): Usage
    {
        throw new RuntimeException('The fake does not answer usage. Assert on what your code sends instead.');
    }

    public function client(): Client
    {
        throw new RuntimeException('The fake has no client underneath it.');
    }

    /**
     * Assert something was validated, optionally one thing in particular.
     *
     * @param  (Closure(string, string|null): bool)|null  $callback
     */
    public function assertValidated(?Closure $callback = null): void
    {
        if ($callback === null) {
            PHPUnit::assertNotEmpty($this->validated, 'No document was validated.');

            return;
        }

        $matched = array_filter(
            $this->validated,
            static fn (array $call): bool => $callback($call['document'], $call['ruleset']),
        );

        PHPUnit::assertNotEmpty($matched, 'No validated document matched.');
    }

    public function assertValidatedCount(int $times): void
    {
        PHPUnit::assertCount($times, $this->validated, "Expected {$times} documents to be validated.");
    }

    public function assertNothingValidated(): void
    {
        PHPUnit::assertEmpty($this->validated, 'A document was validated.');
    }

    /** @param (Closure(array<string, mixed>): bool)|null $callback */
    public function assertConverted(?Closure $callback = null): void
    {
        if ($callback === null) {
            PHPUnit::assertNotEmpty($this->converted, 'No invoice was converted.');

            return;
        }

        PHPUnit::assertNotEmpty(
            array_filter($this->converted, static fn (array $invoice): bool => $callback($invoice)),
            'No converted invoice matched.',
        );
    }

    public function assertLookedUp(string $id): void
    {
        PHPUnit::assertContains($id, $this->lookups, "{$id} was never looked up.");
    }

    public function assertNothingLookedUp(): void
    {
        PHPUnit::assertEmpty($this->lookups, 'A participant was looked up.');
    }
}
