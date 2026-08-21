<?php

namespace App\Console\Commands;

use App\Services\Assessment\QuestionBankValidator;
use Illuminate\Console\Command;

class ValidateStructuredQuestionBank extends Command
{
    protected $signature = 'course:validate-question-bank';

    protected $description = 'Validate deterministic assessment coverage, protected answer configuration, formal gate structure, and canonical evidence blueprints.';

    public function handle(QuestionBankValidator $validator): int
    {
        $report = $validator->validate();

        $this->table(
            ['Metric', 'Count'],
            collect($report['metrics'])->map(fn (int $count, string $metric): array => [$metric, $count])->values()->all(),
        );

        foreach ($report['warnings'] as $warning) {
            $this->warn($warning);
        }
        foreach ($report['errors'] as $error) {
            $this->error($error);
        }

        if ($report['errors'] !== []) {
            $this->error('Question bank validation failed. Formal assessment integrity issues must be resolved before release.');

            return self::FAILURE;
        }

        $this->info('Question bank validation passed.');

        return self::SUCCESS;
    }
}
