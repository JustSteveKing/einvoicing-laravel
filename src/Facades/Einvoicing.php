<?php

declare(strict_types=1);

namespace Einvoicing\Laravel\Facades;

use Einvoicing\Client;
use Einvoicing\Laravel\Einvoicing as Manager;
use Einvoicing\Laravel\Testing\EinvoicingFake;
use Einvoicing\Responses\Conversion;
use Einvoicing\Responses\Participant;
use Einvoicing\Responses\Usage;
use Einvoicing\Responses\ValidationReport;
use Illuminate\Support\Facades\Facade;

/**
 * @method static ValidationReport validate(string $document, ?string $ruleset = null)
 * @method static Conversion convert(array<string, mixed> $invoice, string $target = 'peppol-bis-billing-3', ?string $ruleset = null)
 * @method static Participant participant(string $id, bool $fresh = false)
 * @method static bool canReceive(string $id, string $documentType = 'Invoice-2::Invoice')
 * @method static Usage usage()
 * @method static \Einvoicing\Resources\Rulesets rulesets()
 * @method static \Einvoicing\Resources\Keys keys()
 * @method static \Einvoicing\Resources\Account account()
 * @method static \Einvoicing\Resources\Billing billing()
 * @method static Client client()
 *
 * @see Manager
 */
final class Einvoicing extends Facade
{
    /**
     * Swap the API for one that answers from memory and records what it was
     * asked. Nothing leaves the machine after this.
     */
    public static function fake(): EinvoicingFake
    {
        self::swap($fake = new EinvoicingFake);

        return $fake;
    }

    protected static function getFacadeAccessor(): string
    {
        return Manager::class;
    }
}
