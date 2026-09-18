<?php

declare(strict_types=1);

namespace Einvoicing\Laravel\Rules;

use Closure;
use Einvoicing\Exceptions\EinvoicingException;
use Einvoicing\Laravel\Facades\Einvoicing;
use Einvoicing\Responses\Finding;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Validates a UBL document against the published rules.
 *
 *     $request->validate([
 *         'invoice' => ['required', 'string', new PeppolDocument],
 *     ]);
 *
 * Every error becomes its own message, because the second finding is usually
 * the interesting one and a rule that reports only the first sends people
 * round the loop once per problem.
 */
final class PeppolDocument implements ValidationRule
{
    public function __construct(
        private readonly ?string $ruleset = null,
        private readonly bool $warningsFail = false,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || trim($value) === '') {
            $fail('The :attribute must be a UBL document.');

            return;
        }

        try {
            $report = Einvoicing::validate($value, $this->ruleset);
        } catch (EinvoicingException $e) {
            $fail("The :attribute could not be validated: {$e->getMessage()}");

            return;
        }

        if ($report->valid && ! $this->warningsFail) {
            return;
        }

        $findings = $this->warningsFail ? $report->findings : $report->errors();

        foreach ($findings as $finding) {
            $fail($this->message($finding));
        }
    }

    private function message(Finding $finding): string
    {
        $message = "The :attribute breaks {$finding->ruleId}: {$finding->message}";

        return $finding->fix === null ? $message : "{$message} {$finding->fix}";
    }
}
