<?php

declare(strict_types=1);

namespace Einvoicing\Laravel;

use Einvoicing\Client;
use Einvoicing\Resources\Account;
use Einvoicing\Resources\Billing;
use Einvoicing\Resources\Keys;
use Einvoicing\Resources\Rulesets;
use Einvoicing\Responses\Conversion;
use Einvoicing\Responses\Participant;
use Einvoicing\Responses\Usage;
use Einvoicing\Responses\ValidationReport;
use Illuminate\Contracts\Cache\Repository;

/**
 * The package's front door, and what the Einvoicing facade resolves to.
 *
 * It is a thin layer over the SDK: it applies the configured ruleset so you
 * do not have to pass it everywhere, caches participant lookups through
 * Laravel's cache, and otherwise hands you the SDK's own objects.
 */
class Einvoicing
{
    public function __construct(
        protected readonly Client $client,
        protected readonly Repository $cache,
        protected readonly ?string $ruleset = null,
        protected readonly int $ttl = 300,
        protected readonly string $prefix = 'einvoicing:participant:',
    ) {}

    /**
     * Validate a UBL 2.1 document.
     *
     * An invalid document is a successful call: the report comes back with
     * `valid` false and every finding on it.
     */
    public function validate(string $document, ?string $ruleset = null): ValidationReport
    {
        return $this->client->validations()->validate($document, $ruleset ?? $this->ruleset);
    }

    /**
     * Turn your own invoice data into a Peppol document.
     *
     * @param  array<string, mixed>  $invoice
     */
    public function convert(array $invoice, string $target = 'peppol-bis-billing-3', ?string $ruleset = null): Conversion
    {
        return $this->client->conversions()->convert($invoice, $target, $ruleset ?? $this->ruleset);
    }

    /**
     * Look a participant up, through the cache.
     *
     * Pass `fresh: true` after a send fails: a participant who moves Access
     * Point keeps the same identifier, so a cached answer can go stale while
     * the identifier stays right.
     */
    public function participant(string $id, bool $fresh = false): Participant
    {
        if ($this->ttl <= 0 || $fresh) {
            $participant = $this->client->participants()->find($id);

            if ($this->ttl > 0) {
                $this->cache->put($this->prefix.$id, $participant, $this->ttl);
            }

            return $participant;
        }

        return $this->cache->remember(
            key: $this->prefix.$id,
            ttl: $this->ttl,
            callback: fn (): Participant => $this->client->participants()->find($id),
        );
    }

    /** Can this participant receive this document type today? */
    public function canReceive(string $id, string $documentType = 'Invoice-2::Invoice'): bool
    {
        $participant = $this->participant($id);

        return $participant->registered && $participant->accepts($documentType);
    }

    public function usage(): Usage
    {
        return $this->client->usage()->get();
    }

    public function rulesets(): Rulesets
    {
        return $this->client->rulesets();
    }

    public function keys(): Keys
    {
        return $this->client->keys();
    }

    public function account(): Account
    {
        return $this->client->account();
    }

    public function billing(): Billing
    {
        return $this->client->billing();
    }

    /** The SDK client underneath, for anything this wrapper does not cover. */
    public function client(): Client
    {
        return $this->client;
    }
}
