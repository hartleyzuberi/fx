<?php

namespace App\Services\Assessment;

use Illuminate\Support\Str;

class RubricGrader
{
    /** @param array<string, mixed> $key @return array<string, mixed> */
    public function grade(string $answer, array $key): array
    {
        $normalized = $this->normalize($answer);
        $contradictions = collect($key['contradictions'] ?? [])->filter(fn (string $phrase): bool => str_contains($normalized, $this->normalize($phrase)))->values()->all();
        if ($contradictions !== []) {
            return $this->result('misconception', 0, [], [], $contradictions, 'Your answer contains a reversed or structurally incorrect claim. Revisit the quote or market-structure explanation before retrying.');
        }

        if (($key['kind'] ?? null) === 'manual_review') {
            return $this->result('partially_correct', 0, [], ['manual practical review'], [], 'This gate response was saved, but gate evidence must be reviewed against the practical rubric before it can pass.');
        }

        if (($key['kind'] ?? null) === 'reference_answer') {
            return $this->gradeAgainstReference($normalized, (string) ($key['reference'] ?? ''));
        }

        if (($key['kind'] ?? null) === 'set') {
            $found = collect($key['accepted'] ?? [])->filter(fn (string $item): bool => str_contains($normalized, $this->normalize($item)))->values()->all();
            $minimum = max(1, (int) ($key['minimum'] ?? count($key['accepted'] ?? [])));
            $score = min(100, (int) round((count($found) / $minimum) * 100));
            $missing = array_values(array_diff($key['accepted'] ?? [], $found));

            return $this->result($score === 100 ? 'correct' : ($score >= 50 ? 'partially_correct' : 'incorrect'), $score, $found, $missing, [], $score === 100 ? 'You supplied enough distinct examples.' : 'You identified '.count($found)." distinct instrument(s); the question requires {$minimum}.");
        }

        $groups = $key['required'] ?? [];
        $matched = [];
        $missing = [];
        foreach ($groups as $index => $alternatives) {
            $match = collect($alternatives)->first(fn (string $phrase): bool => str_contains($normalized, $this->normalize($phrase)));
            if ($match !== null) {
                $matched[] = $match;
            } else {
                $missing[] = 'criterion '.($index + 1);
            }
        }
        $minimum = (int) ($key['minimum_groups'] ?? count($groups));
        $score = count($matched) >= $minimum ? 100 : (int) round((count($matched) / max(1, $minimum)) * 100);
        $status = $score === 100 ? 'correct' : ($score >= 50 ? 'partially_correct' : 'incorrect');

        return $this->result($status, $score, $matched, $missing, [], $status === 'correct' ? 'Your explanation contains the required relationships.' : 'Your answer has part of the idea, but one or more required relationships are missing.');
    }

    /** @return array<string, mixed> */
    private function result(string $status, int $score, array $correct, array $missing, array $misconceptions, string $feedback): array
    {
        return ['status' => $status, 'mastery_score' => $score, 'correct_concepts' => $correct, 'missing_concepts' => $missing, 'misconceptions' => $misconceptions, 'feedback' => $feedback, 'follow_up_question' => null, 'requires_remediation' => $score < 100];
    }

    private function normalize(string $value): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', (string) preg_replace('/[^\pL\pN.]+/u', ' ', Str::lower($value))));
    }

    /** @return array<string, mixed> */
    private function gradeAgainstReference(string $answer, string $reference): array
    {
        $stopWords = ['about', 'after', 'again', 'also', 'because', 'before', 'being', 'between', 'could', 'does', 'from', 'have', 'into', 'more', 'must', 'only', 'other', 'should', 'than', 'that', 'their', 'there', 'these', 'they', 'this', 'through', 'under', 'when', 'where', 'which', 'while', 'with', 'would'];
        $tokens = fn (string $value): array => array_values(array_unique(array_filter(
            preg_split('/\s+/u', $this->normalize($value)) ?: [],
            fn (string $word): bool => (mb_strlen($word) >= 4 || preg_match('/\d/', $word)) && ! in_array($word, $stopWords, true),
        )));
        $expected = $tokens($reference);
        $supplied = $tokens($answer);
        $matched = array_values(array_intersect($expected, $supplied));
        $minimum = max(1, min(4, (int) ceil(count($expected) * 0.4)));
        $score = min(100, (int) round((count($matched) / $minimum) * 100));
        $status = $score === 100 ? 'correct' : ($score >= 50 ? 'partially_correct' : 'incorrect');

        return $this->result(
            $status,
            $score,
            $matched,
            $status === 'correct' ? [] : ['one or more defining ideas from the protected reference'],
            [],
            $status === 'correct'
                ? 'Your answer contains the defining source concepts. Semantic AI review, when enabled, can recognize a wider range of valid paraphrases.'
                : 'Your answer does not yet contain enough of the defining source concepts. Revisit the lesson and answer in your own words.',
        );
    }
}
