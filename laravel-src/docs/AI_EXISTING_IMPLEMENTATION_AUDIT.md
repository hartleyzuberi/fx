# AI existing implementation audit

Audited: 2026-08-21

## Executive summary

The completed application is course-first and already remains usable when AI is disabled. It has deterministic curriculum, assessment, progression, notebooks, calculators, practice, strategy, backtest, demo-journal, provenance, and lexical course retrieval. AI is presently an optional OpenAI-only enhancement. The refactor must preserve the deterministic and provenance layers while replacing the two direct provider dependencies.

## Existing AI surfaces

- `App\Services\Tutor\TutorService` coordinates tutor guard, retrieval, and generation.
- `App\Services\Tutor\OpenAIResponsesClient` calls OpenAI Responses directly.
- `App\Services\Assessment\OpenAIAssessmentGrader` calls OpenAI Responses directly and validates structured output.
- `App\Services\Assessment\RubricGrader` provides an AI-independent rubric pathway, including contradiction detection.
- `App\Services\Tutor\CourseRetriever` retrieves canonical/guided source segments from the relational database using lexical scoring and lesson scoping.
- `App\Services\Tutor\TutorGuard` blocks prompt injection patterns, active-assessment answer requests, external URL fetching, and protected-prompt overlap.
- `TutorController` owns tutor threads/messages and usage-event persistence.
- `AssessmentAttemptController` grades deterministically first and currently replaces that recommendation with optional OpenAI grading when enabled.

## Direct provider dependencies

1. `OpenAIResponsesClient::tutorReply()` posts to `https://api.openai.com/v1/responses`.
2. `OpenAIAssessmentGrader::grade()` posts to `https://api.openai.com/v1/responses`.
3. `TutorService` depends directly on `OpenAIResponsesClient`.
4. `AssessmentAttemptController` depends directly on `OpenAIAssessmentGrader`.
5. `config/course.php`, `.env.example`, and `OpenAIContractTest` encode OpenAI-specific configuration and tests.

No other application path calls an AI provider.

## Prompts and schemas

- Tutor prompt requires course-only grounding, inline source labels, prompt-content distrust, no signals/advice, and a short Socratic follow-up.
- Grading prompt requires rubric-based semantic assessment, contradiction detection, citations, and explicitly denies progression authority.
- Grading output is server-validated for status, score, concept lists, feedback, follow-up, remediation, and citations.
- Prompt text is embedded in provider clients and is not versioned.

## Persistence

Existing relevant tables:

- `tutor_threads`, `tutor_messages`: conversation, citations, model, provider response ID, safety status, metadata.
- `ai_usage_events`: user, feature, model, token counts, estimated cost, status, latency, timestamp. It lacks provider, task, fallback, error, prompt version, and capability fields.
- `grading_recommendations`: grader type, model, structured recommendation, citations, and raw metadata.
- `feature_flags`: admin-controlled boolean flags with configuration JSON.
- `admin_audit_events`: actor, subject, before/after values, IP, and timestamp.
- `source_segments`, `content_mappings`, and related provenance tables: authoritative retrieval corpus.

There are no stored OpenAI vector-store IDs or provider-managed thread IDs. Existing response IDs and model metadata must remain readable.

## Retrieval and embeddings

- Retrieval is locally controlled, relational, lesson-scoped, canonical/guided-only, and requires no provider.
- Ranking is lexical token occurrence over at most 250 mapped segments.
- There is no vector index, embedding provider, embedding record, index state, or semantic evaluation command.
- Source citations are constructed from actual retrieved segment IDs, document titles, and physical pages.

## Assessment and progression boundary

- Normal grading begins with deterministic `RubricGrader` output.
- `MasteryService` records evidence and computes concept mastery.
- `ProgressionService` applies score, notebook, and practical-gate requirements and alone unlocks subsequent units.
- Gate/final practical evidence is manually reviewed by an administrator.
- No model has database-write access or direct progression authority.

This boundary is correct and must be retained. The deterministic rubric is the existing AI-independent equivalent path for canonical semantic questions; manual practical review remains the equivalent for gates.

## User preference and UI

- `learner_profiles.ai_tutor_enabled` and `ai_consent_at` exist.
- Onboarding exposes the optional tutor preference.
- Lesson UI sends an explicit request only when the learner asks; opening/navigating a lesson makes zero AI calls.
- There are no separate local/cloud preferences or a settings page for changing them after onboarding.
- When AI is unavailable, the current tutor endpoint returns 503 even though course retrieval could still return useful excerpts.

## Administration and monitoring

- Admin role middleware and audit events exist.
- The content-audit page shows aggregate AI request/token/cost totals and feature flags.
- It does not manage users, providers, routing, budgets, health, prompts, or per-provider usage.
- There is no first-admin provisioning command.

## Jobs and commands

- Course source ingestion, coverage audit, and resource freshness commands/jobs exist.
- There are no AI status, provider smoke-test, evaluation, reindex, or usage-report commands.
- Normal CI uses HTTP fakes for the OpenAI contract and does not require a live key.

## Environment and secrets

- AI defaults off.
- Only OpenAI key/model settings exist.
- Keys stay server-side and are not shared with Inertia.
- Model defaults are hard-coded and should be removed from runtime defaults.

## Existing tests

- OpenAI tutor grounding and strict grading schema contract tests.
- Tutor guard tests for answer shielding, injection, URL removal, and protected-prompt overlap.
- Rubric tests for natural paraphrase and direction-reversal misconception.
- Progression/mastery/admin authorization tests confirm application-owned state transitions.

Missing coverage includes provider routing/fallback, paid-policy enforcement, circuit breaking, runtime modes, local/cloud preference permutations, provider-neutral grading, retrieval index states, user administration, idempotent AI submissions, and key non-disclosure.

## Preserve unchanged

Canonical curriculum data, source mappings, deterministic graders/calculators, mastery thresholds, progression rules, gate workflow, evidence ownership, notebooks, practice records, strategies, backtests, demo journals, and resource library are outside the refactor except for compatible metadata additions and tests.
