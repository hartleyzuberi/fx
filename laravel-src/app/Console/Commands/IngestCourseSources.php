<?php

namespace App\Console\Commands;

use App\Services\Assessment\GateEvidenceBlueprintService;
use App\Services\Assessment\StructuredQuestionBankBuilder;
use App\Services\Curriculum\SourceIngestionService;
use Illuminate\Console\Command;

class IngestCourseSources extends Command
{
    protected $signature = 'course:ingest {--path= : Source-audit artifact directory} {--force : Replace an existing import}';

    protected $description = 'Import the audited forex source documents, curriculum provenance, deterministic assessments, and machine-verifiable gate evidence blueprints';

    public function handle(SourceIngestionService $ingestion, StructuredQuestionBankBuilder $questionBank, GateEvidenceBlueprintService $evidence): int
    {
        $path = (string) ($this->option('path') ?: config('course.audit_path'));
        $result = $ingestion->ingest($path, (bool) $this->option('force'));
        $bank = $questionBank->build();
        $evidenceResult = $evidence->ensure();

        $this->components->info('Source ingestion completed.');
        $this->table(['Metric', 'Count'], collect($result)->map(fn ($value, $key): array => [$key, $value])->values()->all());
        $this->components->info('Deterministic structured assessment and gate-evidence blueprints built.');
        $this->table(['Metric', 'Count'], collect([...$bank, ...$evidenceResult])->map(fn ($value, $key): array => [$key, $value])->values()->all());

        return self::SUCCESS;
    }
}
