<?php

return [
    'audit_path' => env('COURSE_AUDIT_PATH', base_path('../tmp/pdfs/source-audit')),
    'ai_tutor_enabled' => env('AI_ENABLED', false),
    'ai_assessment_enabled' => env('AI_SEMANTIC_GRADING_ENABLED', false),
];
