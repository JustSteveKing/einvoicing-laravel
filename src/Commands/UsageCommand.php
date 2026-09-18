<?php

declare(strict_types=1);

namespace Einvoicing\Laravel\Commands;

use Einvoicing\Laravel\Einvoicing;
use Einvoicing\Responses\Meter;
use Illuminate\Console\Command;

use function Laravel\Prompts\table;

/** Where the account stands this billing period. */
final class UsageCommand extends Command
{
    protected $signature = 'einvoicing:usage';

    protected $description = 'Show this period\'s usage';

    public function handle(Einvoicing $einvoicing): int
    {
        $usage = $einvoicing->usage();

        $this->components->info("Plan: {$usage->plan} ({$usage->periodStart} to {$usage->periodEnd})");

        table(
            headers: ['', 'Included', 'Used', 'Remaining', 'Overage'],
            rows: [
                $this->row('Documents', $usage->documents),
                $this->row('Lookups', $usage->lookups),
            ],
        );

        return self::SUCCESS;
    }

    /** @return array<int, string> */
    private function row(string $label, Meter $meter): array
    {
        return [
            $label,
            (string) $meter->included,
            sprintf('%d (%d%%)', $meter->used, (int) round($meter->fraction() * 100)),
            (string) $meter->remaining(),
            (string) $meter->overage,
        ];
    }
}
