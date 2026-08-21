<?php

namespace App\Console\Commands;

use App\Services\Curriculum\SourceIngestionService;
use Illuminate\Console\Command;

class IngestCourseSources extends Command
{
    protected $signature = 'course:ingest {--path= : Source-audit artifact directory} {--force : Replace an existing import}';

    protected $description = 'Import the audited forex source documents and curriculum provenance';

    public function handle(SourceIngestionService $ingestion): int
    {
        $path = (string) ($this->option('path') ?: config('course.audit_path'));
        $result = $ingestion->ingest($path, (bool) $this->option('force'));
        $this->components->info('Source ingestion completed.');
        $this->table(['Metric', 'Count'], collect($result)->map(fn ($value, $key): array => [$key, $value])->values()->all());

        return self::SUCCESS;
    }
}
