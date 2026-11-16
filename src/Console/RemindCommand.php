<?php

declare(strict_types=1);

namespace Sunrice\CommerceRental\Console;

use Illuminate\Console\Command;
use Sunrice\CommerceRental\Reminders;

/** rental:remind — scheduled every hour from 07:00 to 20:00: pickup and return reminders. */
class RemindCommand extends Command
{
    protected $signature = 'rental:remind';

    protected $description = 'Email customers before their rental pickup and return days';

    public function handle(Reminders $reminders): int
    {
        $this->components->info($reminders().' reminder(s) sent.');

        return self::SUCCESS;
    }
}
