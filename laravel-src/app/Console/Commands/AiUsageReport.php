<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class AiUsageReport extends Command
{
    protected $signature = 'ai:usage-report {--days=30}';

    protected $description = 'Summarize AI requests without displaying prompts or secrets';

    public function handle(): int
    {
        $since = now()->subDays(max(1, (int) $this->option('days')));
        $rows = DB::table('ai_usage_events')->where('occurred_at', '>=', $since)->select(['provider', 'task_type'])
            ->selectRaw('count(*) as requests, sum(case when status = \'failed\' then 1 else 0 end) as failures, sum(case when fallback_used = 1 then 1 else 0 end) as fallbacks, sum(input_tokens) as input_tokens, sum(output_tokens) as output_tokens, round(avg(latency_ms), 0) as avg_latency_ms, sum(estimated_cost_usd) as cost')
            ->groupBy('provider', 'task_type')->get()->map(fn ($row): array => (array) $row)->all();
        $this->table(['provider', 'task_type', 'requests', 'failures', 'fallbacks', 'input_tokens', 'output_tokens', 'avg_latency_ms', 'cost'], $rows);

        return self::SUCCESS;
    }
}
