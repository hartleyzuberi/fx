<?php

namespace App\Services\Curriculum;

use RuntimeException;

class StructuredSourceParser
{
    /**
     * @return array{
     *     quizzes: array<int, array{chapter: int, questions: list<string>, answers: list<string>}>,
     *     exercises: list<array{chapter: int, title: string, instructions: string, source_page: int}>,
     *     gates: list<array{letter: string, chapter: int, title: string, source_page: int, questions: list<string>}>,
     *     appendices: list<array{letter: string, title: string, start: int, end: int}>,
     *     books: list<array{title: string, source_page: int}>,
     *     papers: list<array{title: string, url: string, source_page: int}>,
     *     urls: list<array{url: string, source_page: int}>,
     *     final_exam: list<string>
     * }
     */
    public function parse(string $auditPath): array
    {
        $pages = $this->readPages("{$auditPath}/complete_course_pages.jsonl");
        $outline = $this->readJson("{$auditPath}/complete_course_outline.json");
        $chapterRanges = $this->chapterRanges($outline);
        $quizzes = $this->parseQuizzes($pages, $chapterRanges);
        $answers = $this->parseAnswers($pages);

        foreach ($quizzes as $number => &$quiz) {
            $quiz['answers'] = $answers[$number] ?? [];
        }
        unset($quiz);

        return [
            'quizzes' => $quizzes,
            'exercises' => $this->parseExercises($pages, $chapterRanges),
            'gates' => $this->parseGates($pages, $outline, $chapterRanges),
            'appendices' => $this->parseAppendices($outline),
            'books' => $this->parseBooks($outline),
            'papers' => $this->parsePapers($pages),
            'urls' => $this->parseUrls($pages),
            'final_exam' => $this->parseFinalExam($pages),
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private function readPages(string $path): array
    {
        $stream = fopen($path, 'rb');
        if ($stream === false) {
            throw new RuntimeException("Unable to open {$path}.");
        }
        $pages = [];
        while (($line = fgets($stream)) !== false) {
            $record = json_decode($line, true, flags: JSON_THROW_ON_ERROR);
            $pages[(int) $record['physical_page']] = $record;
        }
        fclose($stream);

        return $pages;
    }

    /** @return list<array<string, mixed>> */
    private function readJson(string $path): array
    {
        $value = json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);

        return is_array($value) ? $value : [];
    }

    /** @param list<array<string, mixed>> $outline @return array<int, array{start: int, end: int}> */
    private function chapterRanges(array $outline): array
    {
        $chapters = array_values(array_filter($outline, fn (array $entry): bool => (int) $entry['depth'] === 1 && preg_match('/^Chapter\s+\d+\s+-/', $entry['title']) === 1));
        $ranges = [];
        foreach ($chapters as $index => $entry) {
            preg_match('/^Chapter\s+(\d+)\s+-/', $entry['title'], $matches);
            $number = (int) $matches[1];
            $ranges[$number] = [
                'start' => (int) $entry['physical_page'],
                'end' => ((int) ($chapters[$index + 1]['physical_page'] ?? 258)) - 1,
            ];
        }

        return $ranges;
    }

    /** @param array<int, array<string, mixed>> $pages @param array<int, array{start: int, end: int}> $ranges @return array<int, array{chapter: int, questions: list<string>, answers: list<string>}> */
    private function parseQuizzes(array $pages, array $ranges): array
    {
        $quizzes = [];
        $active = null;
        foreach ($pages as $pageNumber => $page) {
            if ($pageNumber < 32 || $pageNumber > 257) {
                continue;
            }
            foreach (preg_split('/\R/u', (string) $page['text']) ?: [] as $rawLine) {
                $line = trim($rawLine);
                if (preg_match('/^\d+\.\d+(?:\.\d+)?\s+(?:Final advanced quiz\s+|Quiz\s+)(\d+)\s*$/iu', $line, $match)) {
                    $active = (int) $match[1];
                    $quizzes[$active] = ['chapter' => $this->chapterForPage($pageNumber, $ranges), 'questions' => [], 'answers' => []];

                    continue;
                }
                if (preg_match('/^\d+\.\d+(?:\.\d+)?\s+/', $line)) {
                    $active = null;

                    continue;
                }
                if ($active === null || count($quizzes[$active]['questions']) >= 6 || $this->isNoise($line)) {
                    continue;
                }
                if (preg_match('/^[1-6]\.\s+(.+)$/u', $line, $match)) {
                    $quizzes[$active]['questions'][] = trim($match[1]);
                } elseif ($quizzes[$active]['questions'] !== []) {
                    $last = array_key_last($quizzes[$active]['questions']);
                    $quizzes[$active]['questions'][$last] .= ' '.$line;
                }
            }
        }
        ksort($quizzes);

        return $quizzes;
    }

    /** @param array<int, array<string, mixed>> $pages @return array<int, list<string>> */
    private function parseAnswers(array $pages): array
    {
        $answers = [];
        $active = null;
        foreach ($pages as $pageNumber => $page) {
            if ($pageNumber < 300 || $pageNumber > 321) {
                continue;
            }
            foreach (preg_split('/\R/u', (string) $page['text']) ?: [] as $rawLine) {
                $line = trim($rawLine);
                if (preg_match('/^\d+\.\d+(?:\.\d+)?\s+Quiz\s+(\d+)\s*$/iu', $line, $match)) {
                    $active = (int) $match[1];
                    $answers[$active] = [];

                    continue;
                }
                if ($active === null || count($answers[$active]) >= 6 || $this->isNoise($line)) {
                    continue;
                }
                if (preg_match('/^[1-6]\.\s+(.+)$/u', $line, $match)) {
                    $answers[$active][] = trim($match[1]);
                } elseif ($answers[$active] !== []) {
                    $last = array_key_last($answers[$active]);
                    $answers[$active][$last] .= ' '.$line;
                }
            }
        }
        ksort($answers);

        return $answers;
    }

    /** @param array<int, array<string, mixed>> $pages @param array<int, array{start: int, end: int}> $ranges @return list<array{chapter: int, title: string, instructions: string, source_page: int}> */
    private function parseExercises(array $pages, array $ranges): array
    {
        $exercises = [];
        /** @var array{chapter: int, title: string, page: int, items: list<string>, prose: list<string>}|null $section */
        $section = null;
        $flush = function () use (&$section, &$exercises): void {
            if ($section === null) {
                return;
            }
            if ($section['items'] === [] && $section['prose'] !== [] && ! str_contains(mb_strtolower($section['title']), 'break-even')) {
                $section['items'][] = implode(' ', $section['prose']);
            }
            foreach ($section['items'] as $item) {
                $exercises[] = [
                    'chapter' => $section['chapter'],
                    'title' => mb_strtoupper(mb_substr($item, 0, 1)).mb_substr($item, 1),
                    'instructions' => $item,
                    'source_page' => $section['page'],
                ];
            }
            $section = null;
        };

        foreach ($pages as $pageNumber => $page) {
            if ($pageNumber < 32 || $pageNumber > 257) {
                continue;
            }
            foreach (preg_split('/\R/u', (string) $page['text']) ?: [] as $rawLine) {
                $line = trim($rawLine);
                if (preg_match('/^\d+\.\d+(?:\.\d+)?\s+(.+)$/u', $line, $heading)) {
                    $flush();
                    $title = trim($heading[1]);
                    if (preg_match('/\bExercise(?:s)?\b/iu', $title)) {
                        $section = ['chapter' => $this->chapterForPage($pageNumber, $ranges), 'title' => $title, 'page' => $pageNumber, 'items' => [], 'prose' => []];
                    }

                    continue;
                }
                if ($section === null || $this->isNoise($line)) {
                    continue;
                }
                if (preg_match('/^[1-9]\d*\.\s+(.+)$/u', $line, $item)) {
                    $section['items'][] = trim($item[1]);
                } elseif ($section['items'] !== []) {
                    $last = array_key_last($section['items']);
                    $section['items'][$last] .= ' '.$line;
                } else {
                    $section['prose'][] = $line;
                }
            }
        }
        $flush();

        return $exercises;
    }

    /** @param array<int, array<string, mixed>> $pages @param list<array<string, mixed>> $outline @param array<int, array{start: int, end: int}> $ranges @return list<array{letter: string, chapter: int, title: string, source_page: int, questions: list<string>}> */
    private function parseGates(array $pages, array $outline, array $ranges): array
    {
        $gates = array_values(array_map(function (array $entry) use ($ranges): array {
            preg_match('/Gate Exam\s+([A-G])\s+-\s+(.+)$/u', $entry['title'], $match);

            return ['letter' => $match[1], 'chapter' => $this->chapterForPage((int) $entry['physical_page'], $ranges), 'title' => trim($match[2]), 'source_page' => (int) $entry['physical_page'], 'questions' => []];
        }, array_filter($outline, fn (array $entry): bool => preg_match('/^Gate Exam\s+[A-G]\s+-/', $entry['title']) === 1)));

        $byLetter = array_column($gates, null, 'letter');
        $active = null;
        foreach ($pages as $pageNumber => $page) {
            if ($pageNumber < 32 || $pageNumber > 257) {
                continue;
            }
            foreach (preg_split('/\R/u', (string) $page['text']) ?: [] as $rawLine) {
                $line = trim($rawLine);
                if (preg_match('/^\d+\.\d+(?:\.\d+)?\s+Gate Exam\s+([A-G])\s+-/u', $line, $match)) {
                    $active = $match[1];

                    continue;
                }
                if (preg_match('/^\d+\.\d+(?:\.\d+)?\s+/u', $line) || preg_match('/^(?:Part\s+[IVX]+|Chapter\s+\d+\s+-)/iu', $line)) {
                    $active = null;

                    continue;
                }
                if ($active === null || $this->isNoise($line)) {
                    continue;
                }
                if (preg_match('/^\d+\.\s+(.+)$/u', $line, $question)) {
                    $byLetter[$active]['questions'][] = trim($question[1]);
                } elseif ($byLetter[$active]['questions'] !== []) {
                    $last = array_key_last($byLetter[$active]['questions']);
                    $byLetter[$active]['questions'][$last] .= ' '.$line;
                }
            }
        }

        return array_values($byLetter);
    }

    /** @param list<array<string, mixed>> $outline @return list<array{letter: string, title: string, start: int, end: int}> */
    private function parseAppendices(array $outline): array
    {
        $entries = array_values(array_filter($outline, fn (array $entry): bool => (int) $entry['depth'] === 1 && preg_match('/^Appendix\s+[A-Z]\s+-/', $entry['title']) === 1));

        return array_map(function (array $entry, int $index) use ($entries): array {
            preg_match('/^Appendix\s+([A-Z])\s+-\s+(.+)$/u', $entry['title'], $match);

            return ['letter' => $match[1], 'title' => trim($match[2]), 'start' => (int) $entry['physical_page'], 'end' => ((int) ($entries[$index + 1]['physical_page'] ?? 329)) - 1];
        }, $entries, array_keys($entries));
    }

    /** @param list<array<string, mixed>> $outline @return list<array{title: string, source_page: int}> */
    private function parseBooks(array $outline): array
    {
        $books = array_filter($outline, fn (array $entry): bool => (int) $entry['depth'] === 2 && preg_match('/^(?:Core Book|Advanced Book|Decision Book|Optional later book|Deliberately postponed)\b/iu', $entry['title']) === 1);

        return array_values(array_map(fn (array $entry): array => ['title' => trim((string) preg_replace('/^(?:Core Book\s+\d+|Advanced Book\s+\d+|Decision Book\s+\d+|Optional later book|Deliberately postponed)\s+-\s+/iu', '', $entry['title'])), 'source_page' => (int) $entry['physical_page']], $books));
    }

    /** @param array<int, array<string, mixed>> $pages @return list<array{title: string, url: string, source_page: int}> */
    private function parsePapers(array $pages): array
    {
        $text = (string) ($pages[269]['text'] ?? '');
        preg_match_all('/^[1-4]\.\s+(.+?)(?=^\d+\.|^\d+\.\d+\s+|\z)/msu', $text, $matches);
        $links = array_values(array_unique($pages[269]['external_links'] ?? []));

        return array_map(fn (string $item, int $index): array => [
            'title' => trim((string) preg_replace('/\s*https?:\/\/.*$/su', '', preg_replace('/\s+/u', ' ', $item))),
            'url' => $links[$index] ?? '',
            'source_page' => 269,
        ], $matches[1], array_keys($matches[1]));
    }

    /** @param array<int, array<string, mixed>> $pages @return list<array{url: string, source_page: int}> */
    private function parseUrls(array $pages): array
    {
        $urls = [];
        foreach ($pages as $pageNumber => $page) {
            foreach ($page['external_links'] ?? [] as $url) {
                $urls[$url] ??= ['url' => $url, 'source_page' => $pageNumber];
            }
        }

        return array_values($urls);
    }

    /** @param array<int, array<string, mixed>> $pages @return list<string> */
    private function parseFinalExam(array $pages): array
    {
        $questions = [];
        foreach (range(253, 256) as $pageNumber) {
            foreach (preg_split('/\R/u', (string) ($pages[$pageNumber]['text'] ?? '')) ?: [] as $rawLine) {
                $line = trim($rawLine);
                if ($this->isNoise($line) || preg_match('/^\d+\.\d+\s+Section\s+/iu', $line)) {
                    continue;
                }
                if (preg_match('/^(\d{1,3})\.\s+(.+)$/u', $line, $match) && (int) $match[1] === count($questions) + 1) {
                    $questions[] = trim($match[2]);
                } elseif ($questions !== [] && ! preg_match('/^Final Comprehensive Examination$/iu', $line)) {
                    $last = array_key_last($questions);
                    $questions[$last] .= ' '.$line;
                }
            }
        }

        return $questions;
    }

    /** @param array<int, array{start: int, end: int}> $ranges */
    private function chapterForPage(int $page, array $ranges): int
    {
        foreach ($ranges as $chapter => $range) {
            if ($page >= $range['start'] && $page <= $range['end']) {
                return $chapter;
            }
        }

        throw new RuntimeException("No chapter range contains source page {$page}.");
    }

    private function isNoise(string $line): bool
    {
        return $line === ''
            || preg_match('/^\d{1,4}$/', $line) === 1
            || preg_match('/^Forex Trading From First Principles.*Version 1\.0$/iu', $line) === 1
            || preg_match('/^Chapter\s+\d+$/iu', $line) === 1;
    }
}
