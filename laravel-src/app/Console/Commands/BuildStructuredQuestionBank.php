<?php

namespace App\Console\Commands;

use App\Services\Assessment\GateEvidenceBlueprintService;
use App\Services\Assessment\StructuredQuestionBankBuilder;
use Illuminate\Console\Command;

class BuildStructuredQuestionBank extends Command
{
    protected $signature = 'course:build-question-bank {--no-gates : Build chapter variants only}';

    protected $description = 'Build approved deterministic structured variants, self-study gate/final questions, and canonical evidence blueprints.';

    public function handle(StructuredQuestionBankBuilder $builder, GateEvidenceBlueprintService $evidence): int
    {
        $includeGates = ! $this->option('no-gates');
        $result = $builder->build($includeGates);
        $evidenceResult = $includeGates ? $evidence->ensure() : [];

        $this->table(
            ['Metric', 'Count'],
            collect([...$result, ...$evidenceResult])->map(fn (int $count, string $metric): array => [$metric, $count])->values()->all(),
        );
        $this->info('Structured question bank build completed. No learner answers or historical attempts were modified.');

        return self::SUCCESS;
    }
}
