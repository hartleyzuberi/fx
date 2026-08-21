<?php

namespace App\Services\Assessment;

use App\Models\Question;

class StructuredQuestionGrader
{
    /** @return array<string, mixed> */
    public function grade(Question $question, mixed $answer, string $questionType, array $answerKey): array
    {
        return match ($questionType) {
            'single_choice', 'scenario_choice', 'misconception_choice', 'true_false' => $this->gradeSingleChoice($answer, $answerKey),
            'multiple_select' => $this->gradeMultipleSelect($answer, $answerKey),
            'numeric', 'calculation' => $this->gradeNumeric($answer, $answerKey),
            'matching', 'classification' => $this->gradeMap($answer, $answerKey),
            'ordering' => $this->gradeOrdering($answer, $answerKey),
            'fill_blank' => $this->gradeFillBlank($answer, $answerKey),
            default => throw new \InvalidArgumentException("Unsupported structured question type [{$questionType}]."),
        };
    }

    /** @return array<string, mixed> */
    private function gradeSingleChoice(mixed $answer, array $key): array
    {
        $selected = is_bool($answer) ? ($answer ? 'true' : 'false') : trim((string) $answer);
        $correct = trim((string) ($key['correct'] ?? $key['value'] ?? ''));
        $isCorrect = $selected !== '' && hash_equals($correct, $selected);
        $misconception = $key['misconceptions'][$selected] ?? null;

        return $this->result(
            $isCorrect ? 'correct' : ($misconception ? 'misconception' : 'incorrect'),
            $isCorrect ? 100 : 0,
            $isCorrect ? [$selected] : [],
            $isCorrect ? [] : ['correct option'],
            $misconception ? [(string) $misconception] : [],
            $isCorrect ? 'Correct.' : ($misconception ? 'That choice reflects a known misconception. Review the linked concept before retrying.' : 'That choice is not correct. Review the linked concept before retrying.'),
        );
    }

    /** @return array<string, mixed> */
    private function gradeMultipleSelect(mixed $answer, array $key): array
    {
        $selected = $this->stringList($answer);
        $correct = $this->stringList($key['correct'] ?? []);
        sort($selected);
        sort($correct);

        $missing = array_values(array_diff($correct, $selected));
        $extra = array_values(array_diff($selected, $correct));
        $matched = array_values(array_intersect($selected, $correct));
        $isCorrect = $missing === [] && $extra === [] && $correct !== [];
        $score = $isCorrect ? 100 : (int) round((count($matched) / max(1, count($correct) + count($extra))) * 100);
        $score = max(0, min(100, $score));
        $status = $isCorrect ? 'correct' : ($score >= 50 ? 'partially_correct' : 'incorrect');

        return $this->result(
            $status,
            $score,
            $matched,
            array_merge(array_map(fn (string $id): string => "missing:{$id}", $missing), array_map(fn (string $id): string => "extra:{$id}", $extra)),
            $this->selectedMisconceptions($selected, $key),
            $isCorrect ? 'Correct.' : 'Your selection is incomplete or includes an option that should not be selected.',
        );
    }

    /** @return array<string, mixed> */
    private function gradeNumeric(mixed $answer, array $key): array
    {
        if (! is_numeric($answer)) {
            return $this->result('incorrect', 0, [], ['numeric answer'], [], 'Enter a numeric answer.');
        }

        $actual = (float) $answer;
        $expected = (float) ($key['value'] ?? $key['expected'] ?? 0);
        $tolerance = max(0.0, (float) ($key['tolerance'] ?? 0));
        $isCorrect = abs($actual - $expected) <= $tolerance;
        $unit = isset($key['unit']) ? ' '.trim((string) $key['unit']) : '';

        return $this->result(
            $isCorrect ? 'correct' : 'incorrect',
            $isCorrect ? 100 : 0,
            $isCorrect ? [(string) $actual] : [],
            $isCorrect ? [] : ["expected numeric result{$unit}"],
            [],
            $isCorrect ? 'Correct.' : 'Recheck the calculation and units before retrying.',
        );
    }

    /** @return array<string, mixed> */
    private function gradeMap(mixed $answer, array $key): array
    {
        $submitted = is_array($answer) ? $answer : [];
        $expected = is_array($key['pairs'] ?? null) ? $key['pairs'] : (is_array($key['correct'] ?? null) ? $key['correct'] : []);
        $matched = [];
        $missing = [];

        foreach ($expected as $item => $expectedValue) {
            $actual = isset($submitted[$item]) ? (string) $submitted[$item] : null;
            if ($actual !== null && hash_equals((string) $expectedValue, $actual)) {
                $matched[] = (string) $item;
            } else {
                $missing[] = (string) $item;
            }
        }

        $score = (int) round((count($matched) / max(1, count($expected))) * 100);
        $status = $score === 100 ? 'correct' : ($score >= 50 ? 'partially_correct' : 'incorrect');

        return $this->result($status, $score, $matched, $missing, [], $status === 'correct' ? 'Correct.' : 'One or more items are matched or classified incorrectly.');
    }

    /** @return array<string, mixed> */
    private function gradeOrdering(mixed $answer, array $key): array
    {
        $expected = $this->stringList($key['order'] ?? $key['correct'] ?? []);
        $submitted = [];

        if (is_array($answer)) {
            if (array_is_list($answer)) {
                $submitted = $this->stringList($answer);
            } else {
                $ranked = [];
                foreach ($answer as $item => $rank) {
                    if (is_numeric($rank)) {
                        $ranked[(int) $rank] = (string) $item;
                    }
                }
                ksort($ranked);
                $submitted = array_values($ranked);
            }
        }

        $correctPositions = 0;
        foreach ($expected as $index => $item) {
            if (($submitted[$index] ?? null) === $item) {
                $correctPositions++;
            }
        }
        $score = (int) round(($correctPositions / max(1, count($expected))) * 100);
        $status = $score === 100 ? 'correct' : ($score >= 50 ? 'partially_correct' : 'incorrect');

        return $this->result($status, $score, $status === 'correct' ? $expected : [], $status === 'correct' ? [] : ['correct sequence'], [], $status === 'correct' ? 'Correct.' : 'The sequence is not yet correct.');
    }

    /** @return array<string, mixed> */
    private function gradeFillBlank(mixed $answer, array $key): array
    {
        $normalized = $this->normalize((string) $answer);
        $accepted = array_map(fn (mixed $value): string => $this->normalize((string) $value), $key['accepted'] ?? []);
        $isCorrect = $normalized !== '' && in_array($normalized, $accepted, true);

        return $this->result($isCorrect ? 'correct' : 'incorrect', $isCorrect ? 100 : 0, $isCorrect ? [$normalized] : [], $isCorrect ? [] : ['accepted answer'], [], $isCorrect ? 'Correct.' : 'That answer does not match the accepted course answer.');
    }

    /** @return list<string> */
    private function stringList(mixed $value): array
    {
        if (! is_array($value)) {
            $value = $value === null || $value === '' ? [] : [$value];
        }

        return array_values(array_unique(array_map(fn (mixed $item): string => trim((string) $item), array_filter($value, fn (mixed $item): bool => $item !== null && $item !== ''))));
    }

    /** @return list<string> */
    private function selectedMisconceptions(array $selected, array $key): array
    {
        $map = is_array($key['misconceptions'] ?? null) ? $key['misconceptions'] : [];

        return array_values(array_unique(array_filter(array_map(fn (string $id): ?string => isset($map[$id]) ? (string) $map[$id] : null, $selected))));
    }

    private function normalize(string $value): string
    {
        return trim(mb_strtolower((string) preg_replace('/\s+/u', ' ', $value)));
    }

    /** @return array<string, mixed> */
    private function result(string $status, int $score, array $correct, array $missing, array $misconceptions, string $feedback): array
    {
        return [
            'status' => $status,
            'mastery_score' => max(0, min(100, $score)),
            'correct_concepts' => $correct,
            'missing_concepts' => $missing,
            'misconceptions' => $misconceptions,
            'feedback' => $feedback,
            'follow_up_question' => null,
            'requires_remediation' => $score < 100,
        ];
    }
}
