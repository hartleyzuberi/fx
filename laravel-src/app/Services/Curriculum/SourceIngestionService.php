<?php

namespace App\Services\Curriculum;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class SourceIngestionService
{
    /** @var array<string, array<int, array<int, string>>> */
    private array $segmentIds = [];

    /** @var array<string, string> */
    private array $canonicalBlocks = [];

    /** @var array<int, int> guided page => canonical page */
    private array $guidedDuplicates = [];

    /** @return array<string, int> */
    public function ingest(string $auditPath, bool $replace = false): array
    {
        $this->assertArtifactsExist($auditPath);
        $manifest = $this->json("{$auditPath}/manifest.json");
        $structured = (new StructuredSourceParser)->parse($auditPath);
        $this->guidedDuplicates = collect($this->json("{$auditPath}/canonical_to_guided_page_map.json"))
            ->mapWithKeys(fn (array $pair): array => [(int) $pair['guided_physical_page'] => (int) $pair['canonical_physical_page']])
            ->all();

        return DB::transaction(function () use ($auditPath, $manifest, $replace, $structured): array {
            if (DB::table('courses')->where('slug', 'complete-forex-curriculum')->exists() && ! $replace) {
                throw new RuntimeException('The course is already imported. Pass --force to replace it.');
            }
            if ($replace) {
                DB::table('courses')->where('slug', 'complete-forex-curriculum')->delete();
                DB::table('source_documents')->whereIn('key', ['complete_course', 'tutor_led'])->delete();
                DB::table('source_conflicts')->where('description', 'like', 'SRC-%')->delete();
            }

            $courseId = $this->createCourse();
            $versionId = $this->createCurriculumVersion($courseId);
            $units = $this->createCurriculumUnits($auditPath, $versionId, $structured['appendices']);
            $pageCount = 0;
            foreach ($manifest['documents'] as $document) {
                $pageCount += $this->importDocument($auditPath, $document, $units);
            }
            $duplicates = $this->linkDuplicatePages();
            $this->createStructuredLearningContent($versionId, $units, $structured);
            $this->createResourceLibrary($versionId, $units, $structured);
            $this->createKnownSourceConflicts();
            DB::table('curriculum_versions')->where('id', $versionId)->update([
                'status' => 'published', 'published_at' => now(), 'updated_at' => now(),
            ]);

            return [
                'documents' => count($manifest['documents']),
                'pages' => $pageCount,
                'segments' => DB::table('source_segments')->count(),
                'content_blocks' => DB::table('content_blocks')->count(),
                'mappings' => DB::table('content_mappings')->count(),
                'duplicate_relations' => $duplicates,
                'learning_units' => DB::table('learning_units')->where('curriculum_version_id', $versionId)->count(),
                'assessments' => DB::table('assessments')->count(),
                'questions' => DB::table('questions')->count(),
                'practice_assignments' => DB::table('practice_assignments')->count(),
                'learning_resources' => DB::table('learning_resources')->where('curriculum_version_id', $versionId)->count(),
                'source_conflicts' => DB::table('source_conflicts')->count(),
            ];
        });
    }

    /** @return list<array{content_type: string, content: string}> */
    public function segmentText(string $text): array
    {
        $lines = preg_split('/\R/u', trim($text)) ?: [];
        $segments = [];
        $current = [];
        $currentType = 'paragraph';
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || preg_match('/^\d{1,4}$/', $line)) {
                continue;
            }
            $type = $this->lineType($line);
            if ($type !== null && $current !== []) {
                $segments[] = ['content_type' => $currentType, 'content' => implode("\n", $current)];
                $current = [];
            }
            if ($type !== null) {
                $currentType = $type;
            }
            $current[] = $line;
        }
        if ($current !== []) {
            $segments[] = ['content_type' => $currentType, 'content' => implode("\n", $current)];
        }

        return $segments;
    }

    private function lineType(string $line): ?string
    {
        return match (true) {
            (bool) preg_match('/^(?:Chapter\s+\d+|[IVX]+\s+Part\s+[IVX]+|Appendix\s+[A-Z])\b/i', $line) => 'heading',
            (bool) preg_match('/^\d+\.\d+(?:\.\d+)?\s+/', $line) => 'section',
            (bool) preg_match('/^(?:Learning objectives?|Objectives?)\b/i', $line) => 'learning_objective',
            (bool) preg_match('/^(?:NOTE THIS|What to write|Write this)\b/i', $line) => 'note_this',
            (bool) preg_match('/^(?:Exercise|Practice|Assignment|Task)\b/i', $line) => 'exercise',
            (bool) preg_match('/^(?:Quiz|Question\s+\d+|Q\d+[.)])\b/i', $line) => 'question',
            (bool) preg_match('/^(?:Mastery Check|Teach It Back|Retention Test|Stop and Think)\b/i', $line) => 'recall',
            (bool) preg_match('/^(?:Warning|Caution|Do not|Never)\b/i', $line) => 'warning',
            (bool) preg_match('/^(?:Formula|Equation|Calculation)\b/i', $line) => 'formula',
            (bool) preg_match('/^(?:Resources?|Further reading|Companion reading)\b/i', $line) => 'resource',
            default => null,
        };
    }

    /** @return array<string, mixed> */
    private function json(string $path): array
    {
        $value = json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);

        return is_array($value) ? $value : [];
    }

    private function assertArtifactsExist(string $path): void
    {
        foreach (['manifest.json', 'complete_course_pages.jsonl', 'tutor_led_pages.jsonl', 'complete_course_outline.json', 'tutor_led_outline.json', 'canonical_to_guided_page_map.json'] as $file) {
            if (! is_file("{$path}/{$file}")) {
                throw new RuntimeException("Missing source-audit artifact: {$path}/{$file}");
            }
        }
    }

    private function createCourse(): string
    {
        $id = (string) Str::ulid();
        DB::table('courses')->insert([
            'id' => $id,
            'slug' => 'complete-forex-curriculum',
            'title' => 'Forex Mastery: From First Principles to Advanced Practice',
            'description' => 'A mastery-based guided curriculum grounded in the Complete Course and Tutor-Led Guided Study Edition.',
            'is_active' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return $id;
    }

    private function createCurriculumVersion(string $courseId): string
    {
        $id = (string) Str::ulid();
        DB::table('curriculum_versions')->insert([
            'id' => $id, 'course_id' => $courseId,
            'version_label' => 'source-2026-08-19-v1', 'status' => 'importing',
            'change_summary' => 'Initial deterministic import of both supplied source books.',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return $id;
    }

    /** @param list<array{letter: string, title: string, start: int, end: int}> $appendices @return array<string, mixed> */
    private function createCurriculumUnits(string $auditPath, string $versionId, array $appendices): array
    {
        $canonical = $this->json("{$auditPath}/complete_course_outline.json");
        $guided = $this->json("{$auditPath}/tutor_led_outline.json");
        $phases = [
            [1, 8, 'orientation-market-mechanics', 'Orientation and Market Mechanics'],
            [9, 17, 'chart-technical-literacy', 'Reading Price and Technical Analysis'],
            [18, 33, 'fundamental-macro-literacy', 'Fundamental and Macro Analysis'],
            [34, 42, 'risk-probability', 'Risk, Probability and Money Management'],
            [43, 58, 'strategy-evidence', 'Strategy Design and Evidence'],
            [59, 65, 'psychology-process', 'Psychology, Execution and Journaling'],
            [66, 75, 'demo-micro-live', 'Serious Demo and Micro-Live Progression'],
            [76, 90, 'advanced-graduation', 'Advanced FX Literacy and Graduation'],
        ];
        $phaseIds = [];
        foreach ($phases as $position => [$start, $end, $slug, $title]) {
            $phaseIds[$position] = $this->insertUnit($versionId, null, 'phase', $slug, $title, $position + 1, ['chapter_start' => $start, 'chapter_end' => $end]);
        }
        $reference = $this->insertUnit($versionId, null, 'reference', 'reference-library', 'Reference Library and Appendices', 99, []);
        $appendixIds = $appendixRanges = [];
        foreach ($appendices as $position => $appendix) {
            $letter = $appendix['letter'];
            $appendixRanges[$letter] = ['start' => $appendix['start'], 'end' => $appendix['end']];
            $appendixIds[$letter] = $this->insertUnit(
                $versionId,
                $reference,
                'appendix',
                'appendix-'.Str::lower($letter).'-'.Str::slug($appendix['title']),
                "Appendix {$letter}: {$appendix['title']}",
                $position + 1,
                ['appendix' => $letter, 'canonical_pages' => $appendixRanges[$letter]],
            );
        }
        $canonicalChapters = array_values(array_filter($canonical, fn (array $entry): bool => $entry['depth'] === 1 && preg_match('/^Chapter\s+\d+\s+-/', $entry['title']) === 1));
        $guidedTop = array_values(array_filter($guided, fn (array $entry): bool => $entry['depth'] === 0 && preg_match('/Chapter\s+\d+/', $entry['title']) === 1));
        $chapters = $canonicalRanges = $guidedRanges = [];

        foreach ($canonicalChapters as $index => $entry) {
            preg_match('/^Chapter\s+(\d+)\s+-\s+(.+)$/', $entry['title'], $matches);
            $number = (int) $matches[1];
            $title = trim($matches[2]);
            $canonicalRanges[$number] = ['start' => (int) $entry['physical_page'], 'end' => ((int) ($canonicalChapters[$index + 1]['physical_page'] ?? ($appendices[0]['start'] ?? 329))) - 1];
            $guideEntry = collect($guidedTop)->first(fn (array $candidate): bool => preg_match('/Chapter\s+'.preg_quote((string) $number, '/').'\b/', $candidate['title']) === 1);
            $guideIndex = $guideEntry ? array_search($guideEntry, $guidedTop, true) : false;
            $guidedRanges[$number] = ['start' => (int) ($guideEntry['physical_page'] ?? 1), 'end' => (int) (($guideIndex !== false && isset($guidedTop[$guideIndex + 1])) ? $guidedTop[$guideIndex + 1]['physical_page'] - 1 : 600)];
            $schedule = $guideEntry ? trim((string) preg_replace('/\s+-\s+Chapter\s+\d+.*$/', '', $guideEntry['title'])) : "Chapter {$number}";
            $phaseIndex = collect($phases)->search(fn (array $phase): bool => $number >= $phase[0] && $number <= $phase[1]);
            $chapters[$number] = $this->insertUnit($versionId, $phaseIds[$phaseIndex], 'session', 'chapter-'.$number.'-'.Str::slug($title), "Chapter {$number}: {$title}", $number, ['chapter' => $number, 'schedule' => $schedule, 'canonical_pages' => $canonicalRanges[$number], 'guided_pages' => $guidedRanges[$number]], $number <= 8);
        }

        return compact('chapters', 'canonicalRanges', 'guidedRanges', 'reference') + [
            'canonical_ranges' => $canonicalRanges,
            'guided_ranges' => $guidedRanges,
            'appendices' => $appendixIds,
            'appendix_ranges' => $appendixRanges,
        ];
    }

    /** @param array<string, mixed> $metadata */
    private function insertUnit(string $versionId, ?string $parentId, string $type, string $slug, string $title, int $position, array $metadata, bool $critical = false): string
    {
        $id = (string) Str::ulid();
        DB::table('learning_units')->insert([
            'id' => $id, 'curriculum_version_id' => $versionId, 'parent_id' => $parentId,
            'unit_type' => $type, 'slug' => $slug, 'title' => $title, 'summary' => null,
            'materialized_path' => $parentId ? "/{$parentId}/{$id}" : "/{$id}", 'position' => $position,
            'estimated_minutes' => $type === 'session' ? 90 : null, 'is_safety_critical' => $critical,
            'metadata' => json_encode($metadata, JSON_THROW_ON_ERROR), 'created_at' => now(), 'updated_at' => now(),
        ]);

        return $id;
    }

    /** @param array<string, mixed> $document @param array<string, mixed> $units */
    private function importDocument(string $auditPath, array $document, array $units): int
    {
        $documentId = (string) Str::ulid();
        DB::table('source_documents')->insert([
            'id' => $documentId, 'key' => $document['document_id'],
            'title' => $document['metadata']['Title'] ?? $document['filename'],
            'source_kind' => $document['document_id'] === 'complete_course' ? 'canonical_course' : 'guided_study',
            'is_canonical' => $document['document_id'] === 'complete_course',
            'description' => $document['document_id'] === 'complete_course' ? 'Canonical curriculum content.' : 'Canonical instructional scaffolding and guided-study experience.',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $versionId = (string) Str::ulid();
        DB::table('source_versions')->insert([
            'id' => $versionId, 'source_document_id' => $documentId,
            'version_label' => 'sha256-'.substr($document['file_sha256'], 0, 12), 'filename' => $document['filename'],
            'file_sha256' => $document['file_sha256'], 'file_size_bytes' => $document['file_size_bytes'],
            'physical_page_count' => $document['physical_pages'], 'metadata' => json_encode($document['metadata'], JSON_THROW_ON_ERROR),
            'imported_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);
        $outline = $this->json("{$auditPath}/{$document['document_id']}_outline.json");
        $stream = fopen("{$auditPath}/{$document['document_id']}_pages.jsonl", 'rb');
        if ($stream === false) {
            throw new RuntimeException('Unable to open page records.');
        }
        $count = 0;
        while (($line = fgets($stream)) !== false) {
            $record = json_decode($line, true, flags: JSON_THROW_ON_ERROR);
            $page = (int) $record['physical_page'];
            $pageId = (string) Str::ulid();
            DB::table('source_pages')->insert([
                'id' => $pageId, 'source_version_id' => $versionId, 'physical_page' => $page,
                'logical_page' => $record['logical_page'], 'source_order' => $page, 'text_sha256' => $record['text_sha256'],
                'character_count' => $record['character_count'], 'markers' => json_encode($record['markers'], JSON_THROW_ON_ERROR),
                'extracted_text' => $record['text'], 'created_at' => now(), 'updated_at' => now(),
            ]);
            foreach ($record['external_links'] as $url) {
                DB::table('source_links')->insert([
                    'id' => (string) Str::ulid(), 'source_page_id' => $pageId, 'url' => $url,
                    'resource_type' => 'source_annotation', 'review_status' => 'unreviewed', 'created_at' => now(), 'updated_at' => now(),
                ]);
            }
            $this->importPageSegments($document['document_id'], $pageId, $page, $record['text'], $outline, $units);
            $count++;
        }
        fclose($stream);

        return $count;
    }

    /** @param list<array<string, mixed>> $outline @param array<string, mixed> $units */
    private function importPageSegments(string $documentKey, string $pageId, int $page, string $text, array $outline, array $units): void
    {
        $hierarchy = $this->headingHierarchy($outline, $page);
        $unitId = $this->unitForPage($documentKey, $page, $units);
        foreach ($this->segmentText($text) as $order => $segment) {
            $segmentId = (string) Str::ulid();
            $this->segmentIds[$documentKey][$page][$order] = $segmentId;
            DB::table('source_segments')->insert([
                'id' => $segmentId, 'source_page_id' => $pageId, 'parent_id' => null, 'source_order' => $order + 1,
                'section_identifier' => preg_match('/^(\d+\.\d+(?:\.\d+)?)/', $segment['content'], $matches) ? $matches[1] : null,
                'content_type' => $segment['content_type'], 'heading_hierarchy' => json_encode($hierarchy, JSON_THROW_ON_ERROR),
                'content' => $segment['content'], 'content_sha256' => hash('sha256', $segment['content']), 'is_meaningful' => true,
                'metadata' => json_encode(['document' => $documentKey, 'physical_page' => $page], JSON_THROW_ON_ERROR),
                'created_at' => now(), 'updated_at' => now(),
            ]);
            if ($documentKey === 'tutor_led' && isset($this->guidedDuplicates[$page])) {
                continue;
            }
            $blockId = $this->createBlock($unitId, $segment, $documentKey, $page, $order);
            if ($documentKey === 'complete_course') {
                $this->canonicalBlocks["{$page}:{$order}"] = $blockId;
            }
            $this->createMapping($segmentId, $blockId, $documentKey === 'complete_course' ? 'canonical' : 'guided');
        }
    }

    /** @param array{content_type: string, content: string} $segment */
    private function createBlock(string $unitId, array $segment, string $documentKey, int $page, int $order): string
    {
        $id = (string) Str::ulid();
        $position = ((int) DB::table('content_blocks')->where('learning_unit_id', $unitId)->max('position')) + 1;
        DB::table('content_blocks')->insert([
            'id' => $id, 'learning_unit_id' => $unitId, 'block_type' => $segment['content_type'], 'position' => $position,
            'title' => in_array($segment['content_type'], ['heading', 'section'], true) ? Str::limit(strtok($segment['content'], "\n"), 250, '') : null,
            'body' => $segment['content'], 'payload' => json_encode(['source_document' => $documentKey, 'physical_page' => $page, 'source_order' => $order + 1], JSON_THROW_ON_ERROR),
            'is_required' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);

        return $id;
    }

    private function createMapping(string $segmentId, string $blockId, string $type): void
    {
        DB::table('content_mappings')->insert([
            'id' => (string) Str::ulid(), 'source_segment_id' => $segmentId, 'content_block_id' => $blockId,
            'mapping_type' => $type, 'review_status' => 'machine_mapped', 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function linkDuplicatePages(): int
    {
        $count = 0;
        foreach ($this->guidedDuplicates as $guidedPage => $canonicalPage) {
            foreach ($this->segmentIds['tutor_led'][$guidedPage] ?? [] as $order => $guidedId) {
                $canonicalId = $this->segmentIds['complete_course'][$canonicalPage][$order] ?? null;
                $blockId = $this->canonicalBlocks["{$canonicalPage}:{$order}"] ?? null;
                if ($canonicalId === null || $blockId === null) {
                    continue;
                }
                DB::table('source_relations')->insert([
                    'id' => (string) Str::ulid(), 'from_segment_id' => $guidedId, 'to_segment_id' => $canonicalId,
                    'relation_type' => 'duplicate_of', 'confidence' => 1,
                    'metadata' => json_encode(['comparison' => 'page text SHA-256 and deterministic segment order'], JSON_THROW_ON_ERROR),
                    'created_at' => now(), 'updated_at' => now(),
                ]);
                $this->createMapping($guidedId, $blockId, 'duplicate');
                $count++;
            }
        }

        return $count;
    }

    /** @param list<array<string, mixed>> $outline @return list<string> */
    private function headingHierarchy(array $outline, int $page): array
    {
        $active = [];
        foreach ($outline as $entry) {
            if ($entry['physical_page'] === null || $entry['physical_page'] > $page) {
                continue;
            }
            $depth = (int) $entry['depth'];
            $active = array_slice($active, 0, $depth);
            $active[$depth] = $entry['title'];
        }

        return array_values($active);
    }

    /** @param array<string, mixed> $units */
    private function unitForPage(string $documentKey, int $page, array $units): string
    {
        if ($documentKey === 'complete_course') {
            foreach ($units['appendix_ranges'] as $letter => $range) {
                if ($page >= $range['start'] && $page <= $range['end']) {
                    return $units['appendices'][$letter];
                }
            }
        }
        $ranges = $documentKey === 'complete_course' ? $units['canonical_ranges'] : $units['guided_ranges'];
        foreach ($ranges as $chapter => $range) {
            if ($page >= $range['start'] && $page <= $range['end']) {
                return $units['chapters'][$chapter];
            }
        }

        return $units['reference'];
    }

    /** @param array<string, mixed> $units @param array<string, mixed> $structured */
    private function createStructuredLearningContent(string $versionId, array $units, array $structured): void
    {
        // Chapter 1 has a hand-authored semantic rubric; every later quiz preserves the
        // source prompt and protected answer reference for deterministic/AI grading.
        $this->createChapterOneLearningStructure($versionId, $units['chapters']);

        foreach ($structured['quizzes'] as $number => $quiz) {
            if ($number === 1) {
                continue;
            }
            $assessmentId = (string) Str::ulid();
            DB::table('assessments')->insert([
                'id' => $assessmentId,
                'learning_unit_id' => $units['chapters'][$quiz['chapter']],
                'assessment_type' => 'chapter_quiz',
                'title' => "Chapter {$number} mastery check",
                'passing_score' => 85,
                'is_gate' => false,
                'answers_protected' => true,
                'rules' => json_encode(['closed_book' => true, 'requires_notebook_entry' => true, 'attempts_append_only' => true, 'source_quiz' => $number], JSON_THROW_ON_ERROR),
                'created_at' => now(), 'updated_at' => now(),
            ]);
            foreach ($quiz['questions'] as $position => $prompt) {
                DB::table('questions')->insert([
                    'id' => (string) Str::ulid(), 'assessment_id' => $assessmentId, 'concept_id' => null, 'rubric_id' => null,
                    'question_type' => 'free_response', 'position' => $position + 1, 'prompt' => $prompt,
                    'answer_key' => json_encode(['kind' => 'reference_answer', 'reference' => $quiz['answers'][$position]], JSON_THROW_ON_ERROR),
                    'explanation' => $quiz['answers'][$position], 'points' => 1, 'requires_working' => false,
                    'created_at' => now(), 'updated_at' => now(),
                ]);
            }
        }

        foreach ($structured['exercises'] as $position => $exercise) {
            if ($exercise['chapter'] === 1) {
                continue;
            }
            DB::table('practice_assignments')->insert([
                'id' => (string) Str::ulid(), 'learning_unit_id' => $units['chapters'][$exercise['chapter']],
                'assignment_type' => 'chapter_exercise', 'title' => Str::limit($exercise['title'], 250, ''),
                'instructions' => $exercise['instructions'],
                'requirements' => json_encode(['response_required' => true, 'source_page' => $exercise['source_page'], 'source_order' => $position + 1], JSON_THROW_ON_ERROR),
                'required_observations' => null, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        foreach ($structured['gates'] as $gate) {
            $assessmentId = (string) Str::ulid();
            $threshold = in_array($gate['letter'], ['A', 'B', 'C'], true) ? 85 : 90;
            DB::table('assessments')->insert([
                'id' => $assessmentId, 'learning_unit_id' => $units['chapters'][$gate['chapter']],
                'assessment_type' => 'gate_exam', 'title' => "Gate {$gate['letter']}: {$gate['title']}",
                'passing_score' => $threshold, 'is_gate' => true, 'answers_protected' => true,
                'rules' => json_encode(['manual_practical_review' => true, 'source_page' => $gate['source_page'], 'source_gate' => $gate['letter']], JSON_THROW_ON_ERROR),
                'created_at' => now(), 'updated_at' => now(),
            ]);
            foreach ($gate['questions'] as $position => $prompt) {
                DB::table('questions')->insert([
                    'id' => (string) Str::ulid(), 'assessment_id' => $assessmentId, 'concept_id' => null, 'rubric_id' => null,
                    'question_type' => 'free_response', 'position' => $position + 1, 'prompt' => $prompt,
                    'answer_key' => json_encode(['kind' => 'manual_review', 'gate' => $gate['letter']], JSON_THROW_ON_ERROR),
                    'explanation' => 'Gate answers require the Appendix Y framework and practical evidence review.',
                    'points' => 1, 'requires_working' => true, 'created_at' => now(), 'updated_at' => now(),
                ]);
            }
        }

        $finalAssessmentId = (string) Str::ulid();
        DB::table('assessments')->insert([
            'id' => $finalAssessmentId, 'learning_unit_id' => $units['chapters'][90],
            'assessment_type' => 'final_exam', 'title' => 'Final Comprehensive Examination',
            'passing_score' => 90, 'is_gate' => true, 'answers_protected' => true,
            'rules' => json_encode(['manual_practical_review' => true, 'source_pages' => ['start' => 253, 'end' => 256], 'practical_oral_defence' => true], JSON_THROW_ON_ERROR),
            'created_at' => now(), 'updated_at' => now(),
        ]);
        foreach ($structured['final_exam'] as $position => $prompt) {
            DB::table('questions')->insert([
                'id' => (string) Str::ulid(), 'assessment_id' => $finalAssessmentId, 'concept_id' => null, 'rubric_id' => null,
                'question_type' => 'free_response', 'position' => $position + 1, 'prompt' => $prompt,
                'answer_key' => json_encode(['kind' => 'manual_review', 'final_exam' => true], JSON_THROW_ON_ERROR),
                'explanation' => 'The final examination requires the course framework, evidence record, and practical oral defence.',
                'points' => 1, 'requires_working' => true, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        foreach (range(3, 90) as $chapter) {
            $previous = $chapter - 1;
            DB::table('unlock_rules')->insert([
                ['id' => (string) Str::ulid(), 'learning_unit_id' => $units['chapters'][$chapter], 'rule_type' => 'assessment_score', 'required_learning_unit_id' => $units['chapters'][$previous], 'operator' => '>=', 'threshold' => 85, 'configuration' => json_encode(['assessment_type' => 'chapter_quiz'], JSON_THROW_ON_ERROR), 'position' => 1, 'created_at' => now(), 'updated_at' => now()],
                ['id' => (string) Str::ulid(), 'learning_unit_id' => $units['chapters'][$chapter], 'rule_type' => 'notebook_evidence', 'required_learning_unit_id' => $units['chapters'][$previous], 'operator' => '>=', 'threshold' => 1, 'configuration' => json_encode(['notebook_type' => 'concept'], JSON_THROW_ON_ERROR), 'position' => 2, 'created_at' => now(), 'updated_at' => now()],
            ]);
        }
    }

    /** @param array<string, mixed> $units @param array<string, mixed> $structured */
    private function createResourceLibrary(string $versionId, array $units, array $structured): void
    {
        $sourcePages = DB::table('source_pages as pages')
            ->join('source_versions as versions', 'versions.id', '=', 'pages.source_version_id')
            ->join('source_documents as documents', 'documents.id', '=', 'versions.source_document_id')
            ->where('documents.key', 'complete_course')
            ->pluck('pages.id', 'pages.physical_page');

        foreach ($structured['urls'] as $resource) {
            $id = (string) Str::ulid();
            $host = parse_url($resource['url'], PHP_URL_HOST) ?: 'External source';
            DB::table('learning_resources')->insert([
                'id' => $id, 'curriculum_version_id' => $versionId, 'source_page_id' => $sourcePages[$resource['source_page']] ?? null,
                'resource_type' => 'external_url', 'title' => $host, 'url' => $resource['url'], 'creator' => null,
                'review_status' => 'unreviewed', 'verified_on' => '2026-08-19',
                'metadata' => json_encode(['imported_from_source_annotation' => true], JSON_THROW_ON_ERROR),
                'created_at' => now(), 'updated_at' => now(),
            ]);
            $this->linkLearningResource($id, $this->unitForPage('complete_course', $resource['source_page'], $units));
        }

        foreach ($structured['books'] as $resource) {
            $id = (string) Str::ulid();
            DB::table('learning_resources')->insert([
                'id' => $id, 'curriculum_version_id' => $versionId, 'source_page_id' => $sourcePages[$resource['source_page']] ?? null,
                'resource_type' => 'book', 'title' => $resource['title'], 'url' => null, 'creator' => null,
                'review_status' => 'source_verified', 'verified_on' => '2026-08-19',
                'metadata' => json_encode(['copyrighted_reference_only' => true], JSON_THROW_ON_ERROR),
                'created_at' => now(), 'updated_at' => now(),
            ]);
            $this->linkLearningResource($id, $units['appendices']['B']);
        }

        foreach ($structured['papers'] as $resource) {
            $id = (string) Str::ulid();
            DB::table('learning_resources')->insert([
                'id' => $id, 'curriculum_version_id' => $versionId, 'source_page_id' => $sourcePages[$resource['source_page']] ?? null,
                'resource_type' => 'academic_paper', 'title' => Str::limit($resource['title'], 250, ''), 'url' => $resource['url'], 'creator' => null,
                'review_status' => 'source_verified', 'verified_on' => '2026-08-19',
                'metadata' => json_encode(['advanced_reading' => true], JSON_THROW_ON_ERROR),
                'created_at' => now(), 'updated_at' => now(),
            ]);
            $this->linkLearningResource($id, $units['appendices']['D']);
        }
    }

    private function linkLearningResource(string $resourceId, string $unitId): void
    {
        DB::table('learning_resource_unit')->insert([
            'learning_resource_id' => $resourceId,
            'learning_unit_id' => $unitId,
            'relationship' => 'reference',
        ]);
    }

    private function createKnownSourceConflicts(): void
    {
        $records = [
            ['SRC-001: Guided Chapter 8 schedule label appears out of sequence. Canonical chapter order is preserved.', 'schedule_order', 'open', null],
            ['SRC-002: Automatic rendered chapter counters are offset from explicit chapter titles. Explicit titles are authoritative.', 'presentation_numbering', 'resolved', 'Importer identifies chapters and appendices from explicit titles.'],
            ['SRC-003: Some extracted threshold symbols lose the greater-than character. Plain-language and Appendix Y thresholds are authoritative.', 'threshold_rendering', 'resolved', 'Backend stores explicit >= thresholds corroborated by source prose.'],
            ['SRC-004: Some schedule windows overlap while curriculum prerequisites remain sequential.', 'parallel_schedule', 'resolved', 'Schedule metadata is separate from prerequisite and progression order.'],
            ['SRC-005: Micro-live readiness and research-only IT eligibility are distinct decisions.', 'eligibility_nuance', 'resolved', 'Readiness predicates remain separate from course completion.'],
            ['SRC-006: Regulation, tax, broker, platform and market-statistic references are time-sensitive.', 'freshness', 'open', null],
            ['SRC-007: Guided Chapter 90 concatenates a mastery prompt and final-exam introduction.', 'segmentation', 'resolved', 'The quiz and final 100-question assessment are distinct structured entities.'],
        ];
        foreach ($records as [$description, $type, $status, $resolution]) {
            DB::table('source_conflicts')->insert([
                'id' => (string) Str::ulid(), 'left_segment_id' => null, 'right_segment_id' => null,
                'conflict_type' => $type, 'description' => $description, 'status' => $status,
                'resolution' => $resolution, 'resolved_at' => $status === 'resolved' ? now() : null,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }

    /** @param array<int, string> $chapters */
    private function createChapterOneLearningStructure(string $versionId, array $chapters): void
    {
        $chapterOne = $chapters[1];
        $conceptDefinitions = [
            'relative-exchange-rate' => ['Relative exchange rate', 'A currency quote expresses the value of one currency in terms of another.', true],
            'economic-exchange-vs-speculation' => ['Economic exchange, hedging and speculation', 'Economic participants exchange or hedge existing needs; speculators deliberately accept currency risk.', true],
            'fx-instruments' => ['Principal FX instruments', 'Spot, forwards, swaps, futures and options serve different legal and economic purposes.', false],
            'otc-vs-exchange' => ['OTC versus exchange-traded FX', 'OTC dealer markets differ from centralized, cleared futures exchanges.', true],
            'market-vs-platform' => ['Market versus retail platform', 'A retail platform and its price feed are interfaces into a wider decentralized market.', true],
        ];
        $conceptIds = [];
        foreach ($conceptDefinitions as $slug => [$name, $definition, $critical]) {
            $id = (string) Str::ulid();
            $conceptIds[$slug] = $id;
            DB::table('concepts')->insert([
                'id' => $id, 'curriculum_version_id' => $versionId, 'slug' => $slug, 'name' => $name,
                'definition' => $definition, 'is_safety_critical' => $critical, 'created_at' => now(), 'updated_at' => now(),
            ]);
            DB::table('concept_learning_unit')->insert(['concept_id' => $id, 'learning_unit_id' => $chapterOne, 'relationship' => 'teaches', 'weight' => $critical ? 2 : 1]);
        }

        $objectiveId = (string) Str::ulid();
        DB::table('learning_objectives')->insert([
            'id' => $objectiveId, 'learning_unit_id' => $chapterOne, 'code' => 'CH1-LO1',
            'statement' => 'Explain what an exchange rate represents, distinguish exchange and hedging from speculation, identify the principal FX instruments, and distinguish the wider market from a retail platform.',
            'position' => 1, 'is_critical' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $rubricId = (string) Str::ulid();
        DB::table('rubrics')->insert([
            'id' => $rubricId, 'learning_objective_id' => $objectiveId, 'version_label' => '1',
            'criteria' => json_encode(['relative quote meaning', 'directional interpretation', 'instrument purposes', 'market structure', 'commercial hedging'], JSON_THROW_ON_ERROR),
            'misconceptions' => json_encode(['reversing base and quote currencies', 'treating forex as one centralized exchange', 'equating a retail broker with the global market'], JSON_THROW_ON_ERROR),
            'passing_score' => 85, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $assessmentId = (string) Str::ulid();
        DB::table('assessments')->insert([
            'id' => $assessmentId, 'learning_unit_id' => $chapterOne, 'assessment_type' => 'chapter_quiz',
            'title' => 'Chapter 1 mastery check', 'passing_score' => 85, 'is_gate' => true, 'answers_protected' => true,
            'rules' => json_encode(['closed_book' => true, 'requires_notebook_entry' => true, 'attempts_append_only' => true], JSON_THROW_ON_ERROR),
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $questions = [
            ['relative-exchange-rate', 'What does EUR/USD = 1.1700 mean?', ['kind' => 'propositions', 'required' => [['one euro', '1 eur', 'euro'], ['1.17 us dollars', '1.1700 us dollars', '$1.17', '1.17 dollars']], 'contradictions' => ['one dollar buys 1.17 euros', '1.17 euros for one dollar']], 'One euro is priced at 1.1700 U.S. dollars.'],
            ['relative-exchange-rate', 'If EUR/USD rises, which currency strengthened relative to the other?', ['kind' => 'propositions', 'required' => [['euro strengthened', 'eur strengthened', 'euro rose'], ['dollar weakened', 'usd weakened', 'relative to the dollar']], 'contradictions' => ['dollar strengthened against the euro', 'euro weakened against the dollar'], 'minimum_groups' => 1], 'The euro strengthened relative to the U.S. dollar; equivalently, the dollar weakened relative to the euro.'],
            ['fx-instruments', 'Name four different FX instruments.', ['kind' => 'set', 'accepted' => ['spot', 'forward', 'swap', 'future', 'option'], 'minimum' => 4], 'Examples are spot FX, outright forwards, FX swaps, currency futures and currency options.'],
            ['otc-vs-exchange', 'Is the global spot FX market one centralized exchange?', ['kind' => 'propositions', 'required' => [['no', 'not'], ['decentralized', 'over the counter', 'otc', 'many venues']], 'contradictions' => ['yes it is centralized', 'one official exchange']], 'No. Spot FX is decentralized and trades across OTC venues and liquidity relationships.'],
            ['economic-exchange-vs-speculation', 'Why do corporations use FX forwards?', ['kind' => 'propositions', 'required' => [['fix', 'lock', 'agree'], ['future exchange rate', 'future currency', 'future payment', 'currency exposure'], ['hedge', 'reduce uncertainty', 'manage risk']], 'minimum_groups' => 2], 'A forward can lock an exchange rate for a future currency flow, reducing uncertainty around an existing exposure.'],
            ['otc-vs-exchange', 'Why should a retail trader care whether their product is OTC or exchange-traded?', ['kind' => 'propositions', 'required' => [['legal', 'contract', 'counterparty', 'dealer'], ['execution', 'pricing', 'clearing', 'venue'], ['cost', 'protection', 'regulation', 'risk']], 'minimum_groups' => 2], 'The structure changes the legal product, counterparty, execution, pricing, clearing, costs and protections the trader receives.'],
        ];
        foreach ($questions as $position => [$concept, $prompt, $answerKey, $explanation]) {
            DB::table('questions')->insert([
                'id' => (string) Str::ulid(), 'assessment_id' => $assessmentId, 'concept_id' => $conceptIds[$concept], 'rubric_id' => $rubricId,
                'question_type' => 'free_response', 'position' => $position + 1, 'prompt' => $prompt,
                'answer_key' => json_encode($answerKey, JSON_THROW_ON_ERROR), 'explanation' => $explanation,
                'points' => 1, 'requires_working' => false, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        $exercises = [
            'Explain why EUR/USD is a relative price rather than an absolute price.',
            'Give one example each of hedging, speculation and commercial currency exchange.',
            'Create a two-column comparison of OTC retail forex/CFDs and exchange-traded FX futures.',
            'Use the BIS 2025 FX survey to record the shares of spot, forwards and FX swaps.',
            'Explain why two brokers may show slightly different intraday prices without either necessarily being fraudulent.',
        ];
        foreach ($exercises as $title) {
            DB::table('practice_assignments')->insert([
                'id' => (string) Str::ulid(), 'learning_unit_id' => $chapterOne, 'assignment_type' => 'chapter_exercise',
                'title' => $title, 'instructions' => $title, 'requirements' => json_encode(['response_required' => true], JSON_THROW_ON_ERROR),
                'required_observations' => null, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }
        DB::table('unlock_rules')->insert([
            ['id' => (string) Str::ulid(), 'learning_unit_id' => $chapters[2], 'rule_type' => 'assessment_score', 'required_learning_unit_id' => $chapterOne, 'operator' => '>=', 'threshold' => 85, 'configuration' => json_encode(['assessment_type' => 'chapter_quiz'], JSON_THROW_ON_ERROR), 'position' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['id' => (string) Str::ulid(), 'learning_unit_id' => $chapters[2], 'rule_type' => 'notebook_evidence', 'required_learning_unit_id' => $chapterOne, 'operator' => '>=', 'threshold' => 1, 'configuration' => json_encode(['notebook_type' => 'concept'], JSON_THROW_ON_ERROR), 'position' => 2, 'created_at' => now(), 'updated_at' => now()],
        ]);
    }
}
