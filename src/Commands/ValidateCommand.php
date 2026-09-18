<?php

declare(strict_types=1);

namespace Einvoicing\Laravel\Commands;

use Einvoicing\Laravel\Einvoicing;
use Illuminate\Console\Command;

use function Laravel\Prompts\table;

/**
 * Validate a document from the command line.
 *
 * It exits 1 on an invalid document, so it works in CI as it stands.
 */
final class ValidateCommand extends Command
{
    protected $signature = 'einvoicing:validate
                            {file : Path to a UBL 2.1 Invoice or CreditNote}
                            {--ruleset= : A ruleset id to pin to}
                            {--strict : Treat warnings as failures}';

    protected $description = 'Validate a Peppol document against the published rules';

    public function handle(Einvoicing $einvoicing): int
    {
        $path = $this->argument('file');

        if (! is_string($path) || ! is_file($path)) {
            $this->components->error('No such file: '.(is_string($path) ? $path : 'nothing given'));

            return self::FAILURE;
        }

        $document = (string) file_get_contents($path);
        $ruleset = $this->option('ruleset');

        $report = $einvoicing->validate($document, is_string($ruleset) ? $ruleset : null);

        $findings = $this->option('strict') === true ? $report->findings : $report->errors();

        if ($findings === []) {
            $this->components->info(sprintf(
                'Valid against %s.',
                is_string($report->ruleset['id'] ?? null) ? $report->ruleset['id'] : 'the current ruleset',
            ));

            return $report->valid ? self::SUCCESS : self::FAILURE;
        }

        table(
            headers: ['Rule', 'Layer', 'Severity', 'Message'],
            rows: array_map(static fn ($finding): array => [
                $finding->ruleId,
                $finding->layer,
                $finding->severity,
                $finding->message,
            ], $findings),
        );

        foreach ($findings as $finding) {
            if ($finding->fix !== null) {
                $this->components->bulletList(["{$finding->ruleId}: {$finding->fix}"]);
            }
        }

        $this->newLine();
        $this->components->error(sprintf('%d finding(s).', count($findings)));

        return self::FAILURE;
    }
}
