<?php

namespace App\Console\Commands\Hr;

use App\Services\Hr\HrReminderService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class SendHrRemindersCommand extends Command
{
    protected $signature = 'hr:reminders
                            {--type=all : all, contracts, or documents}
                            {--date= : run as though today were this Gregorian date, for testing}';

    protected $description = 'Notify HR of contracts due for renewal and employee documents about to expire.';

    public function handle(HrReminderService $reminders): int
    {
        $asOf = $this->option('date') ? Carbon::parse($this->option('date')) : Carbon::today();
        $type = (string) $this->option('type');

        if (! in_array($type, ['all', 'contracts', 'documents'], true)) {
            $this->error('--type must be one of: all, contracts, documents');

            return self::FAILURE;
        }

        if ($type === 'all' || $type === 'contracts') {
            $count = $reminders->runContractExpiryCheck($asOf);
            $this->info("Contract renewal reminders sent: {$count}");
        }

        if ($type === 'all' || $type === 'documents') {
            $count = $reminders->runDocumentExpiryCheck($asOf);
            $this->info("Document expiry reminders sent: {$count}");
        }

        return self::SUCCESS;
    }
}
