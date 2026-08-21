# No-AI mode test

Configuration: `AI_ENABLED=false`, no provider keys, `RAG_ENABLED=true`, semantic vectors disabled.

Result: PASS for the canonical curriculum.

- Registration/onboarding, lessons, navigation, notebooks, bookmarks, calculators, exercises, quizzes, mastery, progression, gates, strategy records, backtests, robustness, demo journal, exports, resources, and source citations execute without an AI request.
- Search Course and Ask the Course return locally retrieved, cited passages.
- Deterministic free-response rubrics provide the AI-independent equivalent pathway. Practical gates remain manual review.
- No provider HTTP call occurs during page view, progress update, objective grading, or deterministic calculation.

Automated regression suite result at the time of this document: 79 tests / 528 assertions before the final documentation pass; final counts are recorded in `AI_FINAL_AUDIT.md`.
