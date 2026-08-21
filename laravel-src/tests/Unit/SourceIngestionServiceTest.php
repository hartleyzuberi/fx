<?php

namespace Tests\Unit;

use App\Services\Curriculum\SourceIngestionService;
use PHPUnit\Framework\TestCase;

class SourceIngestionServiceTest extends TestCase
{
    public function test_it_segments_numbered_sections_without_losing_their_paragraphs(): void
    {
        $segments = (new SourceIngestionService)->segmentText("1.1 Learning objectives\nExplain a quote.\n1.2 First principle\nA currency is relative.\n32");

        $this->assertCount(2, $segments);
        $this->assertSame('section', $segments[0]['content_type']);
        $this->assertStringContainsString('Explain a quote.', $segments[0]['content']);
        $this->assertStringContainsString('A currency is relative.', $segments[1]['content']);
        $this->assertStringNotContainsString("\n32", $segments[1]['content']);
    }

    public function test_it_recognizes_notebook_and_recall_blocks(): void
    {
        $segments = (new SourceIngestionService)->segmentText("NOTE THIS\nQuotes are relative.\nTeach It Back\nExplain the quote from memory.");

        $this->assertSame(['note_this', 'recall'], array_column($segments, 'content_type'));
    }
}
