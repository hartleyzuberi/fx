<?php

namespace App\Services\Curriculum;

use Illuminate\Support\Facades\DB;

class CoverageAuditService
{
    /** @return array<string, int|float|bool> */
    public function report(): array
    {
        $meaningful = DB::table('source_segments')->where('is_meaningful', true)->count();
        $mapped = DB::table('source_segments')->where('is_meaningful', true)
            ->whereExists(fn ($query) => $query->selectRaw('1')->from('content_mappings')->whereColumn('content_mappings.source_segment_id', 'source_segments.id'))
            ->count();
        $pages = DB::table('source_pages')->count();
        $pagesWithSegments = DB::table('source_pages')
            ->whereExists(fn ($query) => $query->selectRaw('1')->from('source_segments')->whereColumn('source_segments.source_page_id', 'source_pages.id'))
            ->count();
        $duplicatePages = DB::table('source_pages as guided')
            ->join('source_versions as versions', 'versions.id', '=', 'guided.source_version_id')
            ->join('source_documents as documents', 'documents.id', '=', 'versions.source_document_id')
            ->where('documents.key', 'tutor_led')
            ->whereExists(function ($query) {
                $query->selectRaw('1')->from('source_segments as segments')
                    ->join('source_relations as relations', 'relations.from_segment_id', '=', 'segments.id')
                    ->whereColumn('segments.source_page_id', 'guided.id')->where('relations.relation_type', 'duplicate_of');
            })->count();
        $chapters = DB::table('learning_units')->where('unit_type', 'session')->count();
        $appendices = DB::table('learning_units')->where('unit_type', 'appendix')->count();
        $quizzes = DB::table('assessments')->where('assessment_type', 'chapter_quiz')->count();
        $quizQuestions = DB::table('questions')->join('assessments', 'assessments.id', '=', 'questions.assessment_id')->where('assessments.assessment_type', 'chapter_quiz')->count();
        $gateExams = DB::table('assessments')->where('assessment_type', 'gate_exam')->count();
        $gateQuestions = DB::table('questions')->join('assessments', 'assessments.id', '=', 'questions.assessment_id')->where('assessments.assessment_type', 'gate_exam')->count();
        $finalExams = DB::table('assessments')->where('assessment_type', 'final_exam')->count();
        $finalExamQuestions = DB::table('questions')->join('assessments', 'assessments.id', '=', 'questions.assessment_id')->where('assessments.assessment_type', 'final_exam')->count();
        $exercises = DB::table('practice_assignments')->where('assignment_type', 'chapter_exercise')->count();
        $urls = DB::table('learning_resources')->where('resource_type', 'external_url')->count();
        $books = DB::table('learning_resources')->where('resource_type', 'book')->count();
        $papers = DB::table('learning_resources')->where('resource_type', 'academic_paper')->count();
        $approvedMappings = DB::table('content_mappings')->where('review_status', 'approved')->count();
        $openConflicts = DB::table('source_conflicts')->where('status', 'open')->count();
        $structuredParity = $chapters === 90 && $appendices === 26 && $quizzes === 90 && $quizQuestions === 540
            && $gateExams === 7 && $gateQuestions === 142 && $finalExams === 1 && $finalExamQuestions === 100
            && $exercises === 379 && $urls === 63 && $books === 12 && $papers === 4;

        return [
            'source_pages' => $pages,
            'pages_with_segments' => $pagesWithSegments,
            'meaningful_segments' => $meaningful,
            'mapped_meaningful_segments' => $mapped,
            'unmapped_meaningful_segments' => $meaningful - $mapped,
            'coverage_percent' => $meaningful === 0 ? 0 : round(($mapped / $meaningful) * 100, 4),
            'duplicate_guided_pages_linked' => $duplicatePages,
            'expected_duplicate_guided_pages' => 328,
            'passes_machine_coverage' => $pages === 928 && $pagesWithSegments === 928 && $meaningful === $mapped && $duplicatePages === 328,
            'chapter_units' => $chapters,
            'appendix_units' => $appendices,
            'chapter_quizzes' => $quizzes,
            'chapter_quiz_questions' => $quizQuestions,
            'gate_exams' => $gateExams,
            'gate_questions' => $gateQuestions,
            'final_exams' => $finalExams,
            'final_exam_questions' => $finalExamQuestions,
            'chapter_exercises' => $exercises,
            'external_urls' => $urls,
            'book_references' => $books,
            'academic_papers' => $papers,
            'editorially_approved_mappings' => $approvedMappings,
            'open_source_conflicts' => $openConflicts,
            'passes_structured_parity' => $structuredParity,
        ];
    }
}
