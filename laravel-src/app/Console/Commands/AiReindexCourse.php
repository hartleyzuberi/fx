<?php

namespace App\Console\Commands;

use App\AI\Exceptions\ProviderException;
use App\AI\Services\EmbeddingIndexService;
use Illuminate\Console\Command;

class AiReindexCourse extends Command
{
    protected $signature = 'ai:reindex-course';

    protected $description = 'Incrementally embed changed course source segments';

    public function handle(EmbeddingIndexService $indexer): int
    {
        try {
            $result = $indexer->reindex(fn (int $indexed, int $unchanged, int $failed) => $this->line("Indexed {$indexed}; unchanged {$unchanged}; failed {$failed}"));
            $this->info("Index {$result['state']}: {$result['indexed']} changed, {$result['unchanged']} unchanged, {$result['failed']} failed.");

            return $result['failed'] === 0 ? self::SUCCESS : self::FAILURE;
        } catch (ProviderException $exception) {
            $this->warn($exception->getMessage());

            return self::FAILURE;
        }
    }
}
