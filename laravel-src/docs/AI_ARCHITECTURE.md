# AI architecture

## Responsibility boundary

```text
Course engine ───────┐
Retrieval engine ────┼──> learner experience
AI manager/router ───┘
          │
          └── evidence recommendation ──> MasteryService ──> ProgressionService
```

- The course engine owns canonical lessons, assessments, gates, calculations, and source provenance.
- The retrieval engine performs local lexical search and optional locally stored vector ranking.
- The AI manager selects an allowed provider by task capability, preference, health, cost class, budget, and routing priority.
- The mastery/progression services alone write mastery and unlock state.

## Request path

1. Validate authorization/input and store the learner's submission.
2. Apply `TutorGuard` and active-assessment rules.
3. Retrieve only mapped course segments for the current unit.
4. Resolve runtime/user policy and provider candidates.
5. Call one provider with bounded connect/request timeouts.
6. On retryable outage/rate limit only, record failure, update the per-capability circuit breaker, and try the next allowed provider.
7. Validate structured output. Low-confidence or malformed assessment output is discarded.
8. Persist provider/model/task/prompt-version/fallback/latency metadata without storing secrets or duplicate prompt logs.

## Runtime modes

- `FULL_AI`: cloud provider available and paid use explicitly allowed.
- `FREE_AI`: allowed free/free-tier cloud provider available.
- `LOCAL_AI`: Ollama is the only allowed configured generator.
- `HYBRID_AI`: local and cloud generators are allowed.
- `RETRIEVAL_ONLY`: canonical search works but generation is unavailable/disabled.
- `NO_AI`: neither generation nor retrieval is available.

`DEGRADED` is represented in provider/capability health while the learner sees retrieval fallback or a friendly temporary-unavailability message.

## Retrieval

`CourseRetriever` scopes lesson tutoring to canonical/guided mappings. Lexical scoring is always available. When a ready embedding set exists, `SemanticSearchService` combines lexical and cosine scores. Embeddings are stored with source segment ID, provider, model, content SHA-256, dimensions, and index time. `ai:reindex-course` skips unchanged hashes.

SQLite JSON vectors are deliberately used for the current single-course corpus. PostgreSQL plus pgvector is the scale path; no Qdrant/Redis dependency is introduced.

## Assessment safety

Every canonical free-response item is graded by the deterministic rubric first. AI is considered only for an ambiguous partial/incorrect result and only when enabled. The deterministic rubric is the approved no-AI equivalent pathway. AI confidence is advisory: a score below 0.7, invalid schema, provider failure, or `unable_to_assess` result cannot replace the deterministic result. Practical gates remain human reviewed.

## Privacy and authorization

- Provider keys stay in environment/server config.
- Sensitive tasks are local-only unless a provider is explicitly marked suitable.
- Course retrieval queries only canonical tables. Student notes, journals, answers, and charts are never mixed into the course corpus.
- Tutor thread ownership is checked before reuse.
- Admin routes require verified authentication and the admin role; inactive accounts are rejected globally.

## Cost and resilience

Paid candidates are excluded unless the global paid policy is true. Daily/monthly provider budgets and per-user daily limits are enforced before execution. Circuit state is cached by provider and capability, so an embedding failure need not disable text generation. Non-retryable validation/auth/policy failures do not fan out.
