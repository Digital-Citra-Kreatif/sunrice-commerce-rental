<?php

declare(strict_types=1);

namespace Sunrice\CommerceRental\Console;

use Illuminate\Console\Command;
use Sunrice\CommerceRental\Actions\ImportUnits;

/** rental:import-units units.csv [--dry-run] [--partial] */
class ImportUnitsCommand extends Command
{
    protected $signature = 'rental:import-units {file : CSV with code, sku, location[, condition, active, note]} {--dry-run : Check it and say what would change} {--partial : Import the good rows even when some are wrong}';

    protected $description = 'Create or update rental units from a CSV';

    public function handle(ImportUnits $import): int
    {
        $result = $import->handle((string) $this->argument('file'), (bool) $this->option('dry-run'), (bool) $this->option('partial'));
        foreach ($result['errors'] as $error) {
            $this->components->error($error);
        }
        $verb = $this->option('dry-run') ? 'Would create' : 'Created';
        $this->components->info("{$verb} {$result['created']}, ".($this->option('dry-run') ? 'would update' : 'updated')." {$result['updated']}.");

        return $result['errors'] !== [] && ! $this->option('partial') ? self::FAILURE : self::SUCCESS;
    }
}
