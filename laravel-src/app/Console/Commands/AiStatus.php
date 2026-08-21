<?php

namespace App\Console\Commands;

use App\AI\Services\CircuitBreaker;
use App\AI\Services\ProviderRegistry;
use App\AI\Services\RuntimeModeResolver;
use Illuminate\Console\Command;

class AiStatus extends Command
{
    protected $signature = 'ai:status';

    protected $description = 'Show effective AI runtime and provider status without exposing secrets';

    public function handle(ProviderRegistry $registry, CircuitBreaker $circuit, RuntimeModeResolver $modes): int
    {
        $this->info('Runtime mode: '.$modes->resolve());
        $this->table(['Provider', 'Enabled', 'Configured', 'Cost class', 'Model', 'Text health'], collect($registry->all())->map(fn (array $provider): array => [
            $provider['key'], $provider['enabled'] ? 'yes' : 'no', $provider['configured'] ? 'yes' : 'no', $provider['cost_class'],
            $provider['model'] ?: 'not set', $circuit->status($provider['key'], 'text_generation'),
        ])->all());

        return self::SUCCESS;
    }
}
