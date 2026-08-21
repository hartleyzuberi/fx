<?php

namespace App\Console\Commands;

use App\AI\Services\ProviderRegistry;
use Illuminate\Console\Command;

class AiTestProviders extends Command
{
    protected $signature = 'ai:test-providers {--provider= : Test one provider only}';

    protected $description = 'Run optional minimal live-inference smoke tests for configured providers';

    public function handle(ProviderRegistry $registry): int
    {
        $selected = $this->option('provider');
        $rows = [];
        foreach ($registry->all() as $descriptor) {
            if ($selected && $descriptor['key'] !== $selected) {
                continue;
            }
            if (! $descriptor['enabled'] || ! $descriptor['configured']) {
                $rows[] = [$descriptor['key'], $descriptor['enabled'] ? 'NOT CONFIGURED' : 'DISABLED', '-', $descriptor['model'] ?: '-'];

                continue;
            }
            $result = $registry->provider($descriptor['key'])->test((string) $descriptor['model']);
            $rows[] = [$descriptor['key'], $result['ok'] ? 'PASS' : 'FAIL', $result['latency_ms'].' ms', $result['model'] ?: '-'];
        }
        $this->table(['Provider', 'Result', 'Latency', 'Model'], $rows);

        return self::SUCCESS;
    }
}
