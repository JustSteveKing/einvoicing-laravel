<?php

declare(strict_types=1);

namespace Einvoicing\Laravel\Commands;

use Einvoicing\Laravel\Einvoicing;
use Einvoicing\Responses\Capability;
use Illuminate\Console\Command;

use function Laravel\Prompts\table;

/** Ask the network whether a business can receive, and what. */
final class ParticipantCommand extends Command
{
    protected $signature = 'einvoicing:participant
                            {id : A scheme and value, such as 9932:GB123456789}
                            {--fresh : Skip the cache}';

    protected $description = 'Look a Peppol participant up';

    public function handle(Einvoicing $einvoicing): int
    {
        $id = $this->argument('id');

        if (! is_string($id)) {
            $this->components->error('Give an identifier, such as 9932:GB123456789.');

            return self::FAILURE;
        }

        $participant = $einvoicing->participant($id, fresh: $this->option('fresh') === true);

        if (! $participant->registered) {
            $this->components->warn("{$id} is not registered on the network.");

            return self::FAILURE;
        }

        $this->components->info("{$participant->id} is registered.");

        if ($participant->capabilities === []) {
            $this->components->warn('It accepts no document types.');

            return self::SUCCESS;
        }

        table(
            headers: ['Accepts', 'Document type'],
            rows: array_map(
                static fn (Capability $capability): array => [$capability->name, $capability->documentTypeId],
                $participant->capabilities,
            ),
        );

        $this->components->bulletList(["Checked at {$participant->checkedAt}"]);

        return self::SUCCESS;
    }
}
