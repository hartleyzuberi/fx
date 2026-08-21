<?php

namespace App\Services\Tutor;

use App\AI\Services\SemanticSearchService;
use Illuminate\Support\Facades\DB;

class CourseRetriever
{
    public function __construct(private SemanticSearchService $semantic) {}

    /** @return list<array{id: string, text: string, document: string, page: int, heading: array<mixed>}> */
    public function retrieve(string $learningUnitId, string $query, int $limit = 6): array
    {
        $tokens = collect(preg_split('/[^\pL\pN]+/u', mb_strtolower($query)) ?: [])->filter(fn (string $token): bool => mb_strlen($token) >= 4)->unique()->values();

        $rows = array_values(DB::table('source_segments')
            ->join('content_mappings', 'content_mappings.source_segment_id', '=', 'source_segments.id')
            ->join('content_blocks', 'content_blocks.id', '=', 'content_mappings.content_block_id')
            ->join('source_pages', 'source_pages.id', '=', 'source_segments.source_page_id')
            ->join('source_versions', 'source_versions.id', '=', 'source_pages.source_version_id')
            ->join('source_documents', 'source_documents.id', '=', 'source_versions.source_document_id')
            ->where('content_blocks.learning_unit_id', $learningUnitId)
            ->whereIn('content_mappings.mapping_type', ['canonical', 'guided'])
            ->limit(250)
            ->get(['source_segments.id', 'source_segments.content', 'source_segments.heading_hierarchy', 'source_documents.title as document', 'source_pages.physical_page as page'])
            ->map(function ($row) use ($tokens): array {
                $text = mb_strtolower($row->content);
                $score = $tokens->sum(fn (string $token): int => substr_count($text, $token));

                $heading = json_decode($row->heading_hierarchy ?? '[]', true);

                return ['id' => (string) $row->id, 'text' => (string) $row->content, 'document' => (string) $row->document, 'page' => (int) $row->page, 'heading' => is_array($heading) ? $heading : [], 'lexical_score' => $score];
            })->values()->all());
        $semantic = $this->semantic->scores($query, $rows);
        $maximumLexical = max(1, (int) collect($rows)->max('lexical_score'));

        return array_values(collect($rows)->map(function (array $item) use ($semantic, $maximumLexical): array {
            $item['score'] = (($item['lexical_score'] / $maximumLexical) * 0.65) + (($semantic[$item['id']] ?? 0) * 0.35);

            return $item;
        })
            ->sortByDesc('score')->take($limit)->values()
            ->map(fn (array $item): array => ['id' => (string) $item['id'], 'text' => (string) $item['text'], 'document' => (string) $item['document'], 'page' => (int) $item['page'], 'heading' => $item['heading']])->all());
    }

    /** @return list<array<string, mixed>> */
    public function search(string $query, int $limit = 10): array
    {
        $tokens = collect(preg_split('/[^\pL\pN]+/u', mb_strtolower($query)) ?: [])->filter(fn (string $token): bool => mb_strlen($token) >= 3)->unique()->values();
        if ($tokens->isEmpty()) {
            return [];
        }
        $rows = array_values(DB::table('source_segments')
            ->join('content_mappings', 'content_mappings.source_segment_id', '=', 'source_segments.id')
            ->join('content_blocks', 'content_blocks.id', '=', 'content_mappings.content_block_id')
            ->join('learning_units', 'learning_units.id', '=', 'content_blocks.learning_unit_id')
            ->join('source_pages', 'source_pages.id', '=', 'source_segments.source_page_id')
            ->join('source_versions', 'source_versions.id', '=', 'source_pages.source_version_id')
            ->join('source_documents', 'source_documents.id', '=', 'source_versions.source_document_id')
            ->whereIn('content_mappings.mapping_type', ['canonical', 'guided'])
            ->limit(1500)
            ->get(['source_segments.id', 'source_segments.content', 'learning_units.slug', 'learning_units.title as unit_title', 'learning_units.position', 'source_documents.title as document', 'source_pages.physical_page as page'])
            ->map(function ($row) use ($tokens): array {
                $text = mb_strtolower($row->content);
                $lexical = $tokens->sum(fn (string $token): int => substr_count($text, $token));

                return ['id' => (string) $row->id, 'text' => (string) $row->content, 'slug' => (string) $row->slug, 'unit_title' => (string) $row->unit_title, 'position' => (int) $row->position, 'document' => (string) $row->document, 'page' => (int) $row->page, 'lexical_score' => $lexical];
            })->filter(fn (array $row): bool => $row['lexical_score'] > 0)->values()->all());
        $semantic = $this->semantic->scores($query, $rows);
        $maxLexical = max(1, (int) collect($rows)->max('lexical_score'));

        return array_values(collect($rows)->map(function (array $row) use ($semantic, $maxLexical): array {
            $row['relevance'] = (int) round(min(1, (($row['lexical_score'] / $maxLexical) * 0.7) + (($semantic[$row['id']] ?? 0) * 0.3)) * 100);
            $row['snippet'] = mb_strlen($row['text']) > 260 ? mb_substr($row['text'], 0, 257).'…' : $row['text'];

            return collect($row)->except(['text', 'lexical_score'])->all();
        })->sortByDesc('relevance')->unique('slug')->take($limit)->values()->all());
    }
}
