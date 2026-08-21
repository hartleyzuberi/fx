# AI delta implementation plan

Based on `AI_EXISTING_IMPLEMENTATION_AUDIT.md`. The plan implements the smallest provider-neutral layer that satisfies the runtime and safety requirements without introducing a separate service, Redis requirement, or external vector database.

## KEEP AS-IS

- Canonical course, source provenance, mappings, and relational storage: already authoritative and locally controlled.
- Deterministic calculators, objective grading, and `RubricGrader`: they satisfy deterministic-first and the no-AI mastery pathway.
- `MasteryService` and `ProgressionService`: Laravel already owns mastery and unlocking.
- Tutor threads/messages and admin audit log: extend metadata without deleting historical OpenAI records.
- `TutorGuard`: retain and broaden its current assessment, injection, and signal protections.
- Feature-flag table: use it for capability-level runtime switches.

## MODIFY

- Replace direct `OpenAIResponsesClient` and `OpenAIAssessmentGrader` dependencies with domain-level AI requests through a manager/router.
- Expand AI usage events with provider, task, fallback, error, prompt version, capability, and request ID.
- Expand learner preference from one consent flag to separate cloud/local permission while preserving the original switch.
- Make Ask the Course return grounded retrieval results when generation is disabled/unavailable rather than disappearing.
- Route semantic grading through deterministic grading first, then optional validated AI evidence; application services remain authoritative.
- Extend admin navigation and pages for users and AI operations.
- Replace provider/model-specific environment defaults with explicit, blank, configuration-driven models.

## ADD

- Provider contract, request/response DTOs, typed provider failures, registry, capability-aware router, manager, health/circuit cache, usage recorder, and runtime-mode resolver.
- Groq, Gemini, OpenRouter, Ollama, and optional OpenAI adapters using Laravel HTTP, bounded timeouts, and no browser-exposed keys.
- Provider metadata for privacy, cost class, capabilities, and verification date.
- Database-backed provider overrides/routing plus budgets and health summaries; secrets remain environment-only.
- Prompt-version registry for tutor and semantic grader prompts.
- Provider-independent retrieval service, local embedding records/index state, Ollama/OpenAI/Gemini embedding adapters, hash-based incremental reindexing, and lexical fallback.
- Course-search endpoint/UI that works without a generative model.
- Admin user list, progress summary, safe role/account updates, audit history, and first-admin command.
- Admin AI panel for provider state, routing, feature controls, usage, health/test action, and runtime mode.
- User AI preference settings page.
- AI status, provider test, model evaluation, reindex, and usage-report commands.
- Evaluation fixtures, routing/security/permutation tests, and required operational documentation.

## DEFER

- Dedicated pgvector/Qdrant: SQLite plus JSON vectors and bounded in-process cosine ranking is sufficient for the current 5,807-segment single-course corpus. PostgreSQL/pgvector is the documented scale path.
- Redis-only circuit infrastructure: Laravel cache provides process-safe cooldown state and works with the existing database cache driver.
- Live web/current-information retrieval: disabled by default because no authoritative-source connector is configured. The tutor must identify current-data questions and fail safely.
- Arbitrary prompt editing: admins can inspect versions; deploy-reviewed prompt changes avoid weakening protected assessment instructions.
- Automatic paid/free classification from provider billing APIs: provider metadata is dated and admin-controlled; uncertain classification fails closed when paid fallback is prohibited.
- Mandatory live-provider CI and benchmark claims: commands are supplied, but modes without credentials/hardware are reported `NOT CONFIGURED`, never `PASS`.
- Large-model self-hosting or GPU provisioning: Ollama support accepts a separate host and does not assume suitable web-server hardware.
- Multi-tenancy: owner/source fields and retrieval scopes remain compatible with future tenant filtering, but tenancy is not introduced now.
