<?php

namespace App\Console\Commands;

use App\Services\Assessment\QuestionBankValidator;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

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
            $missing = DB::table('questions')
                ->join('assessments', 'assessments.id', '=', 'questions.assessment_id')
                ->where('assessments.assessment_type', 'chapter_quiz')
                ->where('questions.question_type', 'free_response')
                ->where(function ($query): void {
                    $query->whereNull('questions.structured_status')
                        ->orWhere('questions.structured_status', '!=', 'approved');
                })
                ->orderBy('assessments.title')
                ->orderBy('questions.position')
                ->get([
                    'assessments.title as assessment_title',
                    'questions.id',
                    'questions.position',
                    'questions.prompt',
                    'questions.answer_key',
                ]);

            if ($missing->isNotEmpty()) {
                $this->newLine();
                $this->warn('Missing deterministic alternatives:');
                foreach ($missing as $row) {
                    $key = json_decode((string) $row->answer_key, true);
                    $kind = is_array($key) ? (string) ($key['kind'] ?? 'unknown') : 'invalid';
                    $reference = is_array($key) ? trim((string) ($key['reference'] ?? '')) : '';
                    $suffix = $reference === '' ? 'reference=<empty>' : 'reference='.mb_strimwidth($reference, 0, 140, '…');
                    $this->line("- {$row->assessment_title} · Q{$row->position} · {$row->id} · kind={$kind} · {$suffix}");
                    $this->line('  '.mb_strimwidth((string) $row->prompt, 0, 220, '…'));
                }
            }

            $this->error('Question bank validation failed. Formal assessment integrity issues must be resolved before release.');

            return self::FAILURE;
        }

        $this->info('Question bank validation passed.');

        return self::SUCCESS;
    }
}
