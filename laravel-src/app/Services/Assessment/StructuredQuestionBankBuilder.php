<?php

namespace App\Services\Assessment;

use App\Models\Assessment;
use App\Models\Question;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class StructuredQuestionBankBuilder
{
    /** @return array<string, int> */
    public function build(bool $includeGates = true): array
    {
        $chapter = $this->buildChapterVariants();
        $gate = $includeGates ? $this->buildGateAndFinalQuestions() : ['created' => 0, 'reflections' => 0];

        return [
            'chapter_variants_approved' => $chapter['approved'],
            'chapter_variants_skipped' => $chapter['skipped'],
            'gate_questions_created' => $gate['created'],
            'manual_questions_preserved_as_reflection' => $gate['reflections'],
        ];
    }

    /** @return array{approved: int, skipped: int} */
    private function buildChapterVariants(): array
    {
        $approved = 0;
        $skipped = 0;
        $assessments = Assessment::query()
            ->with('questions')
            ->where('assessment_type', 'chapter_quiz')
            ->get();

        foreach ($assessments as $assessment) {
            /** @var Collection<int, Question> $questions */
            $questions = $assessment->questions;
            foreach ($questions as $question) {
                if ($question->question_type !== 'free_response') {
                    continue;
                }
                if ($question->hasApprovedStructuredVariant()) {
                    $approved++;

                    continue;
                }

                $variant = $this->chapterOneVariant($question)
                    ?? $this->referenceAnswerVariant($question, $questions);

                if ($variant === null) {
                    $skipped++;

                    continue;
                }

                $metadata = $question->metadata ?? [];
                $metadata['structured_derivation'] = $variant['derivation'];
                $metadata['structured_source_question_id'] = $question->id;

                $question->forceFill([
                    'structured_type' => $variant['type'],
                    'structured_choices' => $variant['choices'],
                    'structured_answer_key' => $variant['answer_key'],
                    'structured_status' => 'approved',
                    'metadata' => $metadata,
                ])->save();
                $approved++;
            }
        }

        return compact('approved', 'skipped');
    }

    /**
     * @param Collection<int, Question> $siblings
     * @return array<string, mixed>|null
     */
    private function referenceAnswerVariant(Question $question, Collection $siblings): ?array
    {
        $key = $question->answer_key ?? [];
        if (($key['kind'] ?? null) !== 'reference_answer') {
            return null;
        }

        $correct = $this->cleanOption((string) ($key['reference'] ?? ''));
        if ($correct === '') {
            return null;
        }

        $candidates = $siblings
            ->reject(fn (Question $candidate): bool => $candidate->id === $question->id)
            ->map(function (Question $candidate): string {
                $candidateKey = $candidate->answer_key ?? [];

                return ($candidateKey['kind'] ?? null) === 'reference_answer'
                    ? $this->cleanOption((string) ($candidateKey['reference'] ?? ''))
                    : '';
            })
            ->filter(fn (string $text): bool => $text !== '' && $this->normalize($text) !== $this->normalize($correct))
            ->unique(fn (string $text): string => $this->normalize($text))
            ->values();

        $strictDistractors = $candidates
            ->filter(fn (string $text): bool => $this->similarity($correct, $text) < 0.86)
            ->take(3)
            ->values();

        $distractors = $strictDistractors->count() >= 3
            ? $strictDistractors
            : $candidates
                ->sortBy(fn (string $text): float => $this->similarity($correct, $text))
                ->take(3)
                ->values();

        if ($distractors->count() < 3) {
            return null;
        }

        $texts = collect([$correct, ...$distractors->all()])
            ->sortBy(fn (string $text): string => hash('sha256', $question->id.'|'.$text))
            ->values();
        $choices = $texts->map(fn (string $text): array => ['id' => $this->optionId($text), 'text' => $text])->all();

        return [
            'type' => 'single_choice',
            'choices' => $choices,
            'answer_key' => ['correct' => $this->optionId($correct)],
            'derivation' => [
                'method' => 'source_reference_with_sibling_source_distractors',
                'canonical_reference' => true,
                'auto_validation' => $strictDistractors->count() >= 3
                    ? 'unique_options_and_similarity_guard'
                    : 'unique_options_three_least_similar_source_distractors',
            ],
        ];
    }

    /** @return array<string, mixed>|null */
    private function chapterOneVariant(Question $question): ?array
    {
        $prompt = $this->normalize($question->prompt);

        if (str_contains($prompt, 'eur usd 1.1700')) {
            return $this->singleChoice(
                $question,
                [
                    ['One U.S. dollar is worth 1.1700 euros.', 'reversed_base_quote'],
                    ['One euro is worth 1.1700 U.S. dollars.', null],
                    ['EUR and USD have the same value.', 'absolute_currency_value'],
                    ['The quote describes only the euro and says nothing about the dollar.', 'quote_is_not_relative'],
                ],
                'One euro is worth 1.1700 U.S. dollars.',
                'hand_authored_from_chapter_1_rubric',
            );
        }

        if (str_contains($prompt, 'eur usd rises')) {
            return $this->singleChoice(
                $question,
                [
                    ['The euro strengthened relative to the U.S. dollar.', null],
                    ['The U.S. dollar strengthened relative to the euro.', 'reversed_direction'],
                    ['Both currencies strengthened by exactly the same amount.', 'absolute_currency_value'],
                    ['The movement says nothing about their relative value.', 'quote_is_not_relative'],
                ],
                'The euro strengthened relative to the U.S. dollar.',
                'hand_authored_from_chapter_1_rubric',
            );
        }

        if (str_contains($prompt, 'name four different fx instruments')) {
            return $this->singleChoice(
                $question,
                [
                    ['Spot FX, forwards, FX swaps and currency futures.', null],
                    ['Spot FX, a savings account, a debit card and an ATM withdrawal.', 'non_fx_instruments'],
                    ['Only spot FX; all other listed products are the same instrument.', 'instrument_conflation'],
                    ['A stock index, a commodity future, a bond and a money-market fund.', 'non_fx_instruments'],
                ],
                'Spot FX, forwards, FX swaps and currency futures.',
                'hand_authored_from_chapter_1_rubric',
            );
        }

        if (str_contains($prompt, 'one centralized exchange')) {
            return $this->singleChoice(
                $question,
                [
                    ['Yes. Every global spot FX trade occurs on one official exchange.', 'centralized_spot_fx'],
                    ['No. Spot FX is decentralized and trades across OTC venues and liquidity relationships.', null],
                    ['Yes. A retail broker is the global foreign-exchange market.', 'broker_equals_market'],
                    ['No, because currencies cannot be traded electronically.', 'market_structure_error'],
                ],
                'No. Spot FX is decentralized and trades across OTC venues and liquidity relationships.',
                'hand_authored_from_chapter_1_rubric',
            );
        }

        if (str_contains($prompt, 'corporations use fx forwards')) {
            return $this->singleChoice(
                $question,
                [
                    ['To lock or agree an exchange rate for a future currency flow and reduce uncertainty around an existing exposure.', null],
                    ['To guarantee a speculative profit regardless of future exchange rates.', 'hedging_equals_guaranteed_profit'],
                    ['Because a forward eliminates the underlying commercial payment.', 'hedge_eliminates_exposure'],
                    ['Only because spot FX is illegal for corporations.', 'market_structure_error'],
                ],
                'To lock or agree an exchange rate for a future currency flow and reduce uncertainty around an existing exposure.',
                'hand_authored_from_chapter_1_rubric',
            );
        }

        if (str_contains($prompt, 'care whether their product is otc or exchange traded')) {
            return $this->singleChoice(
                $question,
                [
                    ['Because product structure affects counterparty, execution, pricing, clearing, costs, regulation and protections.', null],
                    ['It does not matter; OTC and exchange-traded products are legally and operationally identical.', 'otc_equals_exchange'],
                    ['Only the chart colour changes.', 'platform_equals_market_structure'],
                    ['Because every OTC product is automatically fraudulent.', 'otc_equals_fraud'],
                ],
                'Because product structure affects counterparty, execution, pricing, clearing, costs, regulation and protections.',
                'hand_authored_from_chapter_1_rubric',
            );
        }

        return null;
    }

    /**
     * @param list<array{0: string, 1: string|null}> $options
     * @return array<string, mixed>
     */
    private function singleChoice(Question $question, array $options, string $correctText, string $method): array
    {
        $sorted = collect($options)
            ->sortBy(fn (array $option): string => hash('sha256', $question->id.'|'.$option[0]))
            ->values();
        $choices = $sorted->map(fn (array $option): array => ['id' => $this->optionId($option[0]), 'text' => $option[0]])->all();
        $misconceptions = [];
        foreach ($sorted as $option) {
            if ($option[1]) {
                $misconceptions[$this->optionId($option[0])] = $option[1];
            }
        }

        return [
            'type' => 'single_choice',
            'choices' => $choices,
            'answer_key' => ['correct' => $this->optionId($correctText), 'misconceptions' => $misconceptions],
            'derivation' => ['method' => $method, 'canonical_reference' => true],
        ];
    }

    /** @return array{created: int, reflections: int} */
    private function buildGateAndFinalQuestions(): array
    {
        $created = 0;
        $reflections = 0;
        $gates = Assessment::query()
            ->with(['questions'])
            ->whereIn('assessment_type', ['gate_exam', 'final_exam'])
            ->get()
            ->sortBy(fn (Assessment $assessment): int => (int) DB::table('learning_units')->where('id', $assessment->learning_unit_id)->value('position'))
            ->values();

        $previousGateChapter = 0;
        foreach ($gates as $gate) {
            $chapter = (int) DB::table('learning_units')->where('id', $gate->learning_unit_id)->value('position');
            $isFinal = $gate->assessment_type === 'final_exam';
            $existingGenerated = $gate->questions->filter(fn (Question $question): bool => ($question->metadata['completion_role'] ?? null) === 'structured_gate');

            foreach ($gate->questions as $question) {
                $key = $question->answer_key ?? [];
                if (($key['kind'] ?? null) === 'manual_review' && $question->status !== 'reflection') {
                    $metadata = $question->metadata ?? [];
                    $metadata['completion_role'] = 'reflection';
                    $question->forceFill(['status' => 'reflection', 'metadata' => $metadata])->save();
                    $reflections++;
                }
            }

            if ($existingGenerated->isNotEmpty()) {
                $previousGateChapter = $isFinal ? $previousGateChapter : $chapter;

                continue;
            }

            $fromChapter = $isFinal ? 1 : $previousGateChapter + 1;
            $toChapter = $isFinal ? 90 : $chapter;
            $pool = $this->approvedChapterPool($fromChapter, $toChapter);
            $target = $isFinal ? min(100, $pool->count()) : min(30, $pool->count());
            $selected = $this->spreadSelection($pool, $target);
            $position = ((int) $gate->questions->max('position')) + 1;

            foreach ($selected as $sourceQuestion) {
                Question::query()->create([
                    'assessment_id' => $gate->id,
                    'concept_id' => $sourceQuestion->concept_id,
                    'learning_objective_id' => $sourceQuestion->learning_objective_id,
                    'rubric_id' => $sourceQuestion->rubric_id,
                    'source_segment_id' => $sourceQuestion->source_segment_id,
                    'question_type' => $sourceQuestion->structured_type,
                    'difficulty' => $sourceQuestion->difficulty ?? 'understanding',
                    'status' => 'approved',
                    'question_version' => '1',
                    'position' => $position++,
                    'prompt' => $sourceQuestion->prompt,
                    'choices' => $sourceQuestion->structured_choices,
                    'answer_key' => $sourceQuestion->structured_answer_key,
                    'explanation' => $sourceQuestion->explanation,
                    'points' => 1,
                    'requires_working' => false,
                    'metadata' => [
                        'completion_role' => 'structured_gate',
                        'source_question_id' => $sourceQuestion->id,
                        'source_assessment_id' => $sourceQuestion->assessment_id,
                        'source_chapter' => (int) DB::table('learning_units')->join('assessments', 'assessments.learning_unit_id', '=', 'learning_units.id')->where('assessments.id', $sourceQuestion->assessment_id)->value('learning_units.position'),
                        'deterministic_equivalent' => true,
                    ],
                ]);
                $created++;
            }

            $rules = $gate->rules ?? [];
            $rules['deterministic_self_study'] = true;
            $rules['structured_question_count'] = $selected->count();
            $rules['structured_chapter_range'] = [$fromChapter, $toChapter];
            $rules['original_reflection_questions_preserved'] = true;
            $rules['manual_practical_review'] = false;
            $gate->forceFill(['rules' => $rules])->save();

            if (! $isFinal) {
                $previousGateChapter = $chapter;
            }
        }

        return compact('created', 'reflections');
    }

    /** @return Collection<int, Question> */
    private function approvedChapterPool(int $fromChapter, int $toChapter): Collection
    {
        $assessmentIds = DB::table('assessments')
            ->join('learning_units', 'learning_units.id', '=', 'assessments.learning_unit_id')
            ->where('assessments.assessment_type', 'chapter_quiz')
            ->whereBetween('learning_units.position', [$fromChapter, $toChapter])
            ->orderBy('learning_units.position')
            ->pluck('assessments.id');

        return Question::query()
            ->whereIn('assessment_id', $assessmentIds)
            ->where('structured_status', 'approved')
            ->whereNotNull('structured_type')
            ->orderBy('assessment_id')
            ->orderBy('position')
            ->get();
    }

    /**
     * @param Collection<int, Question> $pool
     * @return Collection<int, Question>
     */
    private function spreadSelection(Collection $pool, int $target): Collection
    {
        if ($target <= 0 || $pool->isEmpty()) {
            return $pool->take(0)->values();
        }
        if ($pool->count() <= $target) {
            return $pool->values();
        }

        $step = $pool->count() / $target;
        /** @var Collection<int, Question> $selected */
        $selected = collect();
        $used = [];
        for ($index = 0; $index < $target; $index++) {
            $position = min($pool->count() - 1, (int) floor($index * $step));
            while (isset($used[$position]) && $position < $pool->count() - 1) {
                $position++;
            }
            if (isset($used[$position])) {
                continue;
            }
            $used[$position] = true;
            $candidate = $pool->values()->get($position);
            if ($candidate instanceof Question) {
                $selected->push($candidate);
            }
        }

        return $selected->values();
    }

    private function cleanOption(string $text): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', $text));
    }

    private function optionId(string $text): string
    {
        return 'opt_'.substr(hash('sha256', $this->normalize($text)), 0, 12);
    }

    private function normalize(string $text): string
    {
        return trim(mb_strtolower((string) preg_replace('/[^\pL\pN.%$€¥]+/u', ' ', $text)));
    }

    private function similarity(string $left, string $right): float
    {
        $a = $this->normalize($left);
        $b = $this->normalize($right);
        similar_text($a, $b, $percent);

        return $percent / 100;
    }
}
