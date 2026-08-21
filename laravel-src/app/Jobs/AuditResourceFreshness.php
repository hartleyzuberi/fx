<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;

class AuditResourceFreshness implements ShouldQueue
{
    use Queueable;

    public function handle(): void
    {
        DB::table('learning_resources')
            ->where('resource_type', 'external_url')
            ->whereNotNull('verified_on')
            ->whereDate('verified_on', '<=', now()->subDays(90)->toDateString())
            ->whereIn('review_status', ['unreviewed', 'source_verified'])
            ->update(['review_status' => 'freshness_due', 'updated_at' => now()]);
    }
}
