<?php

namespace Tests\Unit;

use App\Services\Curriculum\StructuredSourceParser;
use PHPUnit\Framework\TestCase;

class StructuredSourceParserTest extends TestCase
{
    public function test_it_preserves_the_audited_structured_source_inventory(): void
    {
        $source = (new StructuredSourceParser)->parse(dirname(__DIR__, 3).'/tmp/pdfs/source-audit');

        $this->assertCount(90, $source['quizzes']);
        $this->assertSame(540, array_sum(array_map(fn (array $quiz): int => count($quiz['questions']), $source['quizzes'])));
        $this->assertSame(540, array_sum(array_map(fn (array $quiz): int => count($quiz['answers']), $source['quizzes'])));
        $this->assertCount(379, $source['exercises']);
        $this->assertCount(7, $source['gates']);
        $this->assertSame(142, array_sum(array_map(fn (array $gate): int => count($gate['questions']), $source['gates'])));
        $this->assertCount(26, $source['appendices']);
        $this->assertCount(12, $source['books']);
        $this->assertCount(4, $source['papers']);
        $this->assertCount(63, $source['urls']);
        $this->assertCount(100, $source['final_exam']);
    }

    public function test_every_quiz_has_six_questions_and_six_protected_answers(): void
    {
        $source = (new StructuredSourceParser)->parse(dirname(__DIR__, 3).'/tmp/pdfs/source-audit');

        foreach ($source['quizzes'] as $number => $quiz) {
            $this->assertSame($number, $quiz['chapter']);
            $this->assertCount(6, $quiz['questions']);
            $this->assertCount(6, $quiz['answers']);
        }
    }
}
