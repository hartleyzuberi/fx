<?php

namespace Tests\Unit;

use App\Models\Question;
use App\Services\Assessment\StructuredQuestionGrader;
use PHPUnit\Framework\TestCase;

class StructuredQuestionGraderTest extends TestCase
{
    public function test_single_choice_marks_correct_and_tracks_misconception(): void
    {
        $grader = new StructuredQuestionGrader;
        $question = new Question;

        $correct = $grader->grade($question, 'b', 'single_choice', [
            'correct' => 'b',
            'misconceptions' => ['a' => 'reversed_base_quote'],
        ]);
        $wrong = $grader->grade($question, 'a', 'single_choice', [
            'correct' => 'b',
            'misconceptions' => ['a' => 'reversed_base_quote'],
        ]);

        $this->assertSame('correct', $correct['status']);
        $this->assertSame(100, $correct['mastery_score']);
        $this->assertSame('misconception', $wrong['status']);
        $this->assertSame(['reversed_base_quote'], $wrong['misconceptions']);
    }

    public function test_numeric_grading_respects_tolerance(): void
    {
        $grader = new StructuredQuestionGrader;
        $question = new Question;

        $correct = $grader->grade($question, '22.501', 'numeric', [
            'value' => 22.50,
            'tolerance' => 0.01,
            'unit' => 'USD',
        ]);
        $wrong = $grader->grade($question, '22.60', 'numeric', [
            'value' => 22.50,
            'tolerance' => 0.01,
            'unit' => 'USD',
        ]);

        $this->assertSame('correct', $correct['status']);
        $this->assertSame('incorrect', $wrong['status']);
    }

    public function test_multiple_select_requires_the_correct_set(): void
    {
        $grader = new StructuredQuestionGrader;
        $question = new Question;

        $result = $grader->grade($question, ['spot', 'forward'], 'multiple_select', [
            'correct' => ['spot', 'forward'],
        ]);

        $this->assertSame('correct', $result['status']);
        $this->assertSame(100, $result['mastery_score']);
    }

    public function test_matching_and_ordering_are_graded_deterministically(): void
    {
        $grader = new StructuredQuestionGrader;
        $question = new Question;

        $matching = $grader->grade($question, ['base' => 'eur', 'quote' => 'usd'], 'matching', [
            'pairs' => ['base' => 'eur', 'quote' => 'usd'],
        ]);
        $ordering = $grader->grade($question, ['observe' => 1, 'calculate' => 2, 'record' => 3], 'ordering', [
            'order' => ['observe', 'calculate', 'record'],
        ]);

        $this->assertSame('correct', $matching['status']);
        $this->assertSame('correct', $ordering['status']);
    }
}
