<?php

namespace App\Console\Commands;

use App\Jobs\AuditResourceFreshness;
use Illuminate\Console\Command;

class QueueResourceFreshnessAudit extends Command
{
    protected $signature = 'resources:audit-freshness {--sync : Run immediately instead of using the queue}';

    protected $description = 'Flag external course resources whose source verification date is stale';

    public function handle(): int
    {
        if ($this->option('sync')) {
            AuditResourceFreshness::dispatchSync();
            $this->info('Resource freshness audit completed.');
        } else {
            AuditResourceFreshness::dispatch();
            $this->info('Resource freshness audit queued.');
        }

        return self::SUCCESS;
    }
}
