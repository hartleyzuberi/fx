<?php

namespace App\Console\Commands;

use App\Services\Assessment\StructuredQuestionBankBuilder;
use Illuminate\Console\Command;

class BuildStructuredQuestionBank extends Command
{
    protected $signature = 'course:build-question-bank {--no-gates : Build chapter variants only}';

    protected $description = 'Build approved deterministic structured variants and self-study gate/final question sets from canonical course assessments.';

    public function handle(StructuredQuestionBankBuilder $builder): int
    {
        $result = $builder->build(! $this->option('no-gates'));

        $this->table(['Metric', 'Count'], collect($result)->map(fn (int $count, string $metric): array => [$metric, $count])->values()->all());
        $this->info('Structured question bank build completed. No learner answers or historical attempts were modified.');

        return self::SUCCESS;
    }
}
