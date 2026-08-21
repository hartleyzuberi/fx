<?php

namespace App\Console\Commands;

use App\AI\Data\AiRequest;
use App\AI\Prompts\PromptRegistry;
use App\AI\Services\ProviderRegistry;
use App\Services\Assessment\RubricGrader;
use Illuminate\Console\Command;

class AiEvaluateModels extends Command
{
    protected $signature = 'ai:evaluate-models {--provider=* : Configured providers to evaluate live}';

    protected $description = 'Run the course-specific grading benchmark; live providers are optional';

    public function handle(RubricGrader $grader, ProviderRegistry $registry, PromptRegistry $prompts): int
    {
        $cases = require base_path('tests/AI/evaluation/cases.php');
        $correct = 0;
        foreach ($cases as $case) {
            $correct += $grader->grade($case['answer'], $case['answer_key'])['status'] === $case['expected'] ? 1 : 0;
        }
        $this->info('Deterministic equivalent: '.$correct.'/'.count($cases).' expected classifications.');

        foreach ((array) $this->option('provider') as $providerKey) {
            $descriptor = $registry->descriptor((string) $providerKey);
            if (! $descriptor || ! $descriptor['enabled'] || ! $descriptor['configured']) {
                $this->warn("{$providerKey}: NOT CONFIGURED");

                continue;
            }
            $passed = $schemaFailures = 0;
            $prompt = $prompts->semanticGrader();
            foreach ($cases as $index => $case) {
                try {
                    $response = $registry->provider((string) $providerKey)->generate(new AiRequest(
                        'evaluation', 'structured_output', $prompt['prompt'],
                        "QUESTION\n{$case['question']}\n\nANSWER\n{$case['answer']}\n\nRUBRIC\n".json_encode($case['answer_key'], JSON_THROW_ON_ERROR),
                        $prompt['version'], $this->schema(), false, null, "evaluation-{$index}",
                    ), (string) $descriptor['model']);
                    $decoded = json_decode($response->text, true);
                    if (! is_array($decoded) || ! is_string($decoded['status'] ?? null)) {
                        $schemaFailures++;

                        continue;
                    }
                    $passed += $decoded['status'] === $case['expected'] ? 1 : 0;
                } catch (\Throwable) {
                    $schemaFailures++;
                }
            }
            $this->line("{$providerKey}/{$descriptor['model']}: {$passed}/".count($cases)." expected; {$schemaFailures} schema/request failures");
        }

        return self::SUCCESS;
    }

    /** @return array<string, mixed> */
    private function schema(): array
    {
        return ['type' => 'object', 'additionalProperties' => false, 'properties' => [
            'status' => ['type' => 'string', 'enum' => ['correct', 'partially_correct', 'incorrect', 'misconception', 'unable_to_assess']],
            'mastery_score' => ['type' => 'integer', 'minimum' => 0, 'maximum' => 100],
            'confidence' => ['type' => 'number', 'minimum' => 0, 'maximum' => 1],
        ], 'required' => ['status', 'mastery_score', 'confidence']];
    }
}
