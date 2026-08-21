<?php

namespace Tests\Unit;

use App\Services\Assessment\RubricGrader;
use PHPUnit\Framework\TestCase;

class RubricGraderTest extends TestCase
{
    private array $quoteKey = [
        'kind' => 'propositions',
        'required' => [['one euro', 'a euro', 'euro'], ['1.17 us dollars', '1.17 dollars', '$1.17']],
        'contradictions' => ['one dollar buys 1.17 euros'],
    ];

    public function test_it_accepts_a_natural_paraphrase(): void
    {
        $result = (new RubricGrader)->grade('A euro is worth 1.17 dollars at that quoted moment.', $this->quoteKey);

        $this->assertSame('correct', $result['status']);
        $this->assertSame(100, $result['mastery_score']);
    }

    public function test_it_rejects_a_keyword_rich_reversal_as_a_misconception(): void
    {
        $result = (new RubricGrader)->grade('EUR/USD uses euro and dollars, and means one dollar buys 1.17 euros.', $this->quoteKey);

        $this->assertSame('misconception', $result['status']);
        $this->assertSame(0, $result['mastery_score']);
        $this->assertTrue($result['requires_remediation']);
    }
}
